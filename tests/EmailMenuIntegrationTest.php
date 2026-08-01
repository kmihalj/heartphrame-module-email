<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Tests;

use AaiEduHr\HeartPhrameModuleEmail\Service\EmailMenuIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(EmailMenuIntegration::class)]
final class EmailMenuIntegrationTest extends TestCase
{
    /**
     * HR: Automatska registracija ne smije pomaknuti postojeću E-mail grupu
     *     koju je administrator rasporedio u Menu modulu.
     * EN: Automatic registration must not move an existing E-mail group that
     *     the administrator arranged in the Menu module.
     */
    public function testExistingEmailGroupKeepsConfiguredOrderAndPosition(): void
    {
        $integration = (new ReflectionClass(EmailMenuIntegration::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass(EmailMenuIntegration::class))->getMethod('settingsTreeWithEmailItem');
        $items = [
            [
                'id' => 'auth',
                'order' => 10,
            ],
            [
                'id' => 'auth.sso',
                'order' => 20,
            ],
            [
                'id' => 'email.settings.group',
                'label' => ['hr' => 'Stara labela'],
                'order' => 30,
                'children' => [[
                    'id' => 'email.settings',
                    'label' => ['hr' => 'Stari SMTP'],
                    'order' => 10,
                ]],
            ],
            [
                'id' => 'menu',
                'order' => 40,
            ],
        ];

        $updated = $method->invoke($integration, $items);

        $this->assertIsArray($updated);
        $this->assertSame(['auth', 'auth.sso', 'email.settings.group', 'menu'], array_column($updated, 'id'));
        $this->assertSame(30, $updated[2]['order'] ?? null);
        $this->assertSame('E-mail', $updated[2]['label']['hr'] ?? null);
        $this->assertSame(10, $updated[2]['children'][0]['order'] ?? null);
        $this->assertSame('email.settings', $updated[2]['children'][0]['id'] ?? null);
    }
}
