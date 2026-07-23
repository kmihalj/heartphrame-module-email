# Upute za E-mail modul

## Arhitektura

`EmailService` je javni API reda. Provjerava adresu i poruku, sprema jedan redak
u `email_outbox` i odmah vraća kontrolu. `EmailOutboxWorker` kroz ORM preuzima
raspoložive retke, a `SmtpClient` obavlja mrežni razgovor. Tako web zahtjevi
ostaju brzi, a velike instalacije mogu pokrenuti više neovisnih workera.

Sav rad s bazom koristi HeartPhrame ORM. Početna shema je prijenosna između
SQLitea, PostgreSQL-a i MySQL-a/MariaDB-a.

## SMTP postavke

Samo administrator može otvoriti `/settings/email`.

| Postavka | Namjena |
| --- | --- |
| Uključeno | Dopušta spremanje novih poruka i njihovo slanje workerom. |
| Host / port | SMTP endpoint. Uobičajeni su 587 za STARTTLS i 465 za implicitni TLS. |
| Šifriranje | `starttls`, `tls` ili `none`. `none` koristite samo na pouzdanoj lokalnoj mreži. |
| Korisnik / lozinka | Opcionalna SMTP autentikacija. Prazan korisnik znači bez AUTH-a. |
| Provjera certifikata | Provjerava certifikat i ime poslužitelja. Ostavite uključeno u produkciji. |
| Self-signed | Dopušta self-signed certifikat samo kada je izričito potreban. |
| Pošiljatelj | Adresa i prikazno ime u `From` zaglavlju. |
| Javni URL | Prefiks koji `/lokalnu/putanju` pretvara u apsolutni link u mailu. |
| Kopije obavijesti | Uključuje mail kopije koje stvara Notification modul. |
| Pokušaji / odgoda | Pravilo ponavljanja kod privremenih SMTP grešaka. |

Lozinka namjerno nije dio rezultata `settingsForForm()`. Prazno password polje
čuva tajnu koja je već u host konfiguraciji. `config/email.php` zaštitite
uobičajenim ovlastima aplikacije i nikada ne commitajte stvarne vjerodajnice.

## Čisti PHP transport

Transport koristi PHP stream socket. Izvodi EHLO/HELO, opcionalnu TLS nadogradnju,
PLAIN ili LOGIN autentikaciju, envelope naredbe, MIME izgradnju, dot-stuffing i
provjeru odgovora. OpenSSL je potreban samo kada se koristi TLS.

Klijent služi za SMTP slanje, a ne za čitanje sandučića putem IMAP-a/POP3-a.

## Rad workera

Jedan batch:

```bash
vendor/bin/hph email worker --limit=20
```

Stalni rad:

```bash
vendor/bin/hph email worker --limit=20 --watch
```

Stanje reda:

```bash
vendor/bin/hph email status
```

Privremeno neuspjele poruke vraćaju se u `pending` uz rastuću odgodu. Nakon
maksimalnog broja pokušaja ostaju `failed` s dijagnostičkom porukom. Ako se
worker prekine nakon preuzimanja retka, zaboravljeni lock automatski se vraća
u red nakon 15 minuta.

## Primjer integracije

```php
$email = $container->get(
    \AaiEduHr\HeartPhrameModuleEmail\Service\EmailService::class,
);
$email->queueForUser(
    $userId,
    'Stranica je objavljena',
    'Vaša stranica sada je dostupna.',
    null,
    'workspace:published:42:hr:7',
    '/workspace/tim/stranica',
);
```

Pozivatelj treba tretirati e-mail kao pomoćni kanal. Primarna poslovna radnja
mora ostati uspješna i kada SMTP poslužitelj nije dostupan.
