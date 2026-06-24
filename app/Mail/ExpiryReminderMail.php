<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * What: The queued email sent for a single expiry reminder — carries the already-rendered subject and body.
 * Why: The dispatcher renders the rule's templates (via ReminderTemplate) before constructing the Mailable,
 *      so this class stays dumb: it just wraps the final subject/body. Queued (`ShouldQueue`) per idea.md so
 *      a large daily batch never blocks the scheduler. The markdown body is passed through so admins can use
 *      basic markdown in rule bodies.
 * When: Built and queued by the ReminderDispatcher for each notify_client / notify_admin send.
 */
class ExpiryReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $subjectLine  The rendered email subject.
     * @param  string  $bodyMarkdown  The rendered markdown body.
     */
    public function __construct(
        public string $subjectLine,
        public string $bodyMarkdown,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.expiry-reminder',
            with: ['bodyMarkdown' => $this->bodyMarkdown],
        );
    }
}
