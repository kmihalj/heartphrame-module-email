<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Service;

use RuntimeException;

use function filter_var;
use function in_array;
use function is_dir;
use function is_numeric;
use function is_scalar;
use function mkdir;
use function strtolower;
use function trim;
use function var_export;

use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_URL;

/**
 * HR: Validira administratorsku SMTP formu i zapisuje čistu host konfiguraciju.
 * EN: Validates the administrator SMTP form and writes clean host configuration.
 */
final readonly class EmailSettingsService
{
    /**
     * HR: Prima efektivnu konfiguraciju, uključujući postojeću skrivenu lozinku.
     * EN: Receives effective configuration, including the existing hidden password.
     */
    public function __construct(private EmailConfig $config)
    {
    }

    /**
     * HR: Priprema sigurne vrijednosti forme bez vraćanja SMTP lozinke pregledniku.
     * EN: Prepares safe form values without returning the SMTP password to the browser.
     *
     * @return array<string, mixed>
     */
    public function settingsForForm(): array
    {
        return [
            'enabled' => $this->config->enabled(),
            'host' => $this->config->host(),
            'port' => $this->config->port(),
            'encryption' => $this->config->encryption(),
            'username' => $this->config->username(),
            'password_is_set' => $this->config->password() !== '',
            'connect_timeout' => $this->config->connectTimeout(),
            'io_timeout' => $this->config->ioTimeout(),
            'verify_peer' => $this->config->verifyPeer(),
            'allow_self_signed' => $this->config->allowSelfSigned(),
            'sender_email' => $this->config->senderEmail(),
            'sender_name' => $this->config->senderName(),
            'application_base_url' => $this->config->applicationBaseUrl(),
            'notifications_enabled' => $this->config->notificationsEnabled(),
            'max_attempts' => $this->config->maxAttempts(),
            'retry_delay_seconds' => $this->config->retryDelaySeconds(),
            'settings_file_path' => $this->config->settingsFilePath(),
        ];
    }

    /**
     * HR: Sprema normalizirane postavke; prazno password polje čuva staru lozinku.
     * EN: Saves normalized settings; a blank password field preserves the old password.
     *
     * @param array<string, mixed> $input
     */
    public function saveFromForm(array $input): void
    {
        $enabled = $this->boolValue($input['enabled'] ?? false);
        $host = $this->stringValue($input['host'] ?? '');
        $port = $this->integer($input['port'] ?? 587, 587, 1, 65535);
        $encryption = strtolower($this->stringValue($input['encryption'] ?? 'starttls'));
        $encryption = in_array($encryption, ['none', 'starttls', 'tls'], true)
        ? $encryption
        : 'starttls';
        $senderEmail = $this->stringValue($input['sender_email'] ?? '');
        $baseUrl = rtrim($this->stringValue($input['application_base_url'] ?? ''), '/');
        if ($enabled && $host === '') {
            throw new RuntimeException(__('SMTP host je obavezan kada je slanje uključeno.'));
        }

        if ($enabled && filter_var($senderEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException(__('Adresa pošiljatelja nije valjana.'));
        }

        if ($baseUrl !== '' && filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException(__('Javni URL aplikacije nije valjan.'));
        }

        $newPassword = $this->stringValue($input['password'] ?? '');
        $settings = [
            'enabled' => $enabled,
            'smtp' => [
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $this->stringValue($input['username'] ?? ''),
                'password' => $newPassword !== '' ? $newPassword : $this->config->password(),
                'connect_timeout' => $this->integer(
                    $input['connect_timeout'] ?? 15,
                    15,
                    1,
                    120,
                ),
                'io_timeout' => $this->integer($input['io_timeout'] ?? 30, 30, 1, 300),
                'verify_peer' => $this->boolValue($input['verify_peer'] ?? false),
                'allow_self_signed' => $this->boolValue($input['allow_self_signed'] ?? false),
            ],
            'sender' => [
                'email' => $senderEmail,
                'name' => $this->stringValue($input['sender_name'] ?? 'HeartPhrame'),
            ],
            'application_base_url' => $baseUrl,
            'notifications_enabled' => $this->boolValue($input['notifications_enabled'] ?? false),
            'worker' => [
                'max_attempts' => $this->integer($input['max_attempts'] ?? 5, 5, 1, 100),
                'retry_delay_seconds' => $this->integer(
                    $input['retry_delay_seconds'] ?? 60,
                    60,
                    1,
                    86400,
                ),
            ],
            'menu' => ['auto_register_settings' => true],
        ];

        $path = $this->config->settingsFilePath();
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(__('Nije moguće kreirati direktorij E-mail postavki.'));
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\n"
        . "// HR: Aplikacijske SMTP postavke koje je spremio E-mail modul.\n"
        . "// EN: Application SMTP settings saved by the E-mail module.\n"
        . "return "
        . var_export($settings, true)
        . ";\n";
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException(__('Nije moguće zapisati E-mail postavke.'));
        }
    }

    /**
     * HR: Pretvara checkbox i druge skalarne vrijednosti u boolean.
     * EN: Converts checkbox and other scalar values to boolean.
     */
    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return is_scalar($value)
        && in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * HR: Normalizira skalarni unos u string.
     * EN: Normalizes a scalar input to a string.
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * HR: Normalizira cijeli broj unutar sigurnog raspona.
     * EN: Normalizes an integer within a safe range.
     */
    private function integer(mixed $value, int $default, int $minimum, int $maximum): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return min($maximum, max($minimum, (int)$value));
    }
}
