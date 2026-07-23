<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Service;

use RuntimeException;
use Throwable;

use function base64_encode;
use function bin2hex;
use function date;
use function extension_loaded;
use function fclose;
use function feof;
use function fgets;
use function filter_var;
use function fwrite;
use function gethostname;
use function implode;
use function is_resource;
use function is_string;
use function mb_strcut;
use function preg_match;
use function preg_replace;
use function quoted_printable_encode;
use function random_bytes;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function stream_context_create;
use function stream_get_meta_data;
use function stream_set_timeout;
use function stream_socket_client;
use function stream_socket_enable_crypto;
use function strlen;
use function strtoupper;
use function trim;

use const DATE_RFC2822;
use const FILTER_VALIDATE_EMAIL;
use const STREAM_CLIENT_CONNECT;
use const STREAM_CRYPTO_METHOD_TLS_CLIENT;

/**
 * HR: Minimalan, prenosiv SMTP klijent napisan isključivo u PHP-u. Podržava
 *     SMTP, STARTTLS, implicitni TLS, AUTH PLAIN/LOGIN i UTF-8 MIME sadržaj.
 * EN: A minimal, portable SMTP client written only in PHP. It supports SMTP,
 *     STARTTLS, implicit TLS, AUTH PLAIN/LOGIN, and UTF-8 MIME content.
 */
final class SmtpClient
{
    /**
     * @var resource|false
     */
    private $socket = false;

