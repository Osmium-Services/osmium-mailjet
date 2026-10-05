<?php

declare(strict_types=1);

namespace Osmium\Services\Mailjet\Models;

use Osmium\Core\Library\MailerInterface;

/**
 * Sends mail via Mailjet's Send API v3.1 (JSON POST with HTTP basic auth: API
 * key as the username, secret key as the password). Plain curl, so there is
 * no SDK to install.
 *
 * Built by MailerFactory with the site config, which supplies the shared
 * From address and name; the keys come from this service's own config.
 * Mailjet only sends from a verified sender or domain, so the From address on
 * the Email settings page must be one.
 */
class MailjetMailer implements MailerInterface
{
    private const SEND_URL = 'https://api.mailjet.com/v3.1/send';

    public function __construct(private object $config) {}

    /**
     * @param array<int, array{email: string, name?: string}> $recipients
     * @return array{success: bool, error?: string}
     */
    public function send(array $recipients, string $subject, string $htmlBody): array
    {
        try {
            $this->postMessage($recipients, $subject, $htmlBody);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array<int, array{email: string, name?: string}> $recipients
     */
    private function postMessage(array $recipients, string $subject, string $htmlBody): void
    {
        $notReady = !MailjetConfig::isReady();
        if ($notReady) throw new \RuntimeException('Mailjet is not configured: set the API key and secret key on the Mailjet settings page.');

        $mailjet = MailjetConfig::get();

        $email = $this->config->email;
        $from = ['Email' => $email->fromAddress];
        $fromName = $email->fromName ?? '';
        if ($fromName !== '') $from['Name'] = $fromName;

        $to = \array_map(
            function (array $recipient): array {
                $address = ['Email' => $recipient['email']];
                if (isset($recipient['name'])) $address['Name'] = $recipient['name'];
                return $address;
            },
            $recipients,
        );

        $ch = \curl_init(self::SEND_URL);
        \curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => \json_encode(['Messages' => [[
                'From' => $from,
                'To' => $to,
                'Subject' => $subject,
                'HTMLPart' => $htmlBody,
            ]]]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERPWD => $mailjet->apiKey . ':' . $mailjet->secretKey,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = \curl_exec($ch);
        $curlError = \curl_error($ch);
        $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($curlError) throw new \RuntimeException("Mailjet send curl error: {$curlError}");

        $decoded = \json_decode(json: (string) $response, associative: true);

        $httpFailed = $httpCode < 200 || $httpCode >= 300;
        if ($httpFailed) throw new \RuntimeException("Mailjet send failed ({$httpCode}): " . $this->errorMessage($decoded, (string) $response, $httpCode));

        $messageStatus = $decoded['Messages'][0]['Status'] ?? 'success'; // Mailjet can answer 2xx while rejecting the message itself
        if ($messageStatus !== 'success') {
            throw new \RuntimeException("Mailjet rejected the message ({$httpCode}): " . $this->errorMessage($decoded['Messages'][0] ?? [], (string) $response, $httpCode));
        }
    }

    /**
     * Mailjet reports a request failure as ErrorMessage, and a rejected
     * message as an Errors list with an ErrorMessage per entry.
     */
    private function errorMessage(?array $decoded, string $raw, int $httpCode): string
    {
        $decoded ??= [];

        if (isset($decoded['ErrorMessage'])) return (string) $decoded['ErrorMessage'];

        $messages = \array_filter(\array_map(fn($error) => $error['ErrorMessage'] ?? null, $decoded['Errors'] ?? []));
        if ($messages) return \implode('; ', $messages);

        return $raw !== '' ? $raw : "HTTP {$httpCode}";
    }
}
