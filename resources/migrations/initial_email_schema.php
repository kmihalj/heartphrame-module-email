<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleEmail\ModuleEmail;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleOrm\Database\Schema\Blueprint;

return new class implements ReversibleMigrationInterface {
    /**
     * HR: Kreira prenosivi trajni outbox. U njemu nema SMTP specifičnih tipova
     *     niti binarnih privitaka pa jednako radi na podržanim bazama.
     * EN: Creates the portable persistent outbox. It contains no SMTP-specific
     *     types or binary attachments and works across supported databases.
     */
    public function up(Database $db): void
    {
        $schema = $db->schema();
        if ($schema->hasTable(ModuleEmail::TABLE_OUTBOX)) {
            return;
        }

        $schema->create(ModuleEmail::TABLE_OUTBOX, static function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('dedup_key', 190)->nullable()->unique();
            $table->bigInteger('user_id')->unsigned()->nullable()->index();
            $table->string('recipient_email', 255)->index();
            $table->string('recipient_name', 255)->nullable();
            $table->string('subject', 255);
            $table->longText('body_text');
            $table->longText('body_html')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->integer('attempts')->unsigned()->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('locked_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->longText('last_error')->nullable();
            $table->timestamps();

            $table->index(
                ['status', 'available_at', 'id'],
                'email_outbox_worker_idx',
            );
        });
    }

    /**
     * HR: Uklanja samo outbox tablicu E-mail modula.
     * EN: Removes only the E-mail module outbox table.
     */
    public function down(Database $db): void
    {
        $db->schema()->dropIfExists(ModuleEmail::TABLE_OUTBOX);
    }
};
