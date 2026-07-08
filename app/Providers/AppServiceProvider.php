<?php

namespace App\Providers;

use App\Mail\EmailVerificationMail;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->bootFailedJobLogging();
        $this->bootEmailVerificationUrl();
        $this->bootEmailVerificationTemplate();
    }

    private function bootFailedJobLogging(): void
    {
        Queue::failing(function (JobFailed $event) {
            Log::channel('daily')->error('Queue job failed', [
                'job'       => $event->job->resolveName(),
                'queue'     => $event->job->getQueue(),
                'exception' => $event->exception->getMessage(),
                'trace'     => $event->exception->getTraceAsString(),
            ]);
        });
    }

    private function bootEmailVerificationUrl(): void
    {
        VerifyEmail::createUrlUsing(function ($notifiable) {
            $signedUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
            );

            return $signedUrl;
        });
    }

    private function bootEmailVerificationTemplate(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            /** @var User $notifiable */
            return (new EmailVerificationMail($notifiable, $url))->to($notifiable->email);
        });
    }
}
