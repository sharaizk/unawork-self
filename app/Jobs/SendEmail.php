<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $message;
    protected $subject;
    protected $settings;

    /**
     * Create a new job instance.
     */
    public function __construct(string $email, string $message, string $subject, array $settings)
    {
        $this->email = $email;
        $this->message = $message;
        $this->subject = $subject;
        $this->settings = $settings;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        if (!$this->validateSettings($this->settings)) {
            \Log::error('SMTP settings are invalid or missing.');
            return;
        } else {
            $this->applyMailSettings($this->settings);

            try {
                Mail::raw($this->message, function ($m) {
                    $m->to($this->email)->subject($this->subject);
                });
            } catch (\Exception $e) {
                \Log::error('Error sending email: ' . $e->getMessage());
            }
        }
    }

    protected function validateSettings($settings)
    {
        $requiredKeys = ['mail_driver', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', 'mail_from_address', 'mail_from_name'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $settings) || empty($settings[$key])) {
                return false;
            }
        }
        return true;
    }

    protected function applyMailSettings($settings)
    {
        config([
            'mail.driver' => $settings['mail_driver'],
            'mail.host' => $settings['mail_host'],
            'mail.port' => $settings['mail_port'],
            'mail.encryption' => $settings['mail_encryption'],
            'mail.username' => $settings['mail_username'],
            'mail.password' => $settings['mail_password'],
            'mail.from.address' => $settings['mail_from_address'],
            'mail.from.name' => $settings['mail_from_name'],
        ]);
    }
}