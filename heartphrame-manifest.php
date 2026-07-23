<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleAuth\Middleware\RequireAdminOrBootstrapMiddleware;
use AaiEduHr\HeartPhrameModuleAuth\ModuleAuth;
use AaiEduHr\HeartPhrameModuleEmail\Controller\EmailSettingsController;
use AaiEduHr\HeartPhrameModuleEmail\ModuleEmail;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailMenuIntegration;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use HeartPhrame\Bridge\ComposerBridge;
use HeartPhrame\Command\CommandDefinition;
use HeartPhrame\Config\ConfigInterface;
use Psr\Container\ContainerInterface;

return new class extends \HeartPhrame\Module\AbstractModuleManifest {
    private const AUTH_MODULE_PACKAGE = 'aaieduhr/heartphrame-module-auth';

    private const ORM_MODULE_PACKAGE = 'aaieduhr/heartphrame-module-orm';

    /**
     * HR: Provjerava instalaciju i redoslijed Auth/ORM temelja.
     * EN: Verifies installation and ordering of the Auth/ORM foundation.
     */
    public function canLoad(ContainerInterface $container): bool
    {
        $composer = $container->get(ComposerBridge::class);
        if (!($composer instanceof ComposerBridge)) {
            throw new RuntimeException('E-mail module requires ComposerBridge.');
        }

        if (!$composer->isInstalled(self::AUTH_MODULE_PACKAGE) || !class_exists(ModuleAuth::class)) {
            throw new RuntimeException('E-mail module requires the installed Auth module.');
        }

        if (!$composer->isInstalled(self::ORM_MODULE_PACKAGE) || !class_exists(Database::class)) {
            throw new RuntimeException('E-mail module requires the installed ORM module.');
        }

        $config = $container->get(ConfigInterface::class);
        if (!($config instanceof ConfigInterface)) {
            throw new RuntimeException('E-mail module requires ConfigInterface.');
        }

        $enabled = $config->getAsArrayWithValuesAsNonEmptyStrings('app.modules.enabled') ?? [];
        foreach ([self::AUTH_MODULE_PACKAGE, self::ORM_MODULE_PACKAGE] as $required) {
            if (!in_array($required, $enabled, true)) {
                throw new RuntimeException(
                    'E-mail module requires enabled module "' . $required . '" before "'
                    . ModuleEmail::PACKAGE_NAME . '".',
                );
            }
        }

        return true;
    }

    /**
     * HR: Odgađa učitavanje do registracije obaveznih modula.
     * EN: Defers loading until required modules have been registered.
     */
    public function requiresDeferredLoading(): bool
    {
        return true;
    }

    /**
     * HR: Učitava servisne definicije.
     * EN: Loads service definitions.
     */
    public function getServices(): array
    {
        $services = require __DIR__ . '/config/services.php';
        if (!is_array($services)) {
            throw new RuntimeException('E-mail config/services.php must return an array.');
        }

        return $services;
    }

    /**
     * HR: Registrira administratorske SMTP postavke i test.
     * EN: Registers administrator SMTP settings and test action.
     */
    public function getBaseRoutes(): array
    {
        $admin = [RequireAdminOrBootstrapMiddleware::class];

        return [
            ['GET', '/settings/email', EmailSettingsController::class . '@index', 'email.settings', $admin],
            [
                'POST',
                '/settings/email',
                EmailSettingsController::class . '@save',
                'email.settings.save',
                $admin,
            ],
            [
                'POST',
                '/settings/email/test',
                EmailSettingsController::class . '@test',
                'email.settings.test',
                $admin,
            ],
        ];
    }

    /**
     * HR: Registrira install, worker i status CLI naredbe.
     * EN: Registers install, worker, and status CLI commands.
     */
    public function getCommands(): array
    {
        return [
            new CommandDefinition(
                'email',
                'Pure-PHP SMTP and outbox worker commands.',
                [\AaiEduHr\HeartPhrameModuleEmail\Command\HpEmailCommand::class, 'run'],
            ),
            new CommandDefinition(
                'email:install-migration',
                'Copy initial E-mail migration into the host application.',
                [\AaiEduHr\HeartPhrameModuleEmail\Command\HpEmailCommand::class, 'installMigration'],
            ),
        ];
    }

    /**
     * HR: Nakon bootstrapa opcionalno dodaje settings menu stavku.
     * EN: Optionally adds the settings menu item after bootstrap.
     *
     * @return mixed[]
     */
    public function getBootstrapCallables(): array
    {
        return [
            static function (ContainerInterface $container): void {
                $integration = $container->get(EmailMenuIntegration::class);
                if ($integration instanceof EmailMenuIntegration) {
                    $integration->registerSettingsMenuItem();
                }
            },
        ];
    }

    /**
     * HR: Vraća direktorij viewova modula.
     * EN: Returns the module view directory.
     */
    public function getViewsPath(): string
    {
        return __DIR__ . '/views';
    }
};
