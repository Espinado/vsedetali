<?php

namespace App\Mail;

use App\Models\SellerStaff;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerStaffInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SellerStaff $staff,
        public string $inviteUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Доступ к кабинету продавца — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller-staff-invite',
        );
    }
}
