<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScoringReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $recipientName   Examiner/supervisor full name
     * @param  string  $eventLabel      e.g. PRE-220326-1
     * @param  string  $defenseType     e.g. Pre-Defense / Final Defense
     * @param  string  $eventDate       Formatted date string
     * @param  string  $organizerProgram e.g. "Electrical Engineering (E505.TE)"
     * @param  array   $participants    Each: ['nim', 'name', 'program', 'title', 'room'?]
     */
    public function __construct(
        public readonly string $recipientName,
        public readonly string $eventLabel,
        public readonly string $defenseType,
        public readonly string $eventDate,
        public readonly string $organizerProgram,
        public readonly array  $participants,
        public readonly string $kaprodiName = '',
        public readonly string $kaprodiNip  = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Score Reminder] {$this->defenseType} – {$this->eventLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.scoring_reminder',
        );
    }
}
