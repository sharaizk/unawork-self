<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Utility;

class SendLoginDetail extends Mailable
{
    use Queueable, SerializesModels;
    public $user;

    /**
     * The password to show, passed explicitly (UNA-012): never read from $user->password, which
     * is the hash. Null omits the password row (e.g. self-registration).
     */
    public ?string $password;

    public function __construct(User $user, #[\SensitiveParameter] ?string $password = null)
    {
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $setting = Utility::getAdminPaymentSettings();
        return $this->markdown('email.login.detail')->subject('Login details - ' . $setting['app_name'] ? $setting['app_name'] : 'Taskly Saas');
    }
}