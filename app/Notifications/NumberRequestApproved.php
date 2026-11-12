<?php

namespace App\Notifications;

use App\Models\NumberRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NumberRequestApproved extends Notification implements ShouldQueue
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
            ->subject('Phone Number Request Approved! - ' . config('app.name'))
            ->line('Good news! Your phone number request has been approved.')
            ->line('Number: ' . $this->request->phoneNumber->formatted_number)
            ->line('Country: ' . $this->request->phoneNumber->country_code)
            ->when($this->request->admin_notes, function($mail) {
                return $mail->line('Admin Notes: ' . $this->request->admin_notes);
            })
            ->action('View My Numbers', url('/numbers/my-numbers'))
            ->line('You can now use this number in your campaigns immediately!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'number_request_approved',
            'request_id' => $this->request->id,
            'phone_number' => $this->request->phoneNumber->number,
            'message' => 'Your request for ' . $this->request->phoneNumber->formatted_number . ' has been approved!',
        ];
    }
}
