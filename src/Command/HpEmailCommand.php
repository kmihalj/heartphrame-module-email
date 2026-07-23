<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Command;

use AaiEduHr\HeartPhrameModuleEmail\Service\EmailOutboxWorker;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailService;
use HeartPhrame\Config\ConfigInterface;
use InvalidArgumentException;
use RuntimeException;

use function array_slice;
use function array_values;
use function date;
use function is_dir;
use function is_file;
use function is_numeric;
use function is_scalar;
use function json_encode;
use function max;
use function mkdir;
use function preg_replace;
use function rtrim;
use function sleep;
use function str_starts_with;
use function strtolower;
use function trim;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * HR: Pruža instalaciju migracije, outbox worker i statusne CLI naredbe.
 * EN: Provides migration installation, outbox worker, and status CLI commands.
 */
final readonly class HpEmailCommand
{
    private const DEFAULT_MIGRATIONS_PATH = 'database/migrations';

    private const TEMPLATE_FILE = 'resources/migrations/initial_email_schema.php';

    /**
     * HR: Prima konfiguraciju hosta, queue servis i worker.
     * EN: Receives host configuration, queue service, and worker.
     */
    public function __construct(
        private ConfigInterface $config,
        private EmailService $email,
        private EmailOutboxWorker $worker,
    ) {
    }

    /**
     * HR: Usmjerava `email` podnaredbe.
     * EN: Routes `email` subcommands.
     *
     * @param array<int, string> $arguments
     * @param array<string, mixed> $options
     */
    public function run(array $arguments = [], array $options = []): int
    {
        $subcommand = strtolower(trim((string)($arguments[0] ?? 'help')));
        $subArguments = array_values(array_slice($arguments, 1));

        return match ($subcommand) {
            'install', 'migration:install', 'install-migration', 'scaffold' =>
            $this->installMigration($subArguments, $options),
            'worker', 'outbox:worker' => $this->outboxWorker($subArguments, $options),
            'status', 'outbox:status' => $this->status(),
            'help', '--help', '-h' => $this->help(),
            default => $this->unknownSubcommand($subcommand),
        };
    }

    /**
     * HR: Kopira početnu E-mail migraciju u host aplikaciju.
     * EN: Copies the initial E-mail migration into the host application.
     *
     * @param array<int, string> $arguments
     * @param array<string, mixed> $options
     */
    public function installMigration(array $arguments = [], array $options = []): int
    {
        $directory = $this->targetDirectory($options);
        $template = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . self::TEMPLATE_FILE;
        if (!is_file($template)) {
            throw new RuntimeException(__('Predložak E-mail migracije nije pronađen.'));
        }

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(__('Nije moguće kreirati direktorij migracija.'));
        }

        $target = rtrim($directory, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . date('YmdHis')
        . '_'
        . $this->migrationSuffix($arguments, $options)
        . '.php';
        $content = file_get_contents($template);
        if (!is_string($content) || $content === '' || file_put_contents($target, $content) === false) {
            throw new RuntimeException(__('Nije moguće kopirati E-mail migraciju.'));
        }

        $this->write(__('Kreirana je početna E-mail migracija: ') . $target);
        $this->write(__('Sljedeći korak: pokreni `vendor/bin/hph orm-migrate up`.'));

        return 0;
    }

    /**
     * HR: Obrađuje red do pražnjenja ili stalno čeka uz `--watch`.
     * EN: Processes the queue until empty or keeps waiting with `--watch`.
     *
     * @param array<int, string> $arguments
     * @param array<string, mixed> $options
     */
    public function outboxWorker(array $arguments = [], array $options = []): int
    {
        unset($arguments);
        $batchSize = $this->integerOption($options, ['batch-size', 'b'], 20);
        $sleepSeconds = $this->integerOption($options, ['sleep', 's'], 2);
        $watch = $this->boolOption($options, ['watch', 'w']);

        do {
            $summary = $this->worker->workBatch($batchSize);
            if ($summary['processed'] > 0) {
                $this->write(sprintf(
                    __('Obrađeno: %d, poslano: %d, neuspjelo: %d.'),
                    $summary['processed'],
                    $summary['sent'],
                    $summary['failed'],
                ));
            }

            if (!$watch && $summary['processed'] === 0) {
                return 0;
            }

            if ($watch && $summary['processed'] === 0) {
                sleep($sleepSeconds);
            }
        } while ($watch || $summary['processed'] > 0);

        return 0;
    }

    /**
     * HR: Ispisuje JSON brojeve outbox statusa.
     * EN: Prints JSON outbox status counts.
     */
    public function status(): int
    {
        $json = json_encode(
            $this->email->statusCounts(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        $this->write(is_string($json) ? $json : '{}');

        return 0;
    }

    /**
     * HR: Ispisuje primjere instalacije i workera.
     * EN: Prints installation and worker examples.
     */
    public function help(): int
    {
        $this->write('hph email <install|outbox:worker|outbox:status|help>');
        $this->write('  vendor/bin/hph email install');
        $this->write('  vendor/bin/hph email outbox:worker --batch-size=20');
        $this->write('  vendor/bin/hph email outbox:worker --watch --sleep=2');
        $this->write('  vendor/bin/hph email outbox:status');

        return 0;
    }

    /**
     * HR: Vraća grešku za nepoznatu podnaredbu.
     * EN: Returns an error for an unknown subcommand.
     */
    private function unknownSubcommand(string $subcommand): int
    {
        $this->write(sprintf(__('Nepoznata E-mail podnaredba: %s'), $subcommand));

        return 1;
    }

    /**
     * HR: Razrješava direktorij host migracija.
     * EN: Resolves the host migration directory.
     *
     * @param array<string, mixed> $options
     */
    private function targetDirectory(array $options): string
    {
        $path = $this->option($options, ['path', 'p']);
        if ($path === null) {
            return rtrim($this->config->getAppRootDir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . self::DEFAULT_MIGRATIONS_PATH;
        }

        return str_starts_with($path, DIRECTORY_SEPARATOR)
        ? rtrim($path, DIRECTORY_SEPARATOR)
        : rtrim($this->config->getAppRootDir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * HR: Normalizira naziv generirane migracije.
     * EN: Normalizes the generated migration name.
     *
     * @param array<int, string> $arguments
     * @param array<string, mixed> $options
     */
    private function migrationSuffix(array $arguments, array $options): string
    {
        $name = $this->option($options, ['name']) ?? trim((string)($arguments[0] ?? ''));
        $name = $name !== '' ? $name : 'install_email_module_schema';
        $name = trim((string)preg_replace('/[^a-z0-9_]+/i', '_', strtolower($name)), '_');
        if ($name === '') {
            throw new InvalidArgumentException(__('Naziv migracije ne smije biti prazan.'));
        }

        return $name;
    }

    /**
     * HR: Čita pozitivnu cjelobrojnu CLI opciju.
     * EN: Reads a positive integer CLI option.
     *
     * @param array<string, mixed> $options
     * @param list<string> $keys
     */
    private function integerOption(array $options, array $keys, int $default): int
    {
        $value = $this->option($options, $keys);

        return $value !== null && is_numeric($value) ? max(1, (int)$value) : $default;
    }

    /**
     * HR: Čita boolean CLI zastavicu.
     * EN: Reads a boolean CLI flag.
     *
     * @param array<string, mixed> $options
     * @param list<string> $keys
     */
    private function boolOption(array $options, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $options)) {
                continue;
            }

            $value = $options[$key];
            if ($value === true) {
                return true;
            }

            return is_scalar($value)
            && in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * HR: Čita prvu nepraznu skalarnu CLI opciju.
     * EN: Reads the first non-empty scalar CLI option.
     *
     * @param array<string, mixed> $options
     * @param list<string> $keys
     */
    private function option(array $options, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $options[$key] ?? null;
            if (is_scalar($value) && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }

        return null;
    }

    /**
     * HR: Ispisuje jednu CLI poruku.
     * EN: Prints one CLI message.
     */
    private function write(string $message): void
    {
        echo $message . PHP_EOL;
    }
}
