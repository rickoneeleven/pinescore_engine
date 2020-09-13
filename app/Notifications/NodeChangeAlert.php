<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NodeChangeAlert extends Notification
{
    use Queueable;
    public $array_passed;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($array_passed)
    {
        $this->array_passed = $array_passed;
        //Logger($this->array_passed);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
        ->subject($this->array_passed['now_state']. " -> ".$this->array_passed['ping_ip_table_row']['note'])
        ->line($this->array_passed['ping_ip_table_row']['note']." is now ".$this->array_passed['now_state'])
        ->action('Open Dashboard', url('/'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
