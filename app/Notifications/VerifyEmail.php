<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
class VerifyEmail extends Notification
{
    use Queueable;
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        return (new MailMessage)
            ->subject('Verify Email Address')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('If you did not create an account, no further action is required.');
    }
    protected function verificationUrl($notifiable)
    {
        $domain = Config::get('tenancy.central_domains')[0] ?? 'obsolio.com';
        $frontendDomain = str_contains($domain, 'localhost') ? "http://{$domain}" : "https://{$domain}";
        // Generate the API verification URL first
        $apiDomain = str_contains($domain, 'localhost') ? "http://api.{$domain}" : "https://api.{$domain}";
        
        $currentRoot = URL::formatRoot('', '');
        URL::forceRootUrl($apiDomain);
        try {
            $apiUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        } finally {
            URL::forceRootUrl($currentRoot);
        }
        // Convert API URL to Frontend URL
        // From: https://api.obsolio.com/api/v1/auth/verify-email/123/hash?expires=...&signature=...
        // To:   https://obsolio.com/verify-email?token=base64(full_api_url)
        $token = base64_encode($apiUrl);
        
        return "{$frontendDomain}/verify-email?token={$token}";
    }
}
