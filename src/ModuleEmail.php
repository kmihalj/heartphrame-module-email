<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail;

/**
 * HR: Sadrži stabilne identifikatore E-mail modula.
 * EN: Contains stable E-mail module identifiers.
 */
final class ModuleEmail
{
    public const PACKAGE_NAME = 'aaieduhr/heartphrame-module-email';

    public const TABLE_OUTBOX = 'email_outbox';

    /**
     * HR: Sprječava instanciranje registra konstanti.
     * EN: Prevents instantiation of the constants registry.
     */
    private function __construct()
    {
    }
}
