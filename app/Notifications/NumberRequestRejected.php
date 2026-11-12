<?php

namespace App\Notifications;

use App\Models\NumberRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NumberRequestRejected extends Notification implements ShouldQueue
{
    use Queueable;

    protected $request;

    /**
     * Create a new notification instance.
     */
    public function __construct(NumberRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Phone Number Request Not Approved - ' . config('app.name'))
            ->line('We regret to inform you that your phone number request was not approved.')
            ->line('Number: ' . $this->request->phoneNumber->formatted_number)
            ->when($this->request->admin_notes, function($mail) {
                return $mail->line('Reason: ' . $this->request->admin_notes);
            })
            ->action('Browse Available Numbers', url('/numbers/available'))
            ->line('You can request a different phone number from our available inventory.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'number_request_rejected',
            'request_id' => $this->request->id,
            'phone_number' => $this->request->phoneNumber->number,
            'reason' => $this->request->admin_notes,
            'message' => 'Your request for ' . $this->request->phoneNumber->formatted_number . ' was not approved.',
        ];
    }
}
