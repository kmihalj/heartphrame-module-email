# E-mail Module Guide

## Architecture

`EmailService` is the public queue API. It validates an address and message,
stores one row in `email_outbox`, and returns immediately. `EmailOutboxWorker`
claims available rows through the ORM, and `SmtpClient` performs the network
conversation. This separation keeps page requests fast and lets large sites
run several independent workers.

All database work uses the HeartPhrame ORM. The initial schema is portable
across SQLite, PostgreSQL, and MySQL/MariaDB.

## SMTP Settings

Only an administrator can open `/settings/email`.

| Setting | Purpose |
| --- | --- |
| Enabled | Allows new messages to enter the outbox and workers to send them. |
| Host / port | SMTP server endpoint. Common ports are 587 for STARTTLS and 465 for implicit TLS. |
| Encryption | `starttls`, `tls`, or `none`. Use `none` only on a trusted local network. |
| Username / password | Optional SMTP authentication. Blank username means no AUTH. |
| Verify certificate | Validates the server certificate and hostname. Keep enabled in production. |
| Allow self-signed | Permits a self-signed certificate when explicitly required. |
| Sender | Address and display name used in the `From` header. |
| Public URL | Prefix used to turn `/local/path` notification links into absolute mail links. |
| Notification copies | Enables e-mail copies created by the Notification module. |
| Attempts / delay | Retry policy for transient SMTP failures. |

The password is deliberately absent from `settingsForForm()`. A blank password
preserves the secret already stored in the host config. Protect
`config/email.php` with normal application filesystem permissions and never
commit real credentials.

## Pure-PHP Transport

The transport uses PHP stream sockets. It performs EHLO/HELO, optional TLS
upgrade, PLAIN or LOGIN authentication, envelope commands, MIME generation,
dot-stuffing, and response validation. OpenSSL is required only for TLS.

The client intentionally supports SMTP sending, not mailbox reading (IMAP/POP3).

## Worker Operations

Run one batch:

```bash
vendor/bin/hph email worker --limit=20
```

Run continuously:

```bash
vendor/bin/hph email worker --limit=20 --watch
```

Inspect counts:

```bash
vendor/bin/hph email status
```

Failed transient messages return to `pending` with increasing delay. After the
configured maximum attempts, a row stays `failed` with a diagnostic message.
A worker interrupted after claiming a row leaves a lock; stale locks are
automatically recovered after 15 minutes.

## Integration Example

```php
$email = $container->get(
    \AaiEduHr\HeartPhrameModuleEmail\Service\EmailService::class,
);
$email->queueForUser(
    $userId,
    'Page published',
    'Your page is now available.',
    null,
    'workspace:published:42:hr:7',
    '/workspace/team/page',
);
```

Callers should treat e-mail as an auxiliary channel. The primary business
operation must remain successful when the SMTP server is unavailable.
