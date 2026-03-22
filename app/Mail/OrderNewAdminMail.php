<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderNewAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {
        $this->order->load(['orderItems', 'orderAddresses', 'status', 'shippingMethod', 'paymentMethod']);
    }

    public function envelope(): Envelope
    {
        $replyTo = [];
        if (filled($this->order->customer_email)) {
            $replyTo[] = new Address($this->order->customer_email, $this->order->customer_name);
        }

        return new Envelope(
            subject: 'Новый заказ #'.$this->order->id.' — '.$this->order->customer_name,
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-new-admin',
        );
    }
}
