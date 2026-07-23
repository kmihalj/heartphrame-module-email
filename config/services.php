<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleAuth\Service\AuthUserService;
use AaiEduHr\HeartPhrameModuleEmail\Controller\EmailSettingsController;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailConfig;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailMenuIntegration;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailModuleViewRenderer;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailOutboxWorker;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailService;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailSettingsService;
use AaiEduHr\HeartPhrameModuleEmail\Service\SmtpClient;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use HeartPhrame\Alert\AlertHandler;
use HeartPhrame\Authn\AuthnHandlerInterface;
use HeartPhrame\Config\ConfigInterface;
use HeartPhrame\Http\ResponseFactory;
use HeartPhrame\Routing\UrlGenerator;
use Psr\Container\ContainerInterface;

return [
    EmailConfig::class => static fn(ContainerInterface $container): EmailConfig =>
        new EmailConfig($container->get(ConfigInterface::class), dirname(__DIR__)),

    EmailSettingsService::class => static fn(ContainerInterface $container): EmailSettingsService =>
        new EmailSettingsService($container->get(EmailConfig::class)),

    SmtpClient::class => static fn(): SmtpClient => new SmtpClient(),

    EmailService::class => static fn(ContainerInterface $container): EmailService =>
        new EmailService(
            $container->get(Database::class),
            $container->get(EmailConfig::class),
            $container->get(AuthUserService::class),
        ),

    EmailOutboxWorker::class => static fn(ContainerInterface $container): EmailOutboxWorker =>
        new EmailOutboxWorker(
            $container->get(Database::class),
            $container->get(EmailConfig::class),
            $container->get(SmtpClient::class),
        ),

    EmailMenuIntegration::class => static fn(ContainerInterface $container): EmailMenuIntegration =>
        new EmailMenuIntegration($container, $container->get(EmailConfig::class)),

    EmailModuleViewRenderer::class => static fn(ContainerInterface $container): EmailModuleViewRenderer =>
        new EmailModuleViewRenderer(
            $container->get(ResponseFactory::class),
            $container->get(ConfigInterface::class),
        ),

    EmailSettingsController::class => static fn(ContainerInterface $container): EmailSettingsController =>
        new EmailSettingsController(
            $container->get(ResponseFactory::class),
            $container->get(EmailModuleViewRenderer::class),
            $container->get(EmailSettingsService::class),
            $container->get(EmailService::class),
            $container->get(EmailOutboxWorker::class),
            $container->get(AuthnHandlerInterface::class),
            $container->get(UrlGenerator::class),
            $container->get(AlertHandler::class),
        ),
];
