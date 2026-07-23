<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Service;

use AaiEduHr\HeartPhrameModuleAuth\Service\AuthUserService;
use AaiEduHr\HeartPhrameModuleEmail\ModuleEmail;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use RuntimeException;

use function date;
use function filter_var;
use function is_array;
use function is_numeric;
use function is_scalar;
use function random_bytes;
use function sprintf;
use function str_starts_with;
use function trim;

use const FILTER_VALIDATE_EMAIL;

/**
 * HR: Javni API E-mail modula. Pozivatelji samo stavljaju poruku u trajni
 *     outbox; mrežno SMTP slanje obavlja worker.
 * EN: Public E-mail module API. Callers only queue a message in the persistent
 *     outbox; the worker performs network SMTP delivery.
 */
final readonly class EmailService
{
    /**
     * HR: Prima ORM bazu, efektivne postavke i Auth direktorij korisnika.
     * EN: Receives the ORM database, effective settings, and Auth user directory.
     */
    public function __construct(
        private Database $database,
        private EmailConfig $config,
        private AuthUserService $users,
    ) {
    }

    /**
     * HR: Provjerava je li inicijalna outbox migracija primijenjena.
     * EN: Checks whether the initial outbox migration has been applied.
     */
    public function tablesReady(): bool
    {
        return $this->database->schema()->hasTable(ModuleEmail::TABLE_OUTBOX);
    }

    /**
     * HR: Dohvaća aktivnog korisnika i stavlja poruku na njegovu profilnu e-mail adresu.
     * EN: Loads an active user and queues a message for the profile e-mail address.
     *
     * @return array<string, mixed>|null
     */
    public function queueForUser(
        int $userId,
        string $subject,
        string $bodyText,
        ?string $bodyHtml = null,
        ?string $dedupKey = null,
        string $linkUrl = '',
    ): ?array {
        if (!$this->config->notificationsEnabled()) {
            return null;
        }

        $user = $this->users->findById($userId);
        if (!is_array($user)) {
            return null;
        }

        $email = $this->stringValue($user['email'] ?? '');
        if (!is_string(filter_var($email, FILTER_VALIDATE_EMAIL))) {
            return null;
        }

        $name = $this->stringValue(
            $user['display_name'] ?? $user['login_identifier'] ?? '',
        );

        return $this->queue(
            $email,
            $name,
            $subject,
            $bodyText,
            $bodyHtml,
            $dedupKey,
            $userId,
            $linkUrl,
        );
    }

    /**
     * HR: Sprema generičku poruku u outbox. Kada je slanje isključeno vraća
     *     null i ne gomila stare poruke za neočekivano kasnije slanje.
     * EN: Stores a generic message in the outbox. When delivery is disabled it
     *     returns null and does not accumulate messages for unexpected later delivery.
     *
     * @return array<string, mixed>|null
     */
    public function queue(
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $bodyText,
        ?string $bodyHtml = null,
        ?string $dedupKey = null,
        ?int $userId = null,
        string $linkUrl = '',
    ): ?array {
        if (!$this->config->enabled()) {
            return null;
        }

        $this->assertTablesReady();
        $message = new EmailMessage(
            trim($recipientEmail),
            trim($recipientName),
            trim($subject),
            $this->appendLink(trim($bodyText), $linkUrl),
            $bodyHtml !== null ? $this->appendHtmlLink(trim($bodyHtml), $linkUrl) : null,
        );
        $dedupKey = trim((string)$dedupKey);
        if ($dedupKey !== '') {
            $existing = $this->findByDedupKey($dedupKey);
            if (is_array($existing)) {
                return $existing;
            }
        }

        $now = date('Y-m-d H:i:s');
        $this->database->table(ModuleEmail::TABLE_OUTBOX)->insert([
            'uuid' => $this->uuid(),
            'dedup_key' => $dedupKey !== '' ? $dedupKey : null,
            'user_id' => $userId !== null && $userId > 0 ? $userId : null,
            'recipient_email' => $message->recipientEmail,
            'recipient_name' => $message->recipientName !== '' ? $message->recipientName : null,
            'subject' => $message->subject,
            'body_text' => $message->bodyText,
            'body_html' => $message->bodyHtml,
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => $now,
            'locked_at' => null,
            'sent_at' => null,
            'last_error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = $this->findById((int)$this->database->lastInsertId());
        if (!is_array($row)) {
            throw new RuntimeException(__('Spremljenu e-mail poruku nije moguće učitati.'));
        }

        return $row;
    }

    /**
     * HR: Učitava outbox redak po javnom UUID-u za CLI i test SMTP akciju.
     * EN: Loads an outbox row by public UUID for CLI and the SMTP test action.
     *
     * @return array<string, mixed>|null
     */
    public function findByUuid(string $uuid): ?array
    {
        if (!$this->tablesReady() || trim($uuid) === '') {
            return null;
        }

        $row = $this->database->table(ModuleEmail::TABLE_OUTBOX)
            ->where('uuid', '=', trim($uuid))
            ->first();

        return $this->row($row);
    }

    /**
     * HR: Vraća broj poruka po statusu za administratorsku dijagnostiku.
     * EN: Returns message counts by status for administrator diagnostics.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0];
        if (!$this->tablesReady()) {
            return $counts;
        }

        $rows = $this->database->table(ModuleEmail::TABLE_OUTBOX)
            ->select(['status', 'COUNT(*) AS aggregate'])
            ->groupBy('status')
            ->get();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $status = $this->stringValue($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status] = $this->intValue($row['aggregate'] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * HR: Učitava redak po internom ID-u nakon inserta.
     * EN: Loads a row by internal ID after insertion.
     *
     * @return array<string, mixed>|null
     */
    private function findById(int $id): ?array
    {
        $row = $this->database->table(ModuleEmail::TABLE_OUTBOX)
            ->where('id', '=', $id)
            ->first();

        return $this->row($row);
    }

    /**
     * HR: Traži postojeću poruku po globalnom dedup ključu.
     * EN: Finds an existing message by its global deduplication key.
     *
     * @return array<string, mixed>|null
     */
    private function findByDedupKey(string $dedupKey): ?array
    {
        $row = $this->database->table(ModuleEmail::TABLE_OUTBOX)
            ->where('dedup_key', '=', $dedupKey)
            ->first();

        return $this->row($row);
    }

    /**
     * HR: Pretvara generički ORM rezultat u redak sa string ključevima.
     * EN: Converts a generic ORM result into a string-keyed row.
     *
     * @return array<string, mixed>|null
     */
    private function row(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $row = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $row[$key] = $item;
            }
        }

        return $row;
    }

    /**
     * HR: Dodaje apsolutnu poveznicu na tekstualnu poruku kada je podešen javni URL.
     * EN: Appends an absolute link to the text body when a public URL is configured.
     */
    private function appendLink(string $body, string $linkUrl): string
    {
        $link = $this->absoluteLink($linkUrl);

        return $link !== '' ? rtrim($body) . "\n\n" . $link : $body;
    }

    /**
     * HR: Dodaje escaped poveznicu na HTML poruku kada je dostupna.
     * EN: Appends an escaped link to the HTML body when available.
     */
    private function appendHtmlLink(string $body, string $linkUrl): string
    {
        $link = $this->absoluteLink($linkUrl);
        if ($link === '') {
            return $body;
        }

        $escaped = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        return rtrim($body) . '<p><a href="' . $escaped . '">' . $escaped . '</a></p>';
    }

    /**
     * HR: Pretvara lokalnu putanju u javni URL; vanjski URL ostavlja nepromijenjen.
     * EN: Converts a local path to a public URL and leaves an external URL unchanged.
     */
    private function absoluteLink(string $linkUrl): string
    {
        $linkUrl = trim($linkUrl);
        if ($linkUrl === '') {
            return '';
        }

        if (str_starts_with($linkUrl, 'https://') || str_starts_with($linkUrl, 'http://')) {
            return $linkUrl;
        }

        $base = $this->config->applicationBaseUrl();

        return $base !== '' && str_starts_with($linkUrl, '/')
        ? $base . $linkUrl
        : '';
    }

    /**
     * HR: Zaustavlja queue s jasnom porukom kada migracija nedostaje.
     * EN: Stops queueing with a clear message when the migration is missing.
     */
    private function assertTablesReady(): void
    {
        if (!$this->tablesReady()) {
            throw new RuntimeException(__('E-mail migracija nije primijenjena.'));
        }
    }

    /**
     * HR: Normalizira skalarnu vrijednost u string.
     * EN: Normalizes a scalar value to a string.
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * HR: Pretvara numeričku vrijednost u integer.
     * EN: Converts a numeric value to an integer.
     */
    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * HR: Generira kriptografski nasumičan UUID v4 bez vanjskog paketa.
     * EN: Generates a cryptographically random UUID v4 without an external package.
     */
    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
