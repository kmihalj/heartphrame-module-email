<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Service;

use Psr\Container\ContainerInterface;
use Throwable;

use function array_key_exists;
use function array_replace;
use function is_array;
use function is_file;
use function is_object;
use function is_string;
use function method_exists;

/**
 * HR: Opcionalno dodaje SMTP postavke u zajednički module-menu settings izbornik.
 * EN: Optionally adds SMTP settings to the shared module-menu settings navigation.
 */
final readonly class EmailMenuIntegration
{
    private const MENU_REPOSITORY = 'AaiEduHr\\HeartPhrameModuleMenu\\Service\\MenuConfigRepository';

    /**
     * HR: Prima container i konfiguraciju bez čvrste ovisnosti o Menu modulu.
     * EN: Receives the container and configuration without a hard Menu-module dependency.
     */
    public function __construct(
        private ContainerInterface $container,
        private EmailConfig $config,
    ) {
    }

    /**
     * HR: Registrira jednu settings stavku kada je Menu modul dostupan.
     * EN: Registers one settings item when the Menu module is available.
     */
    public function registerSettingsMenuItem(): void
    {
        if (!$this->config->shouldAutoRegisterSettingsMenu() || !class_exists(self::MENU_REPOSITORY)) {
            return;
        }

        try {
            $repository = $this->container->get(self::MENU_REPOSITORY);
            if (!is_object($repository) || !method_exists($repository, 'jsonPathForSection')) {
                return;
            }

            $path = $repository->jsonPathForSection('settings');
            if (!is_string($path) || $path === '') {
                return;
            }

            $originalItems = $this->read($path);
            $items = $this->settingsTreeWithEmailItem($originalItems);
            if ($items !== $originalItems) {
                $this->write($path, $items);
            }
        } catch (Throwable) {
            // HR: Menu integracija je opcionalna i ne smije prekinuti bootstrap.
            // EN: Menu integration is optional and must not interrupt bootstrap.
        }
    }

    /**
     * HR: Čita postojeće menu stablo.
     * EN: Reads the existing menu tree.
     *
     * @return list<array<string, mixed>>
     */
    private function read(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $json = file_get_contents($path);
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return $this->rows($decoded);
    }

    /**
     * HR: Dodaje E-mail grupu kada nedostaje, a postojeću osvježava na mjestu
     *     kako bi redoslijed podešen u Menu modulu ostao sačuvan.
     * EN: Adds the E-mail group when missing and refreshes an existing group in
     *     place so ordering configured in the Menu module remains preserved.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function settingsTreeWithEmailItem(array $items): array
    {
        foreach ($items as &$item) {
            $id = is_string($item['id'] ?? null) ? $item['id'] : '';
            if ($id !== 'email.settings.group') {
                continue;
            }

            $item = $this->mergeManagedItem($item, $this->emailGroupDefinition());
            $children = $this->rows($item['children'] ?? null);
            $hasSettingsItem = false;

            foreach ($children as &$child) {
                $childId = is_string($child['id'] ?? null) ? $child['id'] : '';
                if ($childId !== 'email.settings') {
                    continue;
                }

                $child = $this->mergeManagedItem($child, $this->emailSettingsDefinition());
                $hasSettingsItem = true;
                break;
            }

            unset($child);

            if (!$hasSettingsItem) {
                $children = $this->withoutEmailItems($children);
                $children[] = $this->emailSettingsDefinition();
            }

            $item['children'] = $children;
            unset($item);

            return $items;
        }

        unset($item);

        $items = $this->withoutEmailItems($items);
        $group = $this->emailGroupDefinition();
        $group['children'] = [$this->emailSettingsDefinition()];
        $items[] = $group;

        return $items;
    }

    /**
     * HR: Spaja modulom upravljana polja, ali čuva korisnički redoslijed.
     * EN: Merges module-managed fields while preserving user-defined ordering.
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function mergeManagedItem(array $existing, array $definition): array
    {
        $merged = array_replace($existing, $definition);
        if (array_key_exists('order', $existing)) {
            $merged['order'] = $existing['order'];
        }

        return $merged;
    }

    /**
     * HR: Vraća zadanu korijensku definiciju E-mail postavki.
     * EN: Returns the default root definition for E-mail settings.
     *
     * @return array<string, mixed>
     */
    private function emailGroupDefinition(): array
    {
        return [
            'id' => 'email.settings.group',
            'parent_id' => '',
            'label' => ['hr' => 'E-mail', 'en' => 'E-mail'],
            'route' => '',
            'url' => '',
            'query' => '',
            'order' => 70,
            'enabled' => true,
            'level' => 0,
        ];
    }

    /**
     * HR: Vraća zadanu definiciju poveznice na SMTP postavke.
     * EN: Returns the default definition for the SMTP settings link.
     *
     * @return array<string, mixed>
     */
    private function emailSettingsDefinition(): array
    {
        return [
            'id' => 'email.settings',
            'parent_id' => 'email.settings.group',
            'label' => ['hr' => 'SMTP postavke', 'en' => 'SMTP settings'],
            'route' => 'email.settings',
            'url' => '',
            'query' => '',
            'order' => 10,
            'enabled' => true,
            'level' => 1,
        ];
    }

    /**
     * HR: Uklanja stare E-mail stavke na bilo kojoj dubini prije popravka
     *     nevaljanog stabla ili prvog dodavanja grupe.
     * EN: Removes stale E-mail entries at any depth before repairing an invalid
     *     tree or adding the group for the first time.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function withoutEmailItems(array $items): array
    {
        $filtered = [];
        foreach ($items as $item) {
            $id = is_string($item['id'] ?? null) ? $item['id'] : '';
            if ($id === 'email.settings') {
                continue;
            }

            if ($id === 'email.settings.group') {
                continue;
            }

            $children = $this->rows($item['children'] ?? null);
            if (isset($item['children'])) {
                $item['children'] = $this->withoutEmailItems($children);
            }

            $filtered[] = $item;
        }

        return $filtered;
    }

    /**
     * HR: Atomarno zapisuje menu JSON.
     * EN: Atomically writes the menu JSON.
     *
     * @param list<array<string, mixed>> $items
     */
    private function write(string $path, array $items): void
    {
        $json = json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return;
        }

        $temporary = $path . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $json . PHP_EOL) !== false) {
            rename($temporary, $path);
        }
    }

    /**
     * HR: Normalizira miješanu vrijednost u listu redaka.
     * EN: Normalizes a mixed value into a row list.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            $normalized = $this->row($row);
            if ($normalized !== null) {
                $rows[] = $normalized;
            }
        }

        return $rows;
    }

    /**
     * HR: Normalizira jedan dekodirani JSON redak u mapu sa string ključevima.
     * EN: Normalizes one decoded JSON row into a string-keyed map.
     *
     * @return array<string, mixed>|null
     */
    private function row(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $row = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $row[$key] = $item;
            }
        }

        return $row;
    }
}
