<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Twenty20\Mailer\Models\MailerEvent;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('handles SendGrid webhooks', function () {
    config(['mailer.provider' => 'sendgrid']);

    $payload = [
        [
            'email' => 'john@example.com',
            'event' => 'bounce',
            'timestamp' => 1693497600,
            'reason' => 'Mailbox full',
            'sg_message_id' => 'abc123',
        ],
        [
            'email' => 'jane@example.com',
            'event' => 'delivered',
            'timestamp' => 1693497700,
            'sg_message_id' => 'xyz789',
        ],
    ];

    postJson('/mailer/webhook', $payload)
        ->assertOk()
        ->assertSee('Webhook processed');

    expect(MailerEvent::count())->toBe(2);

    expect(MailerEvent::where('email', 'john@example.com')->first())
        ->provider->toBe('sendgrid')
        ->event_type->toBe('bounce')
        ->reason->toBe('Mailbox full')
        ->message_id->toBe('abc123');

    expect(MailerEvent::where('email', 'jane@example.com')->first())
        ->event_type->toBe('delivered');
});

it('handles Amazon SES webhooks', function () {
    config(['mailer.provider' => 'amazon_ses']);

    $payload = [
        'Type' => 'Notification',
        'Message' => json_encode([
            'notificationType' => 'Bounce',
            'bounce' => [
                'bounceType' => 'Permanent',
                'bouncedRecipients' => [
                    ['emailAddress' => 'bounced@example.com'],
                ],
            ],
            'mail' => [
                'messageId' => 'uniqueMessageIdHere',
            ],
        ]),
    ];

    postJson('/mailer/webhook', $payload)
        ->assertOk()
        ->assertSee('Webhook processed');

    expect(MailerEvent::count())->toBe(1);
    $record = MailerEvent::first();
    expect($record->provider)->toBe('amazon_ses');
    expect($record->email)->toBe('bounced@example.com');
    expect($record->reason)->toBe('Permanent');
    expect($record->event_type)->toBe('bounce');
    expect($record->message_id)->toBe('uniqueMessageIdHere');
});