    /**
     * HR: Šalje jednu poruku prema efektivnoj konfiguraciji i uvijek zatvara socket.
     * EN: Sends one message using effective configuration and always closes the socket.
     */
    public function send(EmailConfig $config, EmailMessage $message): void
    {
        $this->assertConfiguration($config);

        try {
            $this->connect($config);
            $this->expect($this->readResponse(), [220], __('SMTP poslužitelj nije prihvatio vezu.'));
            $capabilities = $this->hello();

            if ($config->encryption() === 'starttls') {
                $this->startTls($capabilities);
                $capabilities = $this->hello();
            }

            $this->authenticate($config, $capabilities);
            $this->command('MAIL FROM:<' . $config->senderEmail() . '>', [250]);
            $this->command('RCPT TO:<' . $message->recipientEmail . '>', [250, 251]);
            $this->command('DATA', [354]);
            $this->writeData($this->buildMimeMessage($config, $message));
            $this->expect($this->readResponse(), [250], __('SMTP poslužitelj nije prihvatio poruku.'));
            $this->command('QUIT', [221]);
        } catch (Throwable $throwable) {
            throw $throwable instanceof RuntimeException
            ? $throwable
            : new RuntimeException($throwable->getMessage(), 0, $throwable);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }

            $this->socket = false;
        }
    }

    /**
     * HR: Otvara TCP ili implicitni TLS stream s provjerom certifikata.
     * EN: Opens a TCP or implicit TLS stream with certificate verification.
     */
    private function connect(EmailConfig $config): void
    {
        $host = $config->host();
        $socketHost = str_contains($host, ':') && !str_starts_with($host, '[')
        ? '[' . $host . ']'
        : $host;
        $scheme = $config->encryption() === 'tls' ? 'tls' : 'tcp';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $config->verifyPeer(),
                'verify_peer_name' => $config->verifyPeer(),
                'allow_self_signed' => $config->allowSelfSigned(),
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);
        $errno = 0;
        $error = '';
        $socket = @stream_socket_client(
            $scheme . '://' . $socketHost . ':' . $config->port(),
            $errno,
            $error,
            $config->connectTimeout(),
            STREAM_CLIENT_CONNECT,
            $context,
        );
        if (!is_resource($socket)) {
            throw new RuntimeException(sprintf(
                __('Nije moguće povezati se na SMTP poslužitelj: %s (%d).'),
                $error,
                $errno,
            ));
        }

        stream_set_timeout($socket, $config->ioTimeout());
        $this->socket = $socket;
    }

    /**
     * HR: Šalje EHLO, a za stari poslužitelj pokušava HELO.
     * EN: Sends EHLO and falls back to HELO for an older server.
     */
    private function hello(): string
    {
        $hostname = gethostname();
        $hostname = is_string($hostname) && $hostname !== '' ? $hostname : 'localhost';
        $this->writeLine('EHLO ' . $hostname);
        $response = $this->readResponse();
        if ($response['code'] === 250) {
            return strtoupper($response['text']);
        }

        $this->writeLine('HELO ' . $hostname);
        $response = $this->readResponse();
        $this->expect($response, [250], __('SMTP poslužitelj nije prihvatio EHLO/HELO.'));

        return strtoupper($response['text']);
    }

    /**
     * HR: Nadograđuje običnu vezu u TLS i zahtijeva OpenSSL ekstenziju.
     * EN: Upgrades the plain connection to TLS and requires the OpenSSL extension.
     */
    private function startTls(string $capabilities): void
    {
        if (!str_contains($capabilities, 'STARTTLS')) {
            throw new RuntimeException(__('SMTP poslužitelj ne podržava zahtijevani STARTTLS.'));
        }

        if (!extension_loaded('openssl')) {
            throw new RuntimeException(__('PHP OpenSSL ekstenzija potrebna je za STARTTLS.'));
        }

        $this->command('STARTTLS', [220]);
        if (
            !is_resource($this->socket)
            || stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true
        ) {
            throw new RuntimeException(__('Nije uspjela TLS nadogradnja SMTP veze.'));
        }
    }

    /**
     * HR: Autenticira korisnika preferirajući PLAIN, a zatim LOGIN.
     * EN: Authenticates the user, preferring PLAIN and then LOGIN.
     */
    private function authenticate(EmailConfig $config, string $capabilities): void
    {
        if ($config->username() === '') {
            return;
        }

        if (str_contains($capabilities, 'AUTH') && str_contains($capabilities, 'PLAIN')) {
            $this->writeLine(
                'AUTH PLAIN ' . base64_encode("\0" . $config->username() . "\0" . $config->password()),
            );
            $response = $this->readResponse();
            if ($response['code'] === 334) {
                $this->writeLine(base64_encode("\0" . $config->username() . "\0" . $config->password()));
                $response = $this->readResponse();
            }

            $this->expect($response, [235], __('SMTP autentikacija nije uspjela.'));

            return;
        }

        $this->command('AUTH LOGIN', [334]);
        $this->command(base64_encode($config->username()), [334]);
        $this->command(base64_encode($config->password()), [235]);
    }

    /**
     * HR: Šalje jednu SMTP naredbu i provjerava očekivani odgovor.
     * EN: Sends one SMTP command and validates the expected response.
     *
     * @param list<int> $expectedCodes
     */
    private function command(string $command, array $expectedCodes): string
    {
        $this->writeLine($command);
        $response = $this->readResponse();
        $this->expect($response, $expectedCodes, __('SMTP naredba nije prihvaćena.'));

        return $response['text'];
    }

    /**
     * HR: Čita i spaja jedno- ili višelinijski SMTP odgovor.
     * EN: Reads and joins a single-line or multiline SMTP response.
     *
     * @return array{code: int, text: string}
     */
    private function readResponse(): array
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException(__('SMTP veza nije otvorena.'));
        }

        $lines = [];
        $code = 0;
        do {
            $line = fgets($this->socket, 8192);
            if (!is_string($line)) {
                $meta = stream_get_meta_data($this->socket);
                $reason = $meta['timed_out']
                ? __('SMTP odgovor je istekao.')
                : __('SMTP veza je neočekivano prekinuta.');
                throw new RuntimeException($reason);
            }

            $line = rtrim($line, "\r\n");
            $lines[] = $line;
            if (preg_match('/^(\d{3})([ -])/', $line, $matches) === 1) {
                $code = (int)$matches[1];
                $continued = $matches[2] === '-';
            } else {
                $continued = false;
            }
        } while ($continued && !feof($this->socket));

        return ['code' => $code, 'text' => implode("\n", $lines)];
    }

    /**
     * HR: Uspoređuje SMTP kod s dopuštenim odgovorima i uključuje serverovu
     *     poruku u dijagnostiku bez otkrivanja vjerodajnica.
     * EN: Compares the SMTP code with allowed responses and includes the server
     *     message in diagnostics without exposing credentials.
     *
     * @param array{code: int, text: string} $response
     * @param list<int> $expectedCodes
     */
    private function expect(array $response, array $expectedCodes, string $message): void
    {
        if (!in_array($response['code'], $expectedCodes, true)) {
            throw new RuntimeException(
                $message . ' [' . $response['code'] . '] ' . $response['text'],
            );
        }
    }

    /**
     * HR: Šalje završni DATA payload uz SMTP dot-stuffing.
     * EN: Sends the final DATA payload with SMTP dot-stuffing.
     */
    private function writeData(string $message): void
    {
        $message = $this->normalizeLineEndings($message);
        $message = preg_replace('/^\./m', '..', $message) ?? $message;
        $message = rtrim($message, "\r\n") . "\r\n";
        $this->writeAll($message . ".\r\n");
    }

    /**
     * HR: Piše jednu naredbenu liniju.
     * EN: Writes one command line.
     */
    private function writeLine(string $line): void
    {
        $this->writeAll($line . "\r\n");
    }

    /**
     * HR: Piše sve bajtove i obrađuje parcijalni `fwrite`.
     * EN: Writes every byte and handles partial `fwrite` calls.
     */
    private function writeAll(string $data): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException(__('SMTP veza nije otvorena.'));
        }

        $length = strlen($data);
        $written = 0;
        while ($written < $length) {
            $bytes = fwrite($this->socket, substr($data, $written));
            if (!is_int($bytes) || $bytes <= 0) {
                throw new RuntimeException(__('Nije moguće pisati u SMTP vezu.'));
            }

            $written += $bytes;
        }
    }

    /**
     * HR: Gradi RFC/MIME poruku s plain-text sadržajem i opcionalnim HTML dijelom.
     * EN: Builds an RFC/MIME message with plain-text content and an optional HTML part.
     */
    private function buildMimeMessage(EmailConfig $config, EmailMessage $message): string
    {
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->messageIdDomain($config->host()) . '>',
            'From: ' . $this->mailbox($config->senderEmail(), $config->senderName()),
            'To: ' . $this->mailbox($message->recipientEmail, $message->recipientName),
            'Subject: ' . $this->encodedHeader($message->subject),
            'MIME-Version: 1.0',
        ];

        if ($message->bodyHtml === null || trim($message->bodyHtml) === '') {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';

            return implode("\r\n", $headers)
            . "\r\n\r\n"
            . quoted_printable_encode($this->normalizeLineEndings($message->bodyText));
        }

        $boundary = '=_hph_' . bin2hex(random_bytes(16));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $plain = quoted_printable_encode($this->normalizeLineEndings($message->bodyText));
        $html = quoted_printable_encode($this->normalizeLineEndings($message->bodyHtml));

        return implode("\r\n", $headers)
        . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
        . $plain . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
        . $html . "\r\n"
        . '--' . $boundary . "--\r\n";
    }

    /**
     * HR: Formatira mailbox uz RFC 2047 kodirano prikazno ime.
     * EN: Formats a mailbox with an RFC 2047 encoded display name.
     */
    private function mailbox(string $email, string $name): string
    {
        return trim($name) !== ''
        ? $this->encodedHeader($name) . ' <' . $email . '>'
        : '<' . $email . '>';
    }

    /**
     * HR: Kodira Unicode zaglavlje u više sigurnih RFC 2047 riječi.
     * EN: Encodes a Unicode header into safe RFC 2047 words.
     */
    private function encodedHeader(string $value): string
    {
        $value = $this->safeHeaderValue($value);
        $words = [];
        $offset = 0;
        $length = strlen($value);
        while ($offset < $length) {
            $chunk = mb_strcut($value, $offset, 42, 'UTF-8');
            if ($chunk === '') {
                break;
            }

            $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
            $offset += strlen($chunk);
        }

        return implode("\r\n ", $words);
    }

    /**
     * HR: Uklanja CR/LF iz zaglavlja radi sprječavanja header injectiona.
     * EN: Removes CR/LF from a header to prevent header injection.
     */
    private function safeHeaderValue(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    /**
     * HR: Normalizira line ending na SMTP CRLF.
     * EN: Normalizes line endings to SMTP CRLF.
     */
    private function normalizeLineEndings(string $value): string
    {
        return str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $value));
    }

    /**
     * HR: Vraća siguran Message-ID domain.
     * EN: Returns a safe Message-ID domain.
     */
    private function messageIdDomain(string $host): string
    {
        $domain = preg_replace('/[^a-z0-9.-]+/i', '', $host) ?? '';

        return $domain !== '' ? $domain : 'localhost';
    }

    /**
     * HR: Provjerava obavezne SMTP i sender postavke prije mrežnog poziva.
     * EN: Validates required SMTP and sender settings before a network call.
     */
    private function assertConfiguration(EmailConfig $config): void
    {
        if ($config->host() === '') {
            throw new RuntimeException(__('SMTP host nije podešen.'));
        }

        if (!is_string(filter_var($config->senderEmail(), FILTER_VALIDATE_EMAIL))) {
            throw new RuntimeException(__('Adresa pošiljatelja nije valjana.'));
        }
    }
}
