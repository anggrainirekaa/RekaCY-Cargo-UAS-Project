<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** In-app (database) notification; email is added only when enabled in config. */
class ShipmentAlert extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public int $shipmentId,
        public string $shipmentNumber,
        public string $level = 'info', // info | success | warning | danger
    ) {
    }

    public function via(object $notifiable): array
    {
        return config('rekacy.notifications.mail') && $notifiable->email
            ? ['database', 'mail']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[RekaCy] {$this->title} — {$this->shipmentNumber}")
            ->line($this->message)
            ->action('Open shipment', url('/shipments/'.$this->shipmentId));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'shipment_id' => $this->shipmentId,
            'shipment_number' => $this->shipmentNumber,
            'level' => $this->level,
        ];
    }
}
