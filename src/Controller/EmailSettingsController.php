<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Controller;

use AaiEduHr\HeartPhrameModuleEmail\Service\EmailModuleViewRenderer;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailOutboxWorker;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailService;
use AaiEduHr\HeartPhrameModuleEmail\Service\EmailSettingsService;
use HeartPhrame\Alert\Alert;
use HeartPhrame\Alert\AlertHandler;
use HeartPhrame\Authn\AuthnHandlerInterface;
use HeartPhrame\CodeBook\AlertLevelEnum;
use HeartPhrame\Http\ResponseFactory;
use HeartPhrame\Routing\UrlGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function is_array;
use function is_numeric;
use function is_scalar;
use function trim;

/**
 * HR: Prikazuje SMTP postavke, sprema ih i izvodi stvarni test kroz isti outbox.
 * EN: Displays SMTP settings, saves them, and performs a real test through the same outbox.
 */
final readonly class EmailSettingsController
{
    /**
     * HR: Prima view, HTTP, auth, postavke, queue i worker servise.
     * EN: Receives view, HTTP, auth, settings, queue, and worker services.
     */
    public function __construct(
        private ResponseFactory $responseFactory,
        private EmailModuleViewRenderer $viewRenderer,
        private EmailSettingsService $settings,
        private EmailService $email,
        private EmailOutboxWorker $worker,
        private AuthnHandlerInterface $authnHandler,
        private UrlGenerator $urlGenerator,
        private AlertHandler $alertHandler,
    ) {
    }

    /**
     * HR: Prikazuje administratorsku formu i trenutačno stanje outboxa.
     * EN: Displays the administrator form and current outbox status.
     */
    public function index(): ResponseInterface
    {
        $user = $this->currentUser();

        return $this->viewRenderer->render('settings/index', [
            'title' => __('E-mail postavke'),
            'settings' => $this->settings->settingsForForm(),
            'statusCounts' => $this->email->statusCounts(),
            'tablesReady' => $this->email->tablesReady(),
            'savePath' => $this->pathFor('email.settings.save', '/settings/email'),
            'testPath' => $this->pathFor('email.settings.test', '/settings/email/test'),
            'settingsPath' => $this->pathFor('email.settings', '/settings/email'),
            'settingsMenuActiveSection' => 'email.settings',
            'testRecipient' => $this->stringValue($user['email'] ?? ''),
        ]);
    }

    /**
     * HR: Sprema SMTP konfiguraciju i vraća se na novu instancu postavki.
     * EN: Saves SMTP configuration and redirects to a fresh settings instance.
     */
    public function save(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->settings->saveFromForm(
                $this->associativeArray($request->getParsedBody()),
            );
            $this->alertHandler->add(new Alert(
                __('E-mail postavke su spremljene.'),
                AlertLevelEnum::Success,
            ));
        } catch (Throwable $throwable) {
            $this->alertHandler->add(new Alert($throwable->getMessage(), AlertLevelEnum::Danger));
        }

        return $this->responseFactory->redirect(
            $this->pathFor('email.settings', '/settings/email'),
        );
    }

    /**
     * HR: Stavlja testnu poruku u red i odmah je obrađuje istim SMTP transportom.
     * EN: Queues a test message and immediately processes it with the same SMTP transport.
     */
    public function test(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $body = $this->associativeArray($request->getParsedBody());
            $recipient = $this->stringValue($body['recipient_email'] ?? '');
            $user = $this->currentUser();
            $queued = $this->email->queue(
                $recipient,
                $this->stringValue($user['display_name'] ?? ''),
                __('HeartPhrame testna e-mail poruka'),
                __('Ova poruka potvrđuje da SMTP postavke i outbox worker rade.'),
                null,
                null,
                $this->intValue($user['id'] ?? 0),
            );
            if (!is_array($queued)) {
                throw new \RuntimeException(__('E-mail slanje je isključeno.'));
            }

            $sent = $this->worker->workUuid($this->stringValue($queued['uuid'] ?? ''));
            $this->alertHandler->add(new Alert(
                $sent
                    ? __('Testna e-mail poruka je poslana.')
                    : __('Testno slanje nije uspjelo; detalj je spremljen u outboxu.'),
                $sent ? AlertLevelEnum::Success : AlertLevelEnum::Danger,
            ));
        } catch (Throwable $throwable) {
            $this->alertHandler->add(new Alert($throwable->getMessage(), AlertLevelEnum::Danger));
        }

        return $this->responseFactory->redirect(
            $this->pathFor('email.settings', '/settings/email'),
        );
    }

    /**
     * HR: Vraća normalizirani session payload administratora.
     * EN: Returns the normalized administrator session payload.
     *
     * @return array<string, mixed>
     */
    private function currentUser(): array
    {
        return $this->associativeArray($this->authnHandler->userData());
    }

    /**
     * HR: Zadržava samo string ključeve vanjskog array payloada.
     * EN: Keeps only string keys from an external array payload.
     *
     * @return array<string, mixed>
     */
    private function associativeArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * HR: Generira named rutu ili stabilni fallback.
     * EN: Generates a named route or stable fallback.
     */
    private function pathFor(string $routeName, string $fallback): string
    {
        return $this->urlGenerator->namedRouteExists($routeName)
        ? $this->urlGenerator->getPathFor($routeName)
        : $fallback;
    }

    /**
     * HR: Normalizira skalarnu vrijednost u string.
     * EN: Normalizes a scalar value to a string.
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * HR: Pretvara numeričku vrijednost u integer.
     * EN: Converts a numeric value to an integer.
     */
    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }
}
