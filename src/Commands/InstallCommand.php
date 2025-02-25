<?php

namespace Twenty20\Mailer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mailer:install';

    /**
     * The console command description.
     */
    protected $description = 'Install and configure the desired mail service provider.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $provider = $this->choice(
            'Which provider would you like to use?',
            ['SendGrid', 'Amazon SES', 'Mailchimp', 'Mailgun'],
            0
        );

        $this->info("Installing provider: {$provider}");

        // Composer require
        switch (strtolower($provider)) {
            case 'sendgrid':
                $this->runComposerRequire('sendgrid/sendgrid');
                break;
            case 'amazon ses':
                $this->runComposerRequire('aws/aws-sdk-php');
                break;
            case 'mailchimp':
                $this->runComposerRequire('mailchimp/transactional');
                break;
            case 'mailgun':
                $this->runComposerRequire('mailgun/mailgun-php');
                break;
            default:
                $this->warn("Unknown provider [{$provider}]. Defaulting to SendGrid.");
                $provider = 'SendGrid';
                $this->runComposerRequire('sendgrid/sendgrid');
                break;
        }

        // Publish config and migrations
        $this->call('vendor:publish', [
            '--provider' => 'Twenty20\\Mailer\\MailerServiceProvider',
        ]);

        // Update config/mailer.php with chosen provider
        $this->updateMailerConfig($provider);

        // Update .env
        $this->updateEnv($provider);

        // Run migrations
        $this->call('migrate');

        $this->info('Installation complete!');
    }

    /**
     * Run composer require for a given package.
     */
    protected function runComposerRequire($package)
    {
        $command = 'composer require '.$package;
        $this->info("Running: {$command}");
        passthru($command);
    }

    /**
     * Update config file with chosen provider.
     */
    protected function updateMailerConfig(string $provider)
    {
        $configFile = config_path('mailer.php');

        if (! File::exists($configFile)) {
            $this->warn("Config file not found at [{$configFile}]. Skipping update.");

            return;
        }

        $contents = File::get($configFile);
        $pattern = "/('provider' => env\('MAILER_PROVIDER', ')[^']+('\))/";
        $replacement = "'provider' => env('MAILER_PROVIDER', '".strtolower($provider)."')";

        $newContents = preg_replace($pattern, $replacement, $contents);

        File::put($configFile, $newContents);
        $this->info("Updated config/mailer.php to provider={$provider}");
    }

    /**
     * Update .env file with placeholders for the chosen provider.
     */
    protected function updateEnv(string $provider)
    {
        $provider = strtolower($provider);

        $envFilePath = base_path('.env');
        $exampleFilePath = base_path('.env.example');

        if (! File::exists($envFilePath)) {
            $this->warn('.env file not found. Skipping env update.');
        } else {
            $envContents = File::get($envFilePath);
            $envContents = $this->appendProviderKeys($provider, $envContents);
            File::put($envFilePath, $envContents);
            $this->info("Updated .env with placeholders for {$provider}.");
        }

        if (File::exists($exampleFilePath)) {
            $exampleContents = File::get($exampleFilePath);
            $exampleContents = $this->appendProviderKeys($provider, $exampleContents);
            File::put($exampleFilePath, $exampleContents);
            $this->info("Updated .env.example with placeholders for {$provider}.");
        }
    }

    /**
     * Helper to append relevant lines to .env or .env.example for each provider.
     */
    protected function appendProviderKeys($provider, $envContents)
    {
        $placeholders = [];

        switch ($provider) {
            case 'sendgrid':
                $placeholders = [
                    'MAILER_PROVIDER=sendgrid',
                    'SENDGRID_API_KEY=',
                    'SENDGRID_API_URL=https://api.sendgrid.com/v3/mail/send',
                ];
                break;

            case 'amazon ses':
                $placeholders = [
                    'MAILER_PROVIDER=amazon_ses',
                    'AWS_ACCESS_KEY_ID=',
                    'AWS_SECRET_ACCESS_KEY=',
                    'AWS_REGION=us-east-1',
                ];
                break;

            case 'mailchimp':
                $placeholders = [
                    'MAILER_PROVIDER=mailchimp',
                    'MAILCHIMP_API_KEY=',
                    'MAILCHIMP_API_URL=https://<REGION>.api.mailchimp.com/3.0/messages/send',
                ];
                break;

            case 'mailgun':
                $placeholders = [
                    'MAILER_PROVIDER=mailgun',
                    'MAILGUN_API_KEY=',
                    'MAILGUN_API_BASE_URL=https://api.mailgun.net/v3',
                ];
                break;
        }

        // If none, default to SendGrid
        if (empty($placeholders)) {
            $placeholders = [
                'MAILER_PROVIDER=sendgrid',
                'SENDGRID_API_KEY=',
                'SENDGRID_API_URL=https://api.sendgrid.com/v3/mail/send',
            ];
        }

        foreach ($placeholders as $line) {
            if (! Str::contains($envContents, $line)) {
                $envContents .= PHP_EOL.$line;
            }
        }

        return $envContents;
    }
}
