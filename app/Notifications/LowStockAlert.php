<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public InventoryItem $item) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Low stock: {$this->item->name}")
            ->line("{$this->item->name} ({$this->item->sku}) has fallen to {$this->item->quantity} {$this->item->unit}, at or below its reorder level of {$this->item->reorder_level}.")
            ->action('View stock', route('admin.inventory.index', ['search' => $this->item->sku]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Low stock',
            'message' => "{$this->item->name} is down to {$this->item->quantity} {$this->item->unit} (reorder at {$this->item->reorder_level}).",
            'url' => route('admin.inventory.index', ['search' => $this->item->sku], false),
            'level' => 'warning',
        ];
    }
}
