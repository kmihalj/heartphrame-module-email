<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Service;

use AaiEduHr\HeartPhrameModuleEmail\ModuleEmail;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use RuntimeException;
use Throwable;

use function date;
use function is_array;
use function is_numeric;
use function is_scalar;
use function max;
use function min;
use function time;
use function trim;

/**
 * HR: Sigurno preuzima outbox retke, šalje ih čistim PHP SMTP klijentom i
 *     vraća neuspjele poruke u red s rastućom odgodom.
 * EN: Safely claims outbox rows, sends them through the pure-PHP SMTP client,
 *     and requeues failures with increasing delay.
 */
final readonly class EmailOutboxWorker
{
    private const STALE_LOCK_SECONDS = 900;

    /**
     * HR: Prima ORM, konfiguraciju i SMTP transport.
     * EN: Receives ORM, configuration, and SMTP transport.
     */
    public function __construct(
        private Database $database,
        private EmailConfig $config,
        private SmtpClient $smtp,
    ) {
    }

    /**
     * HR: Obrađuje ograničen broj poruka i vraća sažetak batcha.
     * EN: Processes a bounded number of messages and returns a batch summary.
     *
     * @return array{processed: int, sent: int, failed: int}
     */
    public function workBatch(int $limit = 20): array
    {
        if (!$this->config->enabled()) {
            throw new RuntimeException(__('E-mail slanje nije uključeno.'));
        }

        if (!$this->database->schema()->hasTable(ModuleEmail::TABLE_OUTBOX)) {
            throw new RuntimeException(__('E-mail migracija nije primijenjena.'));
        }

        $limit = min(100, max(1, $limit));
        $this->recoverStaleLocks();
        $summary = ['processed' => 0, 'sent' => 0, 'failed' => 0];

        for ($index = 0; $index < $limit; ++$index) {
            $row = $this->claimNext();
            if (!is_array($row)) {
                break;
            }

            ++$summary['processed'];
            if ($this->deliver($row)) {
                ++$summary['sent'];
            } else {
                ++$summary['failed'];
            }
        }

        return $summary;
    }

    /**
     * HR: Odmah obrađuje jednu poruku po UUID-u za SMTP test, uz ista pravila
     *     statusa i retryja kao redovni worker.
     * EN: Immediately processes one message by UUID for an SMTP test using the
     *     same status and retry rules as the regular worker.
     */
    public function workUuid(string $uuid): bool
    {
        if (!$this->config->enabled()) {
            throw new RuntimeException(__('E-mail slanje nije uključeno.'));
        }

        $result = $this->database->transaction(function (Database $database) use ($uuid): ?array {
            $candidate = $database->table(ModuleEmail::TABLE_OUTBOX)
                ->where('uuid', '=', trim($uuid))
                ->where('status', '=', 'pending')
                ->lockForUpdate()
                ->first();
            $candidate = $this->row($candidate);
            if ($candidate === null) {
                return null;
            }

            return $this->markSending($candidate);
        });
        $row = $this->row($result);

        if ($row === null) {
            throw new RuntimeException(__('Testna e-mail poruka nije pronađena u redu.'));
        }

        return $this->deliver($row);
    }

    /**
     * HR: Transakcijski preuzima sljedeći raspoloživ redak.
     * EN: Transactionally claims the next available row.
     *
     * @return array<string, mixed>|null
     */
    private function claimNext(): ?array
    {
        $now = date('Y-m-d H:i:s');

        $row = $this->database->transaction(function (Database $database) use ($now): ?array {
            $candidate = $database->table(ModuleEmail::TABLE_OUTBOX)
                ->where('status', '=', 'pending')
                ->where('available_at', '<=', $now)
                ->orderBy('available_at', 'ASC')
                ->orderBy('id', 'ASC')
                ->lockForUpdate()
                ->first();
            $candidate = $this->row($candidate);
            if ($candidate === null) {
                return null;
            }

            return $this->markSending($candidate);
        });

        return $this->row($row);
    }

    /**
     * HR: Označava zaključani redak aktivnim i povećava broj pokušaja.
     * EN: Marks a locked row as active and increments its attempt count.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function markSending(array $row): array
    {
        $now = date('Y-m-d H:i:s');
        $attempts = $this->intValue($row['attempts'] ?? 0) + 1;
        $this->database->table(ModuleEmail::TABLE_OUTBOX)
            ->where('id', '=', $this->intValue($row['id'] ?? 0))
            ->where('status', '=', 'pending')
            ->update([
                'status' => 'sending',
                'attempts' => $attempts,
                'locked_at' => $now,
                'updated_at' => $now,
            ]);
        $row['status'] = 'sending';
        $row['attempts'] = $attempts;
        $row['locked_at'] = $now;

        return $row;
    }

    /**
     * HR: Šalje preuzeti redak te zapisuje sent ili retry/failed rezultat.
     * EN: Sends a claimed row and records a sent or retry/failed result.
     *
     * @param array<string, mixed> $row
     */
    private function deliver(array $row): bool
    {
        try {
            $this->smtp->send($this->config, new EmailMessage(
                $this->stringValue($row['recipient_email'] ?? ''),
                $this->stringValue($row['recipient_name'] ?? ''),
                $this->stringValue($row['subject'] ?? ''),
                $this->stringValue($row['body_text'] ?? ''),
                $this->nullableString($row['body_html'] ?? null),
            ));
            $now = date('Y-m-d H:i:s');
            $this->database->table(ModuleEmail::TABLE_OUTBOX)
                ->where('id', '=', $this->intValue($row['id'] ?? 0))
                ->update([
                    'status' => 'sent',
                    'locked_at' => null,
                    'sent_at' => $now,
                    'last_error' => null,
                    'updated_at' => $now,
                ]);

            return true;
        } catch (Throwable $throwable) {
            $attempts = $this->intValue($row['attempts'] ?? 1);
            $terminal = $attempts >= $this->config->maxAttempts();
            $delay = $this->config->retryDelaySeconds() * max(1, $attempts);
            $now = date('Y-m-d H:i:s');
            $this->database->table(ModuleEmail::TABLE_OUTBOX)
                ->where('id', '=', $this->intValue($row['id'] ?? 0))
                ->update([
                    'status' => $terminal ? 'failed' : 'pending',
                    'available_at' => date('Y-m-d H:i:s', time() + $delay),
                    'locked_at' => null,
                    'last_error' => $throwable->getMessage(),
                    'updated_at' => $now,
                ]);

            return false;
        }
    }

    /**
     * HR: Vraća zaboravljene `sending` retke nakon rušenja procesa u pending stanje.
     * EN: Returns stale `sending` rows to pending after a worker process crash.
     */
    private function recoverStaleLocks(): void
    {
        $threshold = date('Y-m-d H:i:s', time() - self::STALE_LOCK_SECONDS);
        $now = date('Y-m-d H:i:s');
        $this->database->table(ModuleEmail::TABLE_OUTBOX)
            ->where('status', '=', 'sending')
            ->where('locked_at', '<=', $threshold)
            ->update([
                'status' => 'pending',
                'locked_at' => null,
                'available_at' => $now,
                'updated_at' => $now,
            ]);
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
     * HR: Normalizira DB string vrijednost.
     * EN: Normalizes a database string value.
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * HR: Normalizira opcionalni DB string.
     * EN: Normalizes an optional database string.
     */
    private function nullableString(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value !== '' ? $value : null;
    }

    /**
     * HR: Pretvara numeričku DB vrijednost u integer.
     * EN: Converts a numeric database value to an integer.
     */
    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }
}
