<?php

namespace App\Notifications;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells website staff that a contact-form message is waiting.
 */
class NewEnquiry extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Enquiry $enquiry) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New website enquiry',
            'message' => Str::limit("{$this->enquiry->name}: {$this->enquiry->subject}", 120),
            'url' => route('admin.website.enquiries.index', [], false),
            'level' => 'info',
        ];
    }
}
