<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Generates the VAPID key pair that identifies TRYBE to browser push services.
 *
 *   php artisan trybe:vapid
 *
 * You run this ONCE. Copy the two lines it prints into your .env file.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'trybe:vapid';

    protected $description = 'Generate VAPID keys for web push notifications';

    public function handle(): int
    {
        if (! class_exists(VAPID::class)) {
            $this->error('The web-push package is not installed. Run:');
            $this->line('  composer require minishlink/web-push');
            return self::FAILURE;
        }

        $keys = VAPID::createVapidKeys();

        $this->newLine();
        $this->info('VAPID keys generated. Add these three lines to your .env file:');
        $this->newLine();
        $this->line('VAPID_SUBJECT=mailto:admin@trybe.test');
        $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        $this->newLine();
        $this->warn('Then run: php artisan config:clear');
        $this->warn('Never commit the private key to GitHub.');

        return self::SUCCESS;
    }
}
