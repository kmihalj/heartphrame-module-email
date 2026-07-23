<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Tests;

use AaiEduHr\HeartPhrameModuleEmail\Service\EmailConfig;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailSettingsService;
use HeartPhrame\Config\Config;
use HeartPhrame\Helper\Helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_exists;
use function is_array;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(EmailSettingsService::class)]
#[UsesClass(EmailConfig::class)]
final class EmailSettingsServiceTest extends TestCase
{
    private string $appRoot = '';

    /**
     * HR: Nakon svakog testa uklanja privremenu host konfiguraciju s testnom lozinkom.
     * EN: Removes the temporary host configuration containing the test password after each test.
     */
    protected function tearDown(): void
    {
        if ($this->appRoot !== '') {
            $this->removeDirectory($this->appRoot);
        }
    }

    /**
     * HR: Dokazuje da administratorska SMTP forma sprema autentikaciju, ne
     *     vraća lozinku pregledniku i čuva je kada je polje ostavljeno prazno.
     * EN: Proves that the administrator SMTP form stores authentication, never
     *     returns the password to the browser, and preserves it when the field is blank.
     */
    public function testSmtpAuthenticationIsStoredButNeverReturnedToTheForm(): void
    {
        $this->appRoot = sys_get_temp_dir() . '/hph-email-settings-' . bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($this->appRoot . '/config', 0777, true));
        $config = $this->emailConfig($this->appRoot);
        $settings = new EmailSettingsService($config);

        $settings->saveFromForm([
            'enabled' => '1',
            'host' => 'smtp.example.test',
            'port' => '587',
            'encryption' => 'starttls',
            'username' => 'mailer',
            'password' => 'secret-password',
            'connect_timeout' => '12',
            'io_timeout' => '24',
            'verify_peer' => '1',
            'sender_email' => 'app@example.test',
            'sender_name' => 'Test app',
            'application_base_url' => 'https://example.test/hfc/',
            'notifications_enabled' => '1',
            'max_attempts' => '7',
            'retry_delay_seconds' => '90',
        ]);

        $stored = require $this->appRoot . '/config/email.php';
        $this->assertIsArray($stored);
        $this->assertSame('smtp.example.test', $stored['smtp']['host'] ?? null);
        $this->assertSame('mailer', $stored['smtp']['username'] ?? null);
        $this->assertSame('secret-password', $stored['smtp']['password'] ?? null);
        $this->assertSame('https://example.test/hfc', $stored['application_base_url'] ?? null);

        $reloaded = $this->emailConfig($this->appRoot);
        $form = (new EmailSettingsService($reloaded))->settingsForForm();
        $this->assertArrayNotHasKey('password', $form);
        $this->assertTrue((bool)($form['password_is_set'] ?? false));

        (new EmailSettingsService($reloaded))->saveFromForm([
            'enabled' => '1',
            'host' => 'smtp2.example.test',
            'port' => '465',
            'encryption' => 'tls',
            'username' => 'mailer',
            'password' => '',
            'sender_email' => 'app@example.test',
        ]);
        $updated = require $this->appRoot . '/config/email.php';
        $this->assertIsArray($updated);
        $this->assertSame('smtp2.example.test', $updated['smtp']['host'] ?? null);
        $this->assertSame('secret-password', $updated['smtp']['password'] ?? null);
    }

    /**
     * HR: Gradi Config instancu čiji root pokazuje na izolirani testni direktorij.
     * EN: Builds a Config instance whose root points to an isolated test directory.
     */
    private function emailConfig(string $appRoot): EmailConfig
    {
        $config = new class (new Helper(), [], $appRoot) extends Config {
            /**
             * HR: Prima testni root uz standardne Config ovisnosti.
             * EN: Receives the test root alongside the standard Config dependencies.
             *
             * @param array<string, mixed> $data
             */
            public function __construct(
                Helper $helper,
                array $data,
                private readonly string $appRoot,
            ) {
                parent::__construct($helper, $data);
            }

            /**
             * HR: Vraća izolirani root umjesto stvarne aplikacije.
             * EN: Returns the isolated root instead of the real application.
             */
            public function getAppRootDir(): string
            {
                return $this->appRoot;
            }
        };

        return new EmailConfig($config, dirname(__DIR__));
    }

    /**
     * HR: Rekurzivno uklanja isključivo direktorij koji je test sam kreirao.
     * EN: Recursively removes only the directory created by the test itself.
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
