<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Tests;

use AaiEduHr\HeartPhrameModuleAuth\Service\AuthUserService;
use AaiEduHr\HeartPhrameModuleEmail\ModuleEmail;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailConfig;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailMessage;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailService;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleOrm\Database\Schema\Blueprint;
use HeartPhrame\Config\Config;
use HeartPhrame\Helper\Helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_exists;
use function file_put_contents;
use function is_array;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function unlink;
use function var_export;

#[CoversClass(EmailService::class)]
#[UsesClass(EmailConfig::class)]
#[UsesClass(EmailMessage::class)]
final class EmailServiceTest extends TestCase
{
    private string $appRoot = '';

    /**
     * HR: Uklanja privremenu konfiguraciju reda nakon testa.
     * EN: Removes the temporary queue configuration after the test.
     */
    protected function tearDown(): void
    {
        if ($this->appRoot !== '') {
            $this->removeDirectory($this->appRoot);
        }
    }

    /**
     * HR: Provjerava prijenosnu migraciju, deduplikaciju, korisničku adresu,
     *     javni link i brojanje statusa bez otvaranja SMTP veze.
     * EN: Verifies the portable migration, deduplication, user address, public
     *     link, and status counts without opening an SMTP connection.
     */
    public function testOutboxQueuesPortableDeduplicatedMessages(): void
    {
        [$database, $config] = $this->databaseAndConfig();
        $service = new EmailService($database, $config, new AuthUserService($database));

        $first = $service->queue(
            'recipient@example.test',
            'Primatelj',
            'Naslov',
            'Tekst',
            '<p>Tekst</p>',
            'same-message',
        );
        $second = $service->queue(
            'recipient@example.test',
            'Primatelj',
            'Drugi naslov',
            'Drugi tekst',
            null,
            'same-message',
        );
        $this->assertIsArray($first);
        $this->assertIsArray($second);
        $this->assertSame($first['id'] ?? null, $second['id'] ?? null);

        $forUser = $service->queueForUser(
            7,
            'Objavljeno',
            'Stranica je objavljena.',
            null,
            'workspace:7',
            '/workspace/demo/page',
        );
        $this->assertIsArray($forUser);
        $this->assertSame('editor@example.test', $forUser['recipient_email'] ?? null);
        $this->assertStringContainsString(
            'https://example.test/hfc/workspace/demo/page',
            (string)($forUser['body_text'] ?? ''),
        );
        $this->assertSame(
            ['pending' => 2, 'sending' => 0, 'sent' => 0, 'failed' => 0],
            $service->statusCounts(),
        );
    }

    /**
     * HR: Priprema SQLite, minimalnu Auth tablicu, inicijalni outbox i
     *     uključenu testnu konfiguraciju.
     * EN: Prepares SQLite, a minimal Auth table, the initial outbox, and an
     *     enabled test configuration.
     *
     * @return array{Database, EmailConfig}
     */
    private function databaseAndConfig(): array
    {
        $helper = new Helper();
        $databaseConfig = new Config($helper, [
            'database' => [
                'connections' => [
                    'default' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                    ],
                ],
            ],
        ]);
        $database = new Database($databaseConfig, $helper);
        $database->schema()->create('auth_users', static function (Blueprint $table): void {
            $table->id();
            $table->string('login_identifier', 255);
            $table->boolean('is_active')->default(true);
        });
        $database->schema()->create(
            'auth_user_attribute_values',
            static function (Blueprint $table): void {
                $table->id();
                $table->bigInteger('user_id')->unsigned();
                $table->string('field_key', 190);
                $table->text('value_text')->nullable();
            },
        );
        $database->table('auth_users')->insert([
            'id' => 7,
            'login_identifier' => 'editor',
            'is_active' => true,
        ]);
        $database->table('auth_user_attribute_values')->insert([
            'user_id' => 7,
            'field_key' => 'display_name',
            'value_text' => 'Editor',
        ]);
        $database->table('auth_user_attribute_values')->insert([
            'user_id' => 7,
            'field_key' => 'email',
            'value_text' => 'editor@example.test',
        ]);

        $migration = require dirname(__DIR__) . '/resources/migrations/initial_email_schema.php';
        $this->assertInstanceOf(ReversibleMigrationInterface::class, $migration);
        $migration->up($database);
        $this->assertTrue($database->schema()->hasTable(ModuleEmail::TABLE_OUTBOX));

        $this->appRoot = sys_get_temp_dir() . '/hph-email-service-' . bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($this->appRoot . '/config', 0777, true));
        $settings = [
            'enabled' => true,
            'smtp' => [
                'host' => 'smtp.example.test',
                'port' => 587,
                'encryption' => 'starttls',
                'username' => 'mailer',
                'password' => 'secret',
            ],
            'sender' => [
                'email' => 'app@example.test',
                'name' => 'App',
            ],
            'application_base_url' => 'https://example.test/hfc',
            'notifications_enabled' => true,
        ];
        file_put_contents(
            $this->appRoot . '/config/email.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($settings, true) . ";\n",
        );
        $appConfig = new class ($helper, [], $this->appRoot) extends Config {
            /**
             * HR: Prima izolirani root aplikacije.
             * EN: Receives an isolated application root.
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
             * HR: Vraća izolirani testni root.
             * EN: Returns the isolated test root.
             */
            public function getAppRootDir(): string
            {
                return $this->appRoot;
            }
        };

        return [$database, new EmailConfig($appConfig, dirname(__DIR__))];
    }

    /**
     * HR: Rekurzivno briše samo testni direktorij.
     * EN: Recursively deletes only the test directory.
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
