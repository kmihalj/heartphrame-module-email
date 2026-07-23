<?php

declare(strict_types=1);

/**
 * @var \HeartPhrame\View\View $this
 * @var string $title
 * @var array{
 *     enabled: bool,
 *     host: string,
 *     port: int,
 *     encryption: string,
 *     username: string,
 *     password_is_set: bool,
 *     connect_timeout: int,
 *     io_timeout: int,
 *     verify_peer: bool,
 *     allow_self_signed: bool,
 *     sender_email: string,
 *     sender_name: string,
 *     application_base_url: string,
 *     notifications_enabled: bool,
 *     max_attempts: int,
 *     retry_delay_seconds: int,
 *     settings_file_path: string
 * } $settings
 * @var array{pending: int, sending: int, sent: int, failed: int} $statusCounts
 * @var bool $tablesReady
 * @var string $savePath
 * @var string $testPath
 * @var string $settingsPath
 * @var string $settingsMenuActiveSection
 * @var string $testRecipient
 * @var object|null $menuRenderer
 */

$workerHint = __(
    'Web zahtjev samo sprema poruku. Pokrenite CLI outbox worker kroz cron ili process manager.',
);
?>
<div class="row g-4">
    <aside class="col-lg-3">
        <?php require __DIR__ . '/sidebar.php'; ?>
    </aside>

    <div class="col-lg-9">
        <form method="post" action="<?= $this->escape($savePath) ?>">
            <section class="card shadow-sm">
                <div class="card-body">
                    <header class="mb-4">
                        <h1 class="h3 mb-1"><?= $this->escape($title) ?></h1>
                        <p class="text-body-secondary mb-0">
                            <?= $this->escape(
                                __('Čisti PHP SMTP transport i trajni outbox za sve module aplikacije.'),
                            ) ?>
                        </p>
                    </header>

                    <?= $this->csrfHandler->generateCsrfTokenInputField() ?>

                    <?php if (!$tablesReady) : ?>
                        <div class="alert alert-warning" role="alert">
                            <strong><?= $this->escape(__('Nedostaje E-mail migracija.')) ?></strong>
                            <div><?= $this->escape(
                                __('Instalirajte početnu migraciju prije uključivanja slanja.'),
                            ) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="form-check form-switch mb-4">
                        <input
                            id="email-enabled"
                            class="form-check-input"
                            type="checkbox"
                            name="enabled"
                            value="1"
                            <?= (bool)($settings['enabled'] ?? false) ? 'checked' : '' ?>
                        >
                        <label class="form-check-label fw-semibold" for="email-enabled">
                            <?= $this->escape(__('E-mail slanje je uključeno')) ?>
                        </label>
                    </div>

                    <h2 class="h5"><?= $this->escape(__('SMTP veza')) ?></h2>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-lg-6">
                            <label class="form-label" for="email-host">
                                <?= $this->escape(__('SMTP host')) ?>
                            </label>
                            <input
                                id="email-host"
                                class="form-control"
                                name="host"
                                value="<?= $this->escape((string)($settings['host'] ?? '')) ?>"
                            >
                        </div>
                        <div class="col-6 col-md-3 col-lg-2">
                            <label class="form-label" for="email-port">
                                <?= $this->escape(__('Port')) ?>
                            </label>
                            <input
                                id="email-port"
                                class="form-control"
                                type="number"
                                min="1"
                                max="65535"
                                name="port"
                                value="<?= (int)($settings['port'] ?? 587) ?>"
                            >
                        </div>
                        <div class="col-6 col-md-5 col-lg-4">
                            <label class="form-label" for="email-encryption">
                                <?= $this->escape(__('Šifriranje')) ?>
                            </label>
                            <select id="email-encryption" class="form-select" name="encryption">
                                <?php foreach (['starttls', 'tls', 'none'] as $encryption) : ?>
                                    <option
                                        value="<?= $encryption ?>"
                                        <?= ($settings['encryption'] ?? '') === $encryption ? 'selected' : '' ?>
                                    >
                                    <?= $this->escape(__($encryption)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="email-username">
                                <?= $this->escape(__('Korisničko ime')) ?>
                            </label>
                            <input
                                id="email-username"
                                class="form-control"
                                name="username"
                                autocomplete="username"
                                value="<?= $this->escape((string)($settings['username'] ?? '')) ?>"
                            >
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="email-password">
                                <?= $this->escape(__('Lozinka')) ?>
                            </label>
                            <input
                                id="email-password"
                                class="form-control"
                                type="password"
                                name="password"
                                autocomplete="new-password"
                                placeholder="<?= $this->escape(
                                    (bool)($settings['password_is_set'] ?? false)
                                        ? __('Ostavite prazno za čuvanje postojeće lozinke')
                                        : '',
                                ) ?>"
                            >
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="email-connect-timeout">
                                <?= $this->escape(__('Timeout veze (s)')) ?>
                            </label>
                            <input
                                id="email-connect-timeout"
                                class="form-control"
                                type="number"
                                min="1"
                                max="120"
                                name="connect_timeout"
                                value="<?= (int)($settings['connect_timeout'] ?? 15) ?>"
                            >
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="email-io-timeout">
                                <?= $this->escape(__('SMTP timeout (s)')) ?>
                            </label>
                            <input
                                id="email-io-timeout"
                                class="form-control"
                                type="number"
                                min="1"
                                max="300"
                                name="io_timeout"
                                value="<?= (int)($settings['io_timeout'] ?? 30) ?>"
                            >
                        </div>
                        <div class="col-12 col-md-6 d-flex flex-column justify-content-end">
                            <div class="form-check form-switch mb-2">
                                <input
                                    id="email-verify-peer"
                                    class="form-check-input"
                                    type="checkbox"
                                    name="verify_peer"
                                    value="1"
                                    <?= (bool)($settings['verify_peer'] ?? true) ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="email-verify-peer">
                                    <?= $this->escape(__('Provjeri TLS certifikat')) ?>
                                </label>
                            </div>
                            <div class="form-check form-switch">
                                <input
                                    id="email-self-signed"
                                    class="form-check-input"
                                    type="checkbox"
                                    name="allow_self_signed"
                                    value="1"
                                    <?= (bool)($settings['allow_self_signed'] ?? false) ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="email-self-signed">
                                    <?= $this->escape(__('Dopusti self-signed certifikat')) ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <h2 class="h5"><?= $this->escape(__('Pošiljatelj i poveznice')) ?></h2>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="email-sender-email">
                                <?= $this->escape(__('Adresa pošiljatelja')) ?>
                            </label>
                            <input
                                id="email-sender-email"
                                class="form-control"
                                type="email"
                                name="sender_email"
                                value="<?= $this->escape((string)($settings['sender_email'] ?? '')) ?>"
                            >
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="email-sender-name">
                                <?= $this->escape(__('Ime pošiljatelja')) ?>
                            </label>
                            <input
                                id="email-sender-name"
                                class="form-control"
                                name="sender_name"
                                value="<?= $this->escape(
                                    (string)($settings['sender_name'] ?? 'HeartPhrame'),
                                ) ?>"
                            >
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="email-base-url">
                                <?= $this->escape(__('Javni URL aplikacije')) ?>
                            </label>
                            <input
                                id="email-base-url"
                                class="form-control"
                                type="url"
                                name="application_base_url"
                                placeholder="https://example.org"
                                value="<?= $this->escape(
                                    (string)($settings['application_base_url'] ?? ''),
                                ) ?>"
                            >
                            <div class="form-text">
                                <?= $this->escape(
                                    __('Koristi se za pretvaranje lokalnih putanja u poveznice unutar poruke.'),
                                ) ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input
                                    id="email-notifications"
                                    class="form-check-input"
                                    type="checkbox"
                                    name="notifications_enabled"
                                    value="1"
                                    <?= (bool)($settings['notifications_enabled'] ?? true) ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="email-notifications">
                                    <?= $this->escape(__('Šalji e-mail kopije in-app obavijesti')) ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <h2 class="h5"><?= $this->escape(__('Outbox worker')) ?></h2>
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="email-attempts">
                                <?= $this->escape(__('Maksimalno pokušaja')) ?>
                            </label>
                            <input
                                id="email-attempts"
                                class="form-control"
                                type="number"
                                min="1"
                                max="100"
                                name="max_attempts"
                                value="<?= (int)($settings['max_attempts'] ?? 5) ?>"
                            >
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="email-retry">
                                <?= $this->escape(__('Osnovna retry odgoda (s)')) ?>
                            </label>
                            <input
                                id="email-retry"
                                class="form-control"
                                type="number"
                                min="1"
                                max="86400"
                                name="retry_delay_seconds"
                                value="<?= (int)($settings['retry_delay_seconds'] ?? 60) ?>"
                            >
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label"><?= $this->escape(__('Stanje reda')) ?></label>
                            <div class="d-flex flex-wrap gap-2 pt-2">
                                <?php foreach (['pending', 'sending', 'sent', 'failed'] as $status) : ?>
                                    <span class="badge text-bg-secondary">
                                    <?= $this->escape(__($status)) ?>:
                                    <?= (int)($statusCounts[$status] ?? 0) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4 mb-0" role="note">
                        <?= $this->escape($workerHint) ?>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <button class="btn btn-primary" type="submit">
                        <?= $this->escape(__('Spremi postavke')) ?>
                    </button>
                </div>
            </section>
        </form>

        <form method="post" action="<?= $this->escape($testPath) ?>" class="mt-4">
            <section class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5"><?= $this->escape(__('Test SMTP-a')) ?></h2>
                    <p class="text-body-secondary">
                        <?= $this->escape(
                            __('Test koristi isti outbox i transport kao stvarne poruke.'),
                        ) ?>
                    </p>
                    <?= $this->csrfHandler->generateCsrfTokenInputField() ?>
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md">
                            <label class="form-label" for="email-test-recipient">
                                <?= $this->escape(__('Adresa primatelja')) ?>
                            </label>
                            <input
                                id="email-test-recipient"
                                class="form-control"
                                type="email"
                                name="recipient_email"
                                required
                                value="<?= $this->escape($testRecipient) ?>"
                            >
                        </div>
                        <div class="col-12 col-md-auto">
                            <button
                                class="btn btn-secondary w-100"
                                type="submit"
                                <?= $tablesReady ? '' : 'disabled' ?>
                            >
                                <?= $this->escape(__('Pošalji test')) ?>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </form>
    </div>
</div>
