<?php

declare(strict_types=1);

namespace Osmium\Services\Mailjet\Models;

/**
 * Adapts Mailjet to core's mail.providers hook (see ServiceHooks /
 * MailerFactory).
 */
class MailjetMailProvider
{
    public const ID = 'mailjet';

    /**
     * mail.providers - advertise Mailjet and whether it can send now.
     */
    public static function provider(array $payload): array
    {
        return [
            'id' => self::ID,
            'label' => 'Mailjet',
            'ready' => MailjetConfig::isReady(),
            'settingsRoute' => 'settings/mailjet/',
            'mailer' => MailjetMailer::class,
        ];
    }
}
