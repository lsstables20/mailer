<?php

use Aws\Ses\SesClient;
use Illuminate\Support\Facades\Http;
use Twenty20\Mailer\Mailer;

it('sends mail via SendGrid', function () {
    config([
        'mailer.provider' => 'sendgrid',
        'mailer.providers.sendgrid.api_key' => 'test-sendgrid-key',
        'mailer.providers.sendgrid.api_url' => 'https://api.sendgrid.com/v3/mail/send',
    ]);

    Http::fake([
        'https://api.sendgrid.com/v3/mail/send' => Http::response(['status' => 'OK'], 200),
    ]);

    $config = config('mailer', []);
    $mailer = new Mailer($config);

    $result = $mailer->sendMail(
        'john@example.com',
        'from@example.com',
        'Test Subject',
        'Hello world'
    );

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.sendgrid.com/v3/mail/send'
            && $request->hasHeader('Authorization', 'Bearer test-sendgrid-key');
    });

    expect($result)->toMatchArray(['status' => 'OK']);
});

it('sends mail via Mailgun', function () {
    config([
        'mailer.provider' => 'mailgun',
        'mailer.providers.mailgun.api_key' => 'test-mailgun-key',
        'mailer.providers.mailgun.api_base_url' => 'https://api.mailgun.net/v3',
    ]);

    Http::fake([
        'https://api.mailgun.net/v3/messages' => Http::response(['id' => '123', 'message' => 'Queued'], 200),
    ]);

    $mailer = app(Mailer::class);
    $result = $mailer->sendMail('john@example.com', 'no-reply@myapp.com', 'Hello MG', 'MG Body');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.mailgun.net/v3/messages'
            && $request->hasHeader('Authorization')
            && $request->header('Authorization')[0] === 'Basic '.base64_encode('api:test-mailgun-key');
    });

    expect($result)->toMatchArray(['id' => '123', 'message' => 'Queued']);
});

it('sends mail via Amazon SES', function () {
    $mockSesClient = Mockery::mock(SesClient::class);

    $mockResponse = Mockery::mock();
    $mockResponse->shouldReceive('toArray')->andReturn(['MessageId' => 'test-message-id']);

    $mockSesClient->shouldReceive('sendEmail')
        ->once()
        ->with(Mockery::on(function ($arg) {
            expect($arg)->toHaveKeys(['Source', 'Destination', 'Message']);
            expect($arg['Destination']['ToAddresses'])->toBe(['john@example.com']);

            return true;
        }))
        ->andReturn($mockResponse);

    $config = [
        'provider' => 'amazon_ses',
        'providers' => [
            'amazon_ses' => [
                'region' => 'us-east-1',
                'api_key' => 'fake-key',
                'api_secret' => 'fake-secret',
            ],
        ],
    ];

    $mailer = new Mailer($config, $mockSesClient);

    $result = $mailer->sendMail(
        to: 'john@example.com',
        from: 'from@example.com',
        subject: 'Hello SES',
        body: '<p>Test email</p>'
    );

    expect($result)->toHaveKey('MessageId', 'test-message-id');
});
