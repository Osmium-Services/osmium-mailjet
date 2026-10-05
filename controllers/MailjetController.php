<?php

declare(strict_types=1);

namespace Osmium\Services\Mailjet\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\Mailjet\Models\MailjetConfig;

/**
 * Mailjet settings controller - full-page form POST/redirect, same shape as
 * TurnstileController.
 *
 * Routes:
 *   - index() → /admin/settings/mailjet/  (GET shows the form, POST saves it)
 */
class MailjetController extends AdminController
{
    private const CONFIG_FILE_PATH = 'app/config/services/mailjet.json.php';

    public function index(): void
    {
        $isPost = $this->isPost();
        if ($isPost) $this->handleSubmit();

        $this->data['admin']['config']['mailjet'] = (array) MailjetConfig::get();
        $this->data['admin']['settingsSaved'] = $_SESSION['mailjet_settings_saved'] ?? false;
        $this->data['admin']['settingsError'] = $_SESSION['mailjet_settings_error'] ?? false;
        unset($_SESSION['mailjet_settings_saved'], $_SESSION['mailjet_settings_error']);

        $this->setView('mailjet/index.phtml');
    }

    private function handleSubmit(): void
    {
        $csrfValid = $this->admin->auth->validateCsrf();
        if (!$csrfValid) {
            $_SESSION['mailjet_settings_error'] = 'Invalid form submission. Please try again.';
            $this->redirect('settings/mailjet/');
        }

        $apiKey = \trim($_POST['api_key'] ?? '');

        $postedSecretKey = \trim($_POST['secret_key'] ?? '');
        $secretKey = $postedSecretKey === '' ? (string) (MailjetConfig::get()->secretKey ?? '') : $postedSecretKey; // Blank keeps the stored secret

        $this->saveConfig($apiKey, $secretKey);

        $this->admin->model->changelog->log(
            description: 'Updated Mailjet settings',
            recordType: 'settings',
        );

        MailjetConfig::clearCache();

        $_SESSION['mailjet_settings_saved'] = true;
        $this->redirect('settings/mailjet/');
    }

    private function saveConfig(string $apiKey, string $secretKey): void
    {
        $configExists = \file_exists(self::CONFIG_FILE_PATH);
        if (!$configExists) $this->ensureConfigDirectoryExists();

        $newJson = \json_encode(
            value: ['mailjet' => [
                'apiKey' => $apiKey,
                'secretKey' => $secretKey,
            ]],
            flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        \file_put_contents(self::CONFIG_FILE_PATH, "<?php exit(); ?>\n" . $newJson . "\n");
    }

    private function ensureConfigDirectoryExists(): void
    {
        $dir = \dirname(self::CONFIG_FILE_PATH);
        $alreadyExists = \is_dir($dir);
        if (!$alreadyExists) \mkdir(directory: $dir, permissions: 0755, recursive: true);
    }

    private function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}
