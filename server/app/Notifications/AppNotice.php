<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** In-app notification (database channel only, per product decision). */
class AppNotice extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public string $kind = 'info',        // order | ticket | account | offer | info
        public array $data = [],             // e.g. ['order_id' => 12]
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'kind' => $this->kind] + $this->data;
    }
}
