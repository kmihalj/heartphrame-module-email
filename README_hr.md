# HeartPhrame E-mail modul

[English version](README.md)

E-mail modul daje cijeloj aplikaciji jedan trajni servis za SMTP slanje. Napisan
je u čistom PHP-u i ne koristi Symfony Mailer, PHPMailer ni drugi paket za
e-mail transport.

## Ovisnosti

Obavezno, redoslijedom uključivanja:

1. `aaieduhr/heartphrame-framework` (`dev-main`)
2. `aaieduhr/heartphrame-module-orm` (`dev-main`)
3. `aaieduhr/heartphrame-module-auth` (`dev-main`)
4. `aaieduhr/heartphrame-module-email` (`dev-main`)

Notification nije obavezna ovisnost E-mail modula. Opcionalna integracija radi
u suprotnom smjeru: Notification koristi ovaj modul za stavljanje korisnički
odobrenih e-mail kopija obavijesti u red slanja.

```bash
composer require aaieduhr/heartphrame-module-email:dev-main
vendor/bin/hph email:install-migration
vendor/bin/hph orm-migrate:up
```

English documentation: [README.md](README.md)

## Mogućnosti

- administratorske postavke na `/settings/email`
- nenametljivu registraciju u zajednički settings meni uz očuvanje ručno podešenog redoslijeda
- SMTP host, port i `STARTTLS`, implicitni TLS ili nešifrirana veza
- opcionalna `AUTH PLAIN` i `AUTH LOGIN` autentikacija korisničkim imenom i lozinkom
- provjera TLS certifikata i izričita opcija za self-signed certifikat
- adresa i ime pošiljatelja, timeout veze/I/O-a i javni URL aplikacije
- UTF-8 tekstualne i multipart HTML poruke
- zaštita od umetanja zaglavlja, quoted-printable sadržaj i SMTP dot-stuffing
- prijenosni ORM outbox s ponovnim pokušajima, rastućom odgodom i oporavkom locka
- SMTP test kroz isti red i transport koji koristi stvarno slanje
- CLI worker za cron, systemd, Supervisor ili drugi process manager
- početna migracija bez probnih poruka i SMTP vjerodajnica

Lozinka se sprema samo u `config/email.php` host aplikacije. Stranica postavki
nikad je ne vraća u HTML. Slanje praznog password polja čuva postojeću vrijednost.

## Preduvjeti

- PHP 8.2 ili noviji te OpenSSL kada se koristi TLS
- `aaieduhr/heartphrame-framework`
- `aaieduhr/heartphrame-module-auth`
- `aaieduhr/heartphrame-module-orm`

Nema vanjske runtime ovisnosti za slanje pošte.

## Instalacija

```bash
composer require aaieduhr/heartphrame-module-email
vendor/bin/hph email:install-migration
vendor/bin/hph orm-migrate:up
```

Modul uključite nakon ORM-a i Autha:

```php
'aaieduhr/heartphrame-module-email',
```

Otvorite **Postavke > E-mail**, unesite SMTP poslužitelj i autentikaciju,
spremite te pokrenite **Pošalji test**. Za pozadinsko slanje pokrenite:

```bash
vendor/bin/hph email worker --limit=20
```

Za stalni worker koristite process manager ili ga periodički pozivajte iz
crona. Web zahtjevi samo spremaju poruke u red i ne čekaju SMTP.

## Javni API

Ostali moduli iz containera dohvaćaju `EmailService` te pozivaju `queue()` za
adresu ili `queueForUser()` za Auth korisnika. Dedup ključ sprječava višestruko
stavljanje iste logičke poruke u red.

Detaljne postavke, worker i dijagnostika opisani su u
[docs/index_hr.md](docs/index_hr.md).

## Licenca

Modul je objavljen pod
[European Union Public License (EUPL) v1.2](LICENSE).

## Politika ovisnosti

Framework i interni HeartPhrame moduli zahtijevaju se s pomične grane
`dev-main`. Ovaj modul ne sprema `composer.lock`; CI dohvaća najnovija
razvojna stanja i pokreće cijeli skup provjera `composer on-commit`.
