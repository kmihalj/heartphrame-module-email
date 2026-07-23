<?php

declare(strict_types=1);

$hr = require __DIR__ . '/hr.php';

return [
    ...$hr,
    'E-mail postavke' => 'E-mail settings',
    'Čisti PHP SMTP transport i trajni outbox za sve module aplikacije.' =>
        'Pure PHP SMTP transport and a persistent outbox for every application module.',
    'Nedostaje E-mail migracija.' => 'The E-mail migration is missing.',
    'Instalirajte početnu migraciju prije uključivanja slanja.' =>
        'Install the initial migration before enabling delivery.',
    'E-mail slanje je uključeno' => 'E-mail delivery is enabled',
    'SMTP veza' => 'SMTP connection',
    'Šifriranje' => 'Encryption',
    'tls' => 'Implicit TLS',
    'none' => 'No encryption',
    'Korisničko ime' => 'Username',
    'Lozinka' => 'Password',
    'Ostavite prazno za čuvanje postojeće lozinke' => 'Leave blank to preserve the existing password',
    'Timeout veze (s)' => 'Connection timeout (s)',
    'SMTP timeout (s)' => 'SMTP timeout (s)',
    'Provjeri TLS certifikat' => 'Verify TLS certificate',
    'Dopusti self-signed certifikat' => 'Allow a self-signed certificate',
    'Pošiljatelj i poveznice' => 'Sender and links',
    'Adresa pošiljatelja' => 'Sender address',
    'Ime pošiljatelja' => 'Sender name',
    'Javni URL aplikacije' => 'Public application URL',
    'Koristi se za pretvaranje lokalnih putanja u poveznice unutar poruke.' =>
        'Used to turn local paths into links inside messages.',
    'Šalji e-mail kopije in-app obavijesti' => 'Send e-mail copies of in-app notifications',
    'Maksimalno pokušaja' => 'Maximum attempts',
    'Osnovna retry odgoda (s)' => 'Base retry delay (s)',
    'Stanje reda' => 'Queue status',
    'pending' => 'Pending',
    'sending' => 'Sending',
    'sent' => 'Sent',
    'failed' => 'Failed',
    'Web zahtjev samo sprema poruku. Pokrenite CLI outbox worker kroz cron ili process manager.' =>
        'The web request only queues the message. Run the CLI outbox worker through cron or a process manager.',
    'Spremi postavke' => 'Save settings',
    'Test SMTP-a' => 'SMTP test',
    'Test koristi isti outbox i transport kao stvarne poruke.' =>
        'The test uses the same outbox and transport as real messages.',
    'Adresa primatelja' => 'Recipient address',
    'Pošalji test' => 'Send test',
    'E-mail postavke su spremljene.' => 'E-mail settings were saved.',
    'HeartPhrame testna e-mail poruka' => 'HeartPhrame test e-mail message',
    'Ova poruka potvrđuje da SMTP postavke i outbox worker rade.' =>
        'This message confirms that SMTP settings and the outbox worker work.',
    'Testna e-mail poruka je poslana.' => 'The test e-mail was sent.',
    'Testno slanje nije uspjelo; detalj je spremljen u outboxu.' =>
        'Test delivery failed; details were stored in the outbox.',
    'E-mail slanje je isključeno.' => 'E-mail delivery is disabled.',
    'Adresa primatelja nije valjana.' => 'The recipient address is invalid.',
    'Naslov e-mail poruke je obavezan.' => 'The e-mail subject is required.',
    'E-mail poruka mora imati sadržaj.' => 'The e-mail message must have content.',
    'SMTP host je obavezan kada je slanje uključeno.' =>
        'SMTP host is required when delivery is enabled.',
    'Adresa pošiljatelja nije valjana.' => 'The sender address is invalid.',
    'Javni URL aplikacije nije valjan.' => 'The public application URL is invalid.',
    'Nije moguće kreirati direktorij E-mail postavki.' =>
        'The E-mail settings directory cannot be created.',
    'Nije moguće zapisati E-mail postavke.' => 'E-mail settings cannot be written.',
    'E-mail migracija nije primijenjena.' => 'The E-mail migration has not been applied.',
    'Spremljenu e-mail poruku nije moguće učitati.' => 'The saved e-mail message cannot be loaded.',
    'SMTP host nije podešen.' => 'SMTP host is not configured.',
    'Nije moguće povezati se na SMTP poslužitelj: %s (%d).' =>
        'Cannot connect to the SMTP server: %s (%d).',
    'SMTP poslužitelj nije prihvatio vezu.' => 'The SMTP server did not accept the connection.',
    'SMTP poslužitelj nije prihvatio EHLO/HELO.' => 'The SMTP server did not accept EHLO/HELO.',
    'SMTP poslužitelj ne podržava zahtijevani STARTTLS.' =>
        'The SMTP server does not support the required STARTTLS.',
    'PHP OpenSSL ekstenzija potrebna je za STARTTLS.' =>
        'The PHP OpenSSL extension is required for STARTTLS.',
    'Nije uspjela TLS nadogradnja SMTP veze.' => 'The SMTP connection TLS upgrade failed.',
    'SMTP autentikacija nije uspjela.' => 'SMTP authentication failed.',
    'SMTP naredba nije prihvaćena.' => 'The SMTP command was not accepted.',
    'SMTP poslužitelj nije prihvatio poruku.' => 'The SMTP server did not accept the message.',
    'SMTP veza nije otvorena.' => 'The SMTP connection is not open.',
    'SMTP odgovor je istekao.' => 'The SMTP response timed out.',
    'SMTP veza je neočekivano prekinuta.' => 'The SMTP connection ended unexpectedly.',
    'Nije moguće pisati u SMTP vezu.' => 'Cannot write to the SMTP connection.',
    'Testna e-mail poruka nije pronađena u redu.' => 'The test e-mail was not found in the queue.',
    'Predložak E-mail migracije nije pronađen.' => 'The E-mail migration template was not found.',
    'Nije moguće kreirati direktorij migracija.' => 'The migration directory cannot be created.',
    'Nije moguće kopirati E-mail migraciju.' => 'The E-mail migration cannot be copied.',
    'Kreirana je početna E-mail migracija: ' => 'Created initial E-mail migration: ',
    'Sljedeći korak: pokreni `vendor/bin/hph orm-migrate up`.' =>
        'Next step: run `vendor/bin/hph orm-migrate up`.',
    'Obrađeno: %d, poslano: %d, neuspjelo: %d.' =>
        'Processed: %d, sent: %d, failed: %d.',
    'Nepoznata E-mail podnaredba: %s' => 'Unknown E-mail subcommand: %s',
    'Naziv migracije ne smije biti prazan.' => 'The migration name cannot be empty.',
    'Postavke' => 'Settings',
];
