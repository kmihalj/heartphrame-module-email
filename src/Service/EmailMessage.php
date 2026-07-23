<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Service;

use InvalidArgumentException;

use function filter_var;
use function is_string;
use function trim;

use const FILTER_VALIDATE_EMAIL;

/**
 * HR: Nepromjenjivi DTO jedne tekstualne ili HTML e-mail poruke.
 * EN: Immutable DTO for one text or HTML e-mail message.
 */
final readonly class EmailMessage
{
    /**
     * HR: Validira adresu i obavezni sadržaj odmah pri stvaranju poruke.
     * EN: Validates the address and required content as soon as the message is created.
     */
    public function __construct(
        public string $recipientEmail,
        public string $recipientName,
        public string $subject,
        public string $bodyText,
        public ?string $bodyHtml = null,
    ) {
        if (!is_string(filter_var($recipientEmail, FILTER_VALIDATE_EMAIL))) {
            throw new InvalidArgumentException(__('Adresa primatelja nije valjana.'));
        }

        if (trim($subject) === '') {
            throw new InvalidArgumentException(__('Naslov e-mail poruke je obavezan.'));
        }

        if (trim($bodyText) === '' && trim((string)$bodyHtml) === '') {
            throw new InvalidArgumentException(__('E-mail poruka mora imati sadržaj.'));
        }
    }
}
