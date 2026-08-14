<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleEmail\Event;

/**
 * HR: Neutralni poslovni događaj reda i isporuke e-pošte bez tijela poruke.
 * EN: Neutral business event for e-mail queuing and delivery without message bodies.
 */
final readonly class EmailDeliveryChanged
{
    /** HR: Stvara sigurni opis promjene stanja e-pošte. EN: Creates a safe description of an e-mail state change. */
    public function __construct(
        public string $messageUuid,
        public string $state,
        public ?int $recipientUserId,
        public string $recipientEmail,
        public int $attempts = 0,
        public bool $terminal = false,
    ) {
    }
}
