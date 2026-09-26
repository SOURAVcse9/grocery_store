<?php
/**
 * ==============================================================================
 * GroCo Modern Email Service & Notification Pipeline
 * ==============================================================================
 * Multi-provider transactional email service supporting SMTP, Resend, Brevo,
 * Postmark REST APIs, and local testing logs with responsive HTML templates.
 * ==============================================================================
 */

declare(strict_types=1);

class EmailService
{
    /**
     * Send a transactional email message
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject line
     * @param string $htmlContent HTML formatted message body
     * @param string|null $textContent Optional plain-text fallback
     * @return bool True if accepted for delivery
     */
    public static function send(string $to, string $subject, string $htmlContent, ?string $textContent = null): bool
    {
        $provider = strtolower((string)(getenv('MAIL_DRIVER') ?: 'smtp'));
        $fromEmail = getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@groco.site.je';
        $fromName = getenv('MAIL_FROM_NAME') ?: 'GroCo Grocery Store';

        // 1. Check for modern REST API providers
        $resendApiKey = getenv('RESEND_API_KEY');
        if ($resendApiKey || $provider === 'resend') {
            return self::sendViaResend((string)$resendApiKey, $fromEmail, $fromName, $to, $subject, $htmlContent, $textContent);
        }

        $brevoApiKey = getenv('BREVO_API_KEY');
        if ($brevoApiKey || $provider === 'brevo') {
            return self::sendViaBrevo((string)$brevoApiKey, $fromEmail, $fromName, $to, $subject, $htmlContent);
        }

        $postmarkApiKey = getenv('POSTMARK_API_KEY');
        if ($postmarkApiKey || $provider === 'postmark') {
            return self::sendViaPostmark((string)$postmarkApiKey, $fromEmail, $to, $subject, $htmlContent, $textContent);
        }

        // 2. Default to existing SMTP / PHPMailer infrastructure
        if (function_exists('send_mail')) {
            try {
                return send_mail($to, $subject, $htmlContent);
            } catch (Throwable $e) {
                error_log("EmailService SMTP Error: " . $e->getMessage());
            }
        }

        // 3. Fallback: Log to storage/logs/mail.log for test/dev environments
        $logDir = defined('STORAGE_PATH') ? STORAGE_PATH . '/logs' : dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $entry = sprintf(
            "[%s] TO: %s | SUBJECT: %s | BODY: %s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            strip_tags($htmlContent)
        );
        @file_put_contents($logDir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);

        return true;
    }

    private static function sendViaResend(
        string $apiKey,
        string $fromEmail,
        string $fromName,
        string $to,
        string $subject,
        string $html,
        ?string $text
    ): bool {
        if (empty($apiKey)) return false;

        $ch = curl_init('https://api.resend.com/emails');
        $payload = [
            'from'    => "{$fromName} <{$fromEmail}>",
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
        ];
        if ($text) {
            $payload['text'] = $text;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code >= 200 && $code < 300);
    }

    private static function sendViaBrevo(
        string $apiKey,
        string $fromEmail,
        string $fromName,
        string $to,
        string $subject,
        string $html
    ): bool {
        if (empty($apiKey)) return false;

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        $payload = [
            'sender'      => ['name' => $fromName, 'email' => $fromEmail],
            'to'          => [['email' => $to]],
            'subject'     => $subject,
            'htmlContent' => $html
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'api-key: ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code >= 200 && $code < 300);
    }

    private static function sendViaPostmark(
        string $apiKey,
        string $fromEmail,
        string $to,
        string $subject,
        string $html,
        ?string $text
    ): bool {
        if (empty($apiKey)) return false;

        $ch = curl_init('https://api.postmarkapp.com/email');
        $payload = [
            'From'     => $fromEmail,
            'To'       => $to,
            'Subject'  => $subject,
            'HtmlBody' => $html,
            'TextBody' => $text ?: strip_tags($html)
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'X-Postmark-Server-Token: ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code >= 200 && $code < 300);
    }

    /**
     * Render standard responsive transactional email template
     */
    public static function renderTemplate(string $title, string $greeting, string $bodyHtml, ?string $actionUrl = null, ?string $actionText = null): string
    {
        $siteName = 'GroCo Grocery Store';
        $actionButton = '';
        if ($actionUrl && $actionText) {
            $actionButton = '
                <div style="margin: 28px 0; text-align: center;">
                    <a href="' . htmlspecialchars($actionUrl) . '" style="background-color: #2b8a3e; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 700; display: inline-block;">
                        ' . htmlspecialchars($actionText) . '
                    </a>
                </div>';
        }

        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; color: #212529;">
    <div style="max-width: 600px; margin: 30px auto; background: #ffffff; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        <div style="background-color: #2b8a3e; padding: 24px; text-align: center;">
            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 800;">🛒 ' . htmlspecialchars($siteName) . '</h1>
        </div>
        <div style="padding: 32px 28px;">
            <h2 style="margin-top: 0; color: #1a1a1a; font-size: 18px;">' . htmlspecialchars($greeting) . '</h2>
            <div style="line-height: 1.6; font-size: 14px; color: #495057;">
                ' . $bodyHtml . '
            </div>
            ' . $actionButton . '
        </div>
        <div style="background-color: #f1f3f5; padding: 16px 28px; text-align: center; font-size: 12px; color: #868e96; border-top: 1px solid #dee2e6;">
            © ' . date('Y') . ' ' . htmlspecialchars($siteName) . '. All rights reserved.
        </div>
    </div>
</body>
</html>';
    }
}
