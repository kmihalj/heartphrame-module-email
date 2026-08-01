# HeartPhrame E-mail Module

[Hrvatska verzija](README_hr.md)

The E-mail module provides one application-wide, persistent SMTP delivery
service. It is implemented in pure PHP and does not use Symfony Mailer,
PHPMailer, or another mail transport package.

## Dependencies

Required, in enable order:

1. `aaieduhr/heartphrame-framework` (`dev-main`)
2. `aaieduhr/heartphrame-module-orm` (`dev-main`)
3. `aaieduhr/heartphrame-module-auth` (`dev-main`)
4. `aaieduhr/heartphrame-module-email` (`dev-main`)

Notification is not required by E-mail. The optional integration works in the
other direction: Notification uses this module to queue user-approved e-mail
copies of in-app messages.

```bash
composer require aaieduhr/heartphrame-module-email:dev-main
vendor/bin/hph email:install-migration
vendor/bin/hph orm-migrate:up
```

Croatian documentation: [README_hr.md](README_hr.md)

## Features

- administrator settings at `/settings/email`
- non-invasive shared settings-menu registration that preserves manually configured ordering
- SMTP host, port, and `STARTTLS`, implicit TLS, or unencrypted transport
- optional `AUTH PLAIN` and `AUTH LOGIN` username/password authentication
- TLS certificate verification and an explicit self-signed-certificate option
- sender address and name, connection/I/O timeouts, and public application URL
- UTF-8 plain-text and multipart HTML messages
- header-injection protection, quoted-printable bodies, and SMTP dot-stuffing
- portable ORM outbox with retries, increasing delay, and stale-lock recovery
- one-off SMTP test through the same queue and transport used in production
- CLI worker suitable for cron, systemd, Supervisor, or another process manager
- no sample messages or SMTP credentials in the initial migration

The password is stored only in the host application's `config/email.php`.
Settings pages never return it to HTML. Submitting a blank password preserves
the existing value.

## Requirements

- PHP 8.2 or newer with OpenSSL when TLS is used
- `aaieduhr/heartphrame-framework`
- `aaieduhr/heartphrame-module-auth`
- `aaieduhr/heartphrame-module-orm`

There is no external runtime mail dependency.

## Installation

```bash
composer require aaieduhr/heartphrame-module-email
vendor/bin/hph email:install-migration
vendor/bin/hph orm-migrate:up
```

Enable the module after ORM and Auth:

```php
'aaieduhr/heartphrame-module-email',
```

Open **Settings > E-mail**, enter the SMTP server and authentication details,
save, and use **Send test**. For background delivery, run:

```bash
vendor/bin/hph email worker --limit=20
```

Use a process manager for a long-running worker or call it periodically from
cron. Web requests only queue messages and therefore do not wait for SMTP.

## Public API

Other modules resolve `EmailService` from the container and call `queue()` for
an address or `queueForUser()` for an Auth user. A deduplication key prevents
the same logical message from being queued twice.

Detailed configuration, worker, and troubleshooting notes are in
[docs/index_en.md](docs/index_en.md).

## Licence

This work is published under the
[European Union Public License (EUPL) v1.2](LICENSE).

## Dependency policy

The Framework and internal HeartPhrame modules are required from the moving
`dev-main` branch. This module does not commit `composer.lock`; CI resolves
the latest development heads and runs the complete `composer on-commit` suite.
