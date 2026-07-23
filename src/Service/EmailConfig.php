<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Service;

use HeartPhrame\Config\ConfigInterface;

use function array_replace_recursive;
use function in_array;
use function is_array;
use function is_file;
use function is_numeric;
use function is_scalar;
use function max;
use function min;
use function rtrim;
use function strtolower;
use function trim;

/**
 * HR: Spaja zadane postavke modula s host datotekom `config/email.php` i
 *     izlaže normalizirane vrijednosti ostalim servisima.
 * EN: Merges module defaults with host `config/email.php` and exposes
 *     normalized values to the remaining services.
 */
final readonly class EmailConfig
{
    /**
     * @var array<string, mixed>
     */
    private array $emailConfig;

    /**
     * HR: Prima framework konfiguraciju i root modula.
     * EN: Receives framework configuration and the module root.
     */
    public function __construct(
        private ConfigInterface $config,
        private string $moduleRoot,
    ) {
        $this->emailConfig = $this->load();
    }

    /**
     * HR: Određuje smije li se nova pošta stavljati u red.
     * EN: Determines whether new mail may be queued.
     */
    public function enabled(): bool
    {
        return (bool)($this->emailConfig['enabled'] ?? false);
    }

    /**
     * HR: Određuje smiju li in-app obavijesti stvarati e-mail kopije.
     * EN: Determines whether in-app notifications may create e-mail copies.
     */
    public function notificationsEnabled(): bool
    {
        return (bool)($this->emailConfig['notifications_enabled'] ?? true);
    }

    /**
     * HR: Vraća SMTP host.
     * EN: Returns the SMTP host.
     */
    public function host(): string
    {
        return $this->string($this->smtp()['host'] ?? '');
    }

    /**
     * HR: Vraća SMTP port u valjanom rasponu.
     * EN: Returns the SMTP port in a valid range.
     */
    public function port(): int
    {
        return $this->integer($this->smtp()['port'] ?? 587, 587, 1, 65535);
    }

    /**
     * HR: Vraća `none`, `starttls` ili `tls`.
     * EN: Returns `none`, `starttls`, or `tls`.
     */
    public function encryption(): string
    {
        $value = strtolower($this->string($this->smtp()['encryption'] ?? 'starttls'));

        return in_array($value, ['none', 'starttls', 'tls'], true) ? $value : 'starttls';
    }

    /**
     * HR: Vraća opcionalno SMTP korisničko ime.
     * EN: Returns the optional SMTP username.
     */
    public function username(): string
    {
        return $this->string($this->smtp()['username'] ?? '');
    }

    /**
     * HR: Vraća opcionalnu SMTP lozinku.
     * EN: Returns the optional SMTP password.
     */
    public function password(): string
    {
        return $this->string($this->smtp()['password'] ?? '');
    }

    /**
     * HR: Vraća timeout uspostave veze.
     * EN: Returns the connection timeout.
     */
    public function connectTimeout(): int
    {
        return $this->integer($this->smtp()['connect_timeout'] ?? 15, 15, 1, 120);
    }

    /**
     * HR: Vraća timeout čitanja i pisanja SMTP razgovora.
     * EN: Returns the SMTP conversation read/write timeout.
     */
    public function ioTimeout(): int
    {
        return $this->integer($this->smtp()['io_timeout'] ?? 30, 30, 1, 300);
    }

    /**
     * HR: Određuje mora li TLS certifikat biti valjan.
     * EN: Determines whether the TLS certificate must be valid.
     */
    public function verifyPeer(): bool
    {
        return (bool)($this->smtp()['verify_peer'] ?? true);
    }

    /**
     * HR: Određuje dopušta li se self-signed TLS certifikat.
     * EN: Determines whether a self-signed TLS certificate is allowed.
     */
    public function allowSelfSigned(): bool
    {
        return (bool)($this->smtp()['allow_self_signed'] ?? false);
    }

    /**
     * HR: Vraća adresu pošiljatelja.
     * EN: Returns the sender address.
     */
    public function senderEmail(): string
    {
        return $this->string($this->sender()['email'] ?? '');
    }

    /**
     * HR: Vraća prikazno ime pošiljatelja.
     * EN: Returns the sender display name.
     */
    public function senderName(): string
    {
        return $this->string($this->sender()['name'] ?? 'HeartPhrame');
    }

    /**
     * HR: Vraća opcionalni javni URL aplikacije za linkove u porukama.
     * EN: Returns the optional public application URL for links in messages.
     */
    public function applicationBaseUrl(): string
    {
        return rtrim($this->string($this->emailConfig['application_base_url'] ?? ''), '/');
    }

    /**
     * HR: Vraća maksimalan broj pokušaja slanja.
     * EN: Returns the maximum delivery attempt count.
     */
    public function maxAttempts(): int
    {
        return $this->integer($this->worker()['max_attempts'] ?? 5, 5, 1, 100);
    }

    /**
     * HR: Vraća osnovnu odgodu retryja u sekundama.
     * EN: Returns the base retry delay in seconds.
     */
    public function retryDelaySeconds(): int
    {
        return $this->integer($this->worker()['retry_delay_seconds'] ?? 60, 60, 1, 86400);
    }

    /**
     * HR: Određuje treba li automatski dodati E-mail u settings meni.
     * EN: Determines whether E-mail should be auto-added to the settings menu.
     */
    public function shouldAutoRegisterSettingsMenu(): bool
    {
        $menu = $this->section('menu');

        return (bool)($menu['auto_register_settings'] ?? true);
    }

    /**
     * HR: Vraća apsolutnu putanju host konfiguracije.
     * EN: Returns the absolute host configuration path.
     */
    public function settingsFilePath(): string
    {
        return rtrim($this->config->getAppRootDir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'config'
        . DIRECTORY_SEPARATOR
        . 'email.php';
    }

    /**
     * HR: Vraća sve efektivne postavke za settings servis.
     * EN: Returns all effective settings for the settings service.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->emailConfig;
    }

    /**
     * HR: Vraća SMTP sekciju.
     * EN: Returns the SMTP section.
     *
     * @return array<string, mixed>
     */
    private function smtp(): array
    {
        return $this->section('smtp');
    }

    /**
     * HR: Vraća sender sekciju.
     * EN: Returns the sender section.
     *
     * @return array<string, mixed>
     */
    private function sender(): array
    {
        return $this->section('sender');
    }

    /**
     * HR: Vraća worker sekciju.
     * EN: Returns the worker section.
     *
     * @return array<string, mixed>
     */
    private function worker(): array
    {
        return $this->section('worker');
    }

    /**
     * HR: Vraća jednu konfiguracijsku sekciju.
     * EN: Returns one configuration section.
     *
     * @return array<string, mixed>
     */
    private function section(string $key): array
    {
        return $this->associativeArray($this->emailConfig[$key] ?? []);
    }

    /**
     * HR: Učitava module defaults i opcionalni host override.
     * EN: Loads module defaults and the optional host override.
     *
     * @return array<string, mixed>
     */
    private function load(): array
    {
        $defaults = $this->associativeArray(
            require $this->moduleRoot . '/config/email.php',
        );

        $path = $this->settingsFilePath();
        if (!is_file($path)) {
            return $defaults;
        }

        $override = $this->associativeArray(require $path);

        return $this->associativeArray(
            array_replace_recursive($defaults, $override),
        );
    }

    /**
     * HR: Pretvara vanjsku konfiguracijsku vrijednost u mapu sa string ključevima.
     * EN: Converts an external configuration value into a string-keyed map.
     *
     * @return array<string, mixed>
     */
    private function associativeArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * HR: Normalizira skalarnu vrijednost u string.
     * EN: Normalizes a scalar value to a string.
     */
    private function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * HR: Normalizira cijeli broj u zadanom rasponu.
     * EN: Normalizes an integer within the supplied range.
     */
    private function integer(mixed $value, int $default, int $minimum, int $maximum): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return min($maximum, max($minimum, (int)$value));
    }
}
