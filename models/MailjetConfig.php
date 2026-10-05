<?php

declare(strict_types=1);

namespace Osmium\Services\Mailjet\Models;

/**
 * Mailjet configuration.
 *
 * File-based config (app/config/services/mailjet.json.php), matching the
 * Xero/Stripe/PayPal/Turnstile convention. The From address and name are not
 * kept here: they stay on the core Email settings page, shared by every mail
 * provider.
 */
class MailjetConfig
{
    private static ?object $config = null;
    private static string $configPath = 'app/config/services/mailjet.json.php';

    public static function get(): object
    {
        $configLoaded = self::$config !== null;
        if ($configLoaded) return self::$config;

        $configFile = self::$configPath;

        $configExists = \file_exists($configFile);
        if (!$configExists) {
            self::$config = self::defaults();
            return self::$config;
        }

        $content = \file_get_contents($configFile);
        $jsonStart = \strpos(haystack: $content, needle: '{');

        $noJsonFound = $jsonStart === false;
        if ($noJsonFound) {
            self::$config = self::defaults();
            return self::$config;
        }

        $json = \substr(string: $content, offset: $jsonStart);
        $decoded = \json_decode($json);

        self::$config = (object) \array_merge((array) self::defaults(), (array) ($decoded->mailjet ?? []));

        return self::$config;
    }

    public static function clearCache(): void
    {
        self::$config = null;
    }

    /**
     * Can mail be sent right now - both the API key and the secret key are set.
     */
    public static function isReady(): bool
    {
        $config = self::get();

        return ($config->apiKey ?? '') !== '' && ($config->secretKey ?? '') !== '';
    }

    private static function defaults(): object
    {
        return (object) [
            'apiKey' => '',
            'secretKey' => '',
        ];
    }
}
