<?php

namespace Twenty20\Mailer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

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

        $provider = select(
            label: 'Which provider would you like to use?',
            options: ['SendGrid', 'Amazon SES'],
            default: 'SendGrid',
        );

        info("Installing provider: {$provider}");

        // Composer require
        switch (strtolower($provider)) {
            case 'sendgrid':
                $this->runComposerRequire('sendgrid/sendgrid');
                break;
            case 'amazon ses':
                $this->runComposerRequire('aws/aws-sdk-php');
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

        info('Installation completed');
        info('Set your API keys in .env and start sending emails!');
    }

    /**
     * Run composer require for a given package.
     */
    protected function runComposerRequire($package)
    {
        $command = 'composer require '.$package;
        info("Running: {$command}");
        passthru($command);
    }

    /**
     * Update config file with chosen provider.
     */
    protected function updateMailerConfig(string $provider)
    {
        $configFile = config_path('mailer.php');

        if (! File::exists($configFile)) {
            warning("Config file not found at [{$configFile}]. Skipping update.");

            return;
        }

        $contents = File::get($configFile);
        $pattern = "/('provider' => env\('MAILER_PROVIDER', ')[^']+('\))/";
        $replacement = "'provider' => env('MAILER_PROVIDER', '".strtolower($provider)."')";

        $newContents = preg_replace($pattern, $replacement, $contents);

        File::put($configFile, $newContents);
        info("Updated config/mailer.php to provider {$provider}");
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
            warning('.env file not found. Skipping env update.');
        } else {
            $envContents = File::get($envFilePath);
            $envContents = $this->appendProviderKeys($provider, $envContents);
            File::put($envFilePath, $envContents);
            info("Updated .env with placeholders for {$provider}.");
        }

        if (File::exists($exampleFilePath)) {
            $exampleContents = File::get($exampleFilePath);
            $exampleContents = $this->appendProviderKeys($provider, $exampleContents);
            File::put($exampleFilePath, $exampleContents);
            info("Updated .env.example with placeholders for {$provider}.");
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
