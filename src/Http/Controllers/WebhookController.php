<?php

namespace Twenty20\Mailer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Twenty20\Mailer\Models\MailerEvent;

class WebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $provider = config('mailer.provider', 'sendgrid');
        $payload = $request->all();

        match (strtolower($provider)) {
            'sendgrid' => $this->handleSendGridEvents($payload),
            'amazon_ses' => $this->handleSesEvents($payload),
            'mailchimp' => $this->handleMailchimpEvents($payload),
            'mailgun' => $this->handleMailgunEvents($payload),
            default => Log::warning("Unknown provider: {$provider}"),
        };

        return response('Webhook processed', 200);
    }

    protected function handleSendGridEvents(array $payload): void
    {
        // Example structure:
        // [
        //   {
        //     "email": "john@example.com",
        //     "event": "bounce",
        //     "timestamp": 1677425657,
        //     "reason": "Mailbox full"
        //   },
        //   ...
        // ]
        foreach ($payload as $event) {
            MailerEvent::create([
                'provider' => 'sendgrid',
                'email' => $event['email'] ?? null,
                'event_type' => $event['event'] ?? null,
                'reason' => $event['reason'] ?? null,
                'message_id' => $event['sg_message_id'] ?? null,
                'event_at' => isset($event['timestamp'])
                    ? date('Y-m-d H:i:s', $event['timestamp'])
                    : now(),
            ]);
        }
    }

    protected function handleSesEvents(array $payload): void
    {
        // Amazon SES typically sends a JSON in the "Message" field.
        $rawMessage = $payload['Message'] ?? '{}';
        $messageData = json_decode($rawMessage, true);

        if (isset($messageData['notificationType'])) {
            $type = strtolower($messageData['notificationType']);
            $bounce = $messageData['bounce'] ?? [];
            $bouncedRecipients = $bounce['bouncedRecipients'] ?? [];

            foreach ($bouncedRecipients as $recipient) {
                MailerEvent::create([
                    'provider' => 'amazon_ses',
                    'email' => $recipient['emailAddress'] ?? null,
                    'event_type' => $type,
                    'reason' => $bounce['bounceType'] ?? null,
                    'message_id' => $messageData['mail']['messageId'] ?? null,
                    'event_at' => now(),
                ]);
            }
        }
    }

    protected function handleMailchimpEvents(array $payload): void
    {
        // Example:
        // {
        //   "type": "unsubscribe",
        //   "fired_at": "2023-09-01 12:34:56",
        //   "data": {
        //     "email": "john@example.com",
        //     "reason": "manual unsubscribe",
        //     "id": "someInternalId"
        //   }
        // }
        $eventType = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];

        MailerEvent::create([
            'provider' => 'mailchimp',
            'email' => $data['email'] ?? null,
            'event_type' => $eventType,
            'reason' => $data['reason'] ?? null,
            'message_id' => $data['id'] ?? null,
            'event_at' => isset($payload['fired_at'])
                ? date('Y-m-d H:i:s', strtotime($payload['fired_at']))
                : now(),
        ]);
    }

    protected function handleMailgunEvents(array $payload): void
    {
        // Example:
        // {
        //   "event": "failed",
        //   "recipient": "someone@example.com",
        //   "reason": "bounce",
        //   "Message-Id": "<someMessageId@domain.com>",
        //   "timestamp": 1690852009
        // }
        MailerEvent::create([
            'provider' => 'mailgun',
            'email' => $payload['recipient'] ?? null,
            'event_type' => $payload['event'] ?? null,
            'reason' => $payload['reason'] ?? null,
            'message_id' => $payload['Message-Id'] ?? null,
            'event_at' => isset($payload['timestamp'])
                ? date('Y-m-d H:i:s', $payload['timestamp'])
                : now(),
        ]);
    }
}
