<?php

namespace App\Notifications;

use App\Models\NumberRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NumberRequestCreated extends Notification implements ShouldQueue
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
            ->subject('New Phone Number Request - ' . config('app.name'))
            ->line($this->request->customer->name . ' has requested a phone number.')
            ->line('Number: ' . $this->request->phoneNumber->formatted_number)
            ->line('Country: ' . $this->request->phoneNumber->country_code)
            ->when($this->request->customer_notes, function($mail) {
                return $mail->line('Customer Notes: ' . $this->request->customer_notes);
            })
            ->action('Review Request', url('/admin/number-requests'))
            ->line('Please review and approve/reject this request.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'number_request_created',
            'request_id' => $this->request->id,
            'customer_id' => $this->request->customer_id,
            'customer_name' => $this->request->customer->name,
            'phone_number' => $this->request->phoneNumber->number,
            'message' => $this->request->customer->name . ' requested ' . $this->request->phoneNumber->formatted_number,
        ];
    }
}
