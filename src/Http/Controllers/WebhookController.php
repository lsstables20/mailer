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
            // MailerEvent::create([
            //     'provider' => 'sendgrid',
            //     'email' => $event['email'] ?? null,
            //     'event_type' => $event['event'] ?? null,
            //     'reason' => $event['reason'] ?? null,
            //     'message_id' => $event['sg_message_id'] ?? null,
            //     'event_at' => isset($event['timestamp'])
            //         ? date('Y-m-d H:i:s', $event['timestamp'])
            //         : now(),
            // ]);


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
}
