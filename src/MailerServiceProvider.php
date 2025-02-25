<?php

namespace Twenty20\Mailer;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Twenty20\Mailer\Commands\InstallCommand;

class MailerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('mailer')
            ->hasConfigFile()
            ->hasRoutes('web')
            ->hasMigration('create_mailer_table')
            ->hasCommand(InstallCommand::class);
    }

    /**
     * Register bindings or anything else your package needs.
     */
    public function packageRegistered()
    {
        // Bind or singleton your Mailer class into the container:
        $this->app->singleton(Mailer::class, function ($app) {
            // The config array from config('mailer')
            return new Mailer($app['config']->get('mailer', []));
        });
    }
}
