<?php

namespace Twenty20\Mailer;

use Aws\Sdk as AwsSdk;
use Aws\Ses\SesClient;
use Illuminate\Support\Facades\Http;
use SendGrid\Mail\Mail as SendGridMail;

class Mailer
{
    /**
     * The name of the configured provider, e.g.
     * 'sendgrid', 'amazon_ses', 'mailchimp', 'mailgun'.
     */
    protected string $provider;

    protected array $providerConfig;

    protected ?SesClient $sesClient;

    /**
     * Constructor
     */
    public function __construct(array $config, ?SesClient $sesClient = null)
    {
        $this->provider = $config['provider'] ?? 'sendgrid';
        $this->providerConfig = $config['providers'][$this->provider] ?? [];
        $this->sesClient = $sesClient;
    }

    /**
     * Send an email in a provider-agnostic way, returning the response.
     *
     * @param  string  $to  Recipient email
     * @param  string  $from  Sender email
     * @param  string  $subject  Subject line
     * @param  string  $body  Plain text or HTML message
     * @return mixed Typically some array or object with the response data
     */
    public function sendMail(string $to, string $from, string $subject, string $body)
    {
        return match ($this->provider) {
            'sendgrid' => $this->sendViaSendGrid($to, $from, $subject, $body),
            'amazon_ses' => $this->sendViaAmazonSes($to, $from, $subject, $body),
            'mailchimp' => $this->sendViaMailchimp($to, $from, $subject, $body),
            'mailgun' => $this->sendViaMailgun($to, $from, $subject, $body),
            default => throw new \RuntimeException("Unsupported provider [{$this->provider}]."),
        };
    }

    /**
     * Send an email via SendGrid using either the official SDK or a raw HTTP request.
     */
    protected function sendViaSendGrid(string $to, string $from, string $subject, string $body)
    {
        try {
            $email = new SendGridMail;
            $email->setFrom($from);
            $email->setSubject($subject);
            $email->addTo($to);

            // If you want HTML, you can add text/plain AND text/html
            $email->addContent('text/plain', strip_tags($body));
            $email->addContent('text/html', $body);

            // Official SendGrid client
            // $sg = new \SendGrid($this->providerConfig['api_key']);
            // $response = $sg->send($email);
            // return $response;

            $response = Http::withToken($this->providerConfig['api_key'])
                ->post($this->providerConfig['api_url'] ?? 'https://api.sendgrid.com/v3/mail/send', $email->jsonSerialize());

            return $response->json();
        } catch (\Exception $e) {
            return throw new \RuntimeException("Error sending email via SendGrid: {$e->getMessage()}");
        }
    }

    /**
     * Send an email via Amazon SES using AWS SDK for PHP.
     */
    protected function sendViaAmazonSes(string $to, string $from, string $subject, string $body)
    {
        try {
            if (! $this->sesClient) {
                $sdk = new AwsSdk([
                    'region' => $this->providerConfig['region'] ?? 'us-east-1',
                    'version' => 'latest',
                    'credentials' => [
                        'key' => $this->providerConfig['api_key'] ?? '',
                        'secret' => $this->providerConfig['api_secret'] ?? '',
                    ],
                ]);

                $this->sesClient = $sdk->createSes();
            }

            $message = [
                'Source' => $from,
                'Destination' => [
                    'ToAddresses' => [$to],
                ],
                'Message' => [
                    'Subject' => ['Data' => $subject],
                    'Body' => [
                        'Text' => ['Data' => strip_tags($body)],
                        'Html' => ['Data' => $body],
                    ],
                ],
            ];

            return $this->sesClient->sendEmail($message)->toArray();
        } catch (\Exception $e) {
            return throw new \RuntimeException("Error sending email via Amazon SES: {$e->getMessage()}");
        }
    }

    /**
     * Send an email via Mailchimp.
     */
    protected function sendViaMailchimp(string $to, string $from, string $subject, string $body)
    {
        try {
            $apiKey = $this->providerConfig['api_key'] ?? '';
            $apiUrl = $this->providerConfig['api_url'] ?? 'https://<REGION>.api.mailchimp.com/3.0/messages/send';

            // If your API key is "us1-XXXX", the 'us1' might define the region, etc.

            $payload = [
                'from_email' => $from,
                'to_email' => $to,
                'subject' => $subject,
                'content' => $body,
            ];

            // Some Mailchimp endpoints might require Basic Auth or token.
            $response = Http::withToken($apiKey)->post($apiUrl, $payload);

            return $response->json();
        } catch (\Exception $e) {
            return throw new \RuntimeException("Error sending email via Mailchimp: {$e->getMessage()}");
        }
    }

    /**
     * Send an email via Mailgun using either the official library or raw HTTP.
     */
    protected function sendViaMailgun(string $to, string $from, string $subject, string $body)
    {
        try {
            $apiKey = $this->providerConfig['api_key'] ?? '';
            $apiBaseUrl = $this->providerConfig['api_base_url'] ?? 'https://api.mailgun.net/v3';

            $url = "{$apiBaseUrl}/messages";

            $response = Http::withBasicAuth('api', $apiKey)
                ->asForm()
                ->post($url, [
                    'from' => $from,
                    'to' => $to,
                    'subject' => $subject,
                    'text' => strip_tags($body),
                    'html' => $body,
                ]);

            return $response->json();
        } catch (\Exception $e) {
            return throw new \RuntimeException("Error sending email via Mailgun: {$e->getMessage()}");
        }
    }
}
