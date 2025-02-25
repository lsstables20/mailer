<?php

namespace Twenty20\Mailer;

use Illuminate\Mail\MailManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Twenty20\Mailer\Commands\InstallCommand;
use Twenty20\Mailer\Transport\Twenty20TransportFactory;

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
        $this->app->singleton(Mailer::class, function ($app) {
            return new Mailer($app['config']->get('twentytwenty-mailer', []));
        });

        // Register custom mail transport inside Laravel
        $this->app->extend('mail.manager', function (MailManager $mailManager, $app) {
            $mailManager->extend('twenty20', function () {
                return (new Twenty20TransportFactory)->create(new \Symfony\Component\Mailer\Transport\Dsn);
            });

            return $mailManager;
        });
    }
}
