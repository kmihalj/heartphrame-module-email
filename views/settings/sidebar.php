<?php

declare(strict_types=1);

/**
 * HR: Koristi zajednički settings meni kada postoji, a inače prikazuje mali fallback.
 * EN: Uses the shared settings menu when available and otherwise shows a small fallback.
 *
 * @var \HeartPhrame\View\View $this
 * @var string $settingsPath
 * @var string $settingsMenuActiveSection
 * @var object|null $menuRenderer
 */

$settingsMenuHtml = null;
if (isset($menuRenderer) && is_object($menuRenderer)) {
    $callback = [$menuRenderer, 'renderSettingsMenu'];
    if (is_callable($callback)) {
        $rendered = $callback($settingsMenuActiveSection);
        $settingsMenuHtml = is_string($rendered) ? $rendered : null;
    }
}
?>
<?php if ($settingsMenuHtml !== null) : ?>
    <?= $settingsMenuHtml ?>
<?php else : ?>
    <nav class="card" aria-label="<?= $this->escape(__('E-mail postavke')) ?>">
        <div class="card-body">
            <h2 class="h5 mb-3"><?= $this->escape(__('Postavke')) ?></h2>
            <div class="list-group list-group-flush">
                <a
                    class="list-group-item list-group-item-action active"
                    href="<?= $this->escape($settingsPath) ?>"
                    aria-current="page"
                >
    <?= $this->escape(__('E-mail postavke')) ?>
                </a>
            </div>
        </div>
    </nav>
<?php endif; ?>
