<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            // Cambia esta URL por la URL real de tu Frontend en Vue
            // El frontend recibirá el token y el email por URL
            return config('app.frontend_url')."/reset-password/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
