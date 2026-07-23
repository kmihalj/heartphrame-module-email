<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Service;

use AaiEduHr\HeartPhrameModuleEmail\ModuleEmail;
use HeartPhrame\Config\ConfigInterface;
use HeartPhrame\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;

/**
 * HR: Renderira E-mail prikaze uz host override podršku.
 * EN: Renders E-mail views with host override support.
 */
final readonly class EmailModuleViewRenderer
{
    /**
     * HR: Prima framework response factory i konfiguraciju view direktorija.
     * EN: Receives the framework response factory and view-directory configuration.
     */
    public function __construct(
        private ResponseFactory $responseFactory,
        private ConfigInterface $config,
    ) {
    }

    /**
     * HR: Renderira puni prikaz iz overridea ili iz modula.
     * EN: Renders a full view from an override or from the module.
     *
     * @param array<string, mixed> $data
     */
    public function render(
        string $view,
        array $data = [],
        null|true|string $layout = true,
        int $status = 200,
    ): ResponseInterface {
        $override = $this->findOverrideView($view);
        if ($override !== null) {
            return $this->responseFactory->view($override, $data, $layout, $status);
        }

        return $this->responseFactory->viewForModule(
            ModuleEmail::PACKAGE_NAME,
            $view,
            $data,
            $layout,
            $status,
        );
    }

    /**
     * HR: Traži kratku i punu aplikacijsku override putanju.
     * EN: Searches the short and fully qualified application override paths.
     */
    private function findOverrideView(string $view): ?string
    {
        $root = rtrim($this->config->getAsString('app.views.path') ?? '', '/');
        if ($root === '') {
            return null;
        }

        foreach (
            [
                'modules/heartphrame-module-email/' . $view,
                'modules/aaieduhr/heartphrame-module-email/' . $view,
            ] as $candidate
        ) {
            if (is_file($root . '/' . $candidate . '.php')) {
                return $candidate;
            }
        }

        return null;
    }
}
