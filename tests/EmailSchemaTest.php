<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Tests;

use AaiEduHr\HeartPhrameModuleEmail\ModuleEmail;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use HeartPhrame\Config\Config;
use HeartPhrame\Helper\Helper;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class EmailSchemaTest extends TestCase
{
    /**
     * HR: Dokazuje da jedina početna migracija stvara cijeli outbox bez testnih podataka.
     * EN: Proves that the single initial migration creates the full outbox without sample data.
     */
    public function testInitialMigrationCreatesEmptyPortableOutbox(): void
    {
        $helper = new Helper();
        $config = new Config($helper, [
            'database' => [
                'connections' => [
                    'default' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                    ],
                ],
            ],
        ]);
        $database = new Database($config, $helper);
        $migration = require dirname(__DIR__) . '/resources/migrations/initial_email_schema.php';
        $this->assertInstanceOf(ReversibleMigrationInterface::class, $migration);

        $migration->up($database);

        $this->assertTrue($database->schema()->hasColumns(ModuleEmail::TABLE_OUTBOX, [
            'uuid',
            'recipient_email',
            'subject',
            'body_text',
            'status',
            'attempts',
            'available_at',
            'locked_at',
            'sent_at',
            'last_error',
        ]));
        $this->assertSame([], $database->table(ModuleEmail::TABLE_OUTBOX)->get());
    }
}
