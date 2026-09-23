<?php
/**
 * ==========================================================================
 * public/mail_test.php — Development SMTP Connectivity & Delivery Diagnostic
 * ==========================================================================
 * Available in development / debug mode or to authenticated administrators.
 * Tests:
 *   - Environment variable resolution & settings table fallback
 *   - Socket connectivity to SMTP host and port
 *   - TLS / STARTTLS crypto negotiation
 *   - AUTH LOGIN credential verification
 *   - Live email dispatch test to custom recipient
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Security: restrict to development mode or authenticated admin
if (!APP_DEBUG && APP_ENV === 'production' && !is_admin()) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style="font-family:sans-serif; text-align:center; padding:50px;"><h2>403 Forbidden</h2><p>SMTP diagnostic test endpoint is disabled in production.</p></body></html>');
}

$pageTitle = 'SMTP Diagnostic Tool — ' . site_name();
$extraStylesheets = ['css/auth.css'];

$config = get_smtp_config();
$testRecipient = trim(input('test_recipient', ''));
$testResult = null;
$diagnosticLogs = [];
$testType = input('action', '');

if (method_is('post') && verify_csrf()) {
    if ($testType === 'test_connection') {
        // Socket connection & handshake only
        $logs = [];
        $log = function(string $msg) use (&$logs) {
            $logs[] = '[' . date('H:i:s') . '] ' . $msg;
        };

        $host = $config['host'];
        $port = $config['port'];
        $enc  = $config['encryption'];
        $user = $config['user'];
        $pass = $config['pass'];

        $socketHost = ($enc === 'ssl') ? "ssl://{$host}" : $host;
        $log("Testing connection to {$socketHost}:{$port}...");

        $context = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client("{$socketHost}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);

        if (!$socket) {
            $log("ERROR: Connection failed ({$errno}): {$errstr}");
            $testResult = ['success' => false, 'message' => "Socket connection failed: {$errstr} (code {$errno})"];
        } else {
            $log("TCP Connection established.");
            $greeting = trim((string) fgets($socket, 512));
            $log("< {$greeting}");

            fwrite($socket, "EHLO localhost\r\n");
            $ehloResp = '';
            while ($line = fgets($socket, 512)) {
                $ehloResp .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            $log("< " . trim($ehloResp));

            if ($enc === 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                $tlsResp = trim((string) fgets($socket, 512));
                $log("< {$tlsResp}");
                if (str_starts_with($tlsResp, '220')) {
                    $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    $log($crypto ? "TLS encryption successfully negotiated." : "Failed to negotiate TLS crypto stream.");
                }
            }

            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            $log("Connection test complete.");
            $testResult = ['success' => true, 'message' => 'SMTP Socket handshake completed successfully!'];
        }
        $diagnosticLogs = $logs;

    } elseif ($testType === 'send_test_email') {
        if (empty($testRecipient) || !filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
            $testResult = ['success' => false, 'message' => 'Please enter a valid recipient email address.'];
        } else {
            $subject = 'GroCo SMTP Diagnostic Test — ' . date('Y-m-d H:i:s');
            $html = "
                <div style='font-family:sans-serif; max-width:600px; margin:0 auto; padding:20px; border:1px solid #e2e8f0; border-radius:8px;'>
                    <h2 style='color:#0ca678;'>SMTP Diagnostic Test Succeeded!</h2>
                    <p>This test email confirms that your GroCo Grocery Store SMTP delivery engine is configured correctly and operational.</p>
                    <p><strong>Host:</strong> {$config['host']}<br>
                    <strong>Port:</strong> {$config['port']}<br>
                    <strong>Encryption:</strong> {$config['encryption']}<br>
                    <strong>Sender:</strong> {$config['from_email']}</p>
                    <hr style='border:none; border-top:1px solid #eeeeee;'>
                    <small style='color:#888;'>Timestamp: " . date('r') . "</small>
                </div>
            ";
            $text = "SMTP Diagnostic Test Succeeded!\n\nHost: {$config['host']}\nPort: {$config['port']}\nTimestamp: " . date('r');

            $ok = send_smtp_email($testRecipient, $subject, $html, $text, [], $diagnosticLogs);
            if ($ok) {
                $testResult = ['success' => true, 'message' => "Test email successfully sent to {$testRecipient}!"];
            } else {
                $testResult = ['success' => false, 'message' => "Failed to deliver test email to {$testRecipient}."];
            }
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="container" style="max-width: 900px; margin: 40px auto; padding: 0 20px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; color: var(--color-text); margin: 0 0 6px 0;">
                <i class="fas fa-envelope-circle-check" style="color: var(--color-primary); margin-right: 8px;"></i>
                SMTP Server Diagnostic Tool
            </h1>
            <p style="font-size: 13px; color: var(--color-text-muted); margin: 0;">
                Verify email server connectivity, cryptographic handshake, and live message dispatch.
            </p>
        </div>
        <div>
            <span style="font-size: 11px; font-weight: 700; background: <?= APP_DEBUG ? 'rgba(12, 166, 120, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= APP_DEBUG ? '#0ca678' : '#ef4444' ?>; padding: 4px 10px; border-radius: 20px;">
                ENV: <?= strtoupper(APP_ENV) ?> &bull; DEBUG: <?= APP_DEBUG ? 'ON' : 'OFF' ?>
            </span>
        </div>
    </div>

    <!-- Configuration Overview Card -->
    <div class="dashboard-card" style="margin-bottom: 24px;">
        <h3 class="dashboard-card-title">Resolved SMTP Configuration</h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 12px;">
            <div style="background: var(--color-bg); padding: 12px 16px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                <span style="font-size: 11px; color: var(--color-text-muted); display: block;">SMTP Host</span>
                <strong style="font-size: 14px; color: var(--color-text);"><?= e($config['host'] ?: '(Not Configured)') ?></strong>
            </div>
            <div style="background: var(--color-bg); padding: 12px 16px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                <span style="font-size: 11px; color: var(--color-text-muted); display: block;">Port &amp; Encryption</span>
                <strong style="font-size: 14px; color: var(--color-text);"><?= e($config['port']) ?> &bull; <?= strtoupper(e($config['encryption'])) ?></strong>
            </div>
            <div style="background: var(--color-bg); padding: 12px 16px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                <span style="font-size: 11px; color: var(--color-text-muted); display: block;">SMTP Username</span>
                <strong style="font-size: 14px; color: var(--color-text);"><?= e($config['user'] ?: '(None / Anonymous)') ?></strong>
            </div>
            <div style="background: var(--color-bg); padding: 12px 16px; border-radius: var(--radius-md); border: 1px solid var(--color-border);">
                <span style="font-size: 11px; color: var(--color-text-muted); display: block;">Sender Address</span>
                <strong style="font-size: 14px; color: var(--color-text);"><?= e($config['from_email']) ?></strong>
            </div>
        </div>
    </div>

    <!-- Diagnostic Actions -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;" class="grid-2">
        <!-- Test 1: Handshake -->
        <div class="dashboard-card">
            <h3 class="dashboard-card-title">1. Test Socket Handshake</h3>
            <p style="font-size: 13px; color: var(--color-text-muted); line-height: 1.4; margin-bottom: 16px;">
                Tests TCP connection, EHLO greeting, and TLS encryption negotiation without sending an email.
            </p>
            <form action="<?= current_url() ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="test_connection">
                <button type="submit" class="btn-auth-submit" style="height: 42px; font-size: 13px;">
                    <i class="fas fa-network-wired" style="margin-right: 6px;"></i> Run Socket Handshake Test
                </button>
            </form>
        </div>

        <!-- Test 2: Live Dispatch -->
        <div class="dashboard-card">
            <h3 class="dashboard-card-title">2. Send Test Email</h3>
            <p style="font-size: 13px; color: var(--color-text-muted); line-height: 1.4; margin-bottom: 16px;">
                Sends a live HTML test message to verify end-to-end delivery to your mailbox.
            </p>
            <form action="<?= current_url() ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_test_email">
                <div class="form-field-group" style="margin-bottom: 12px;">
                    <input type="email" name="test_recipient" class="auth-input" placeholder="recipient@example.com" value="<?= e($testRecipient ?: ($config['from_email'] ?? '')) ?>" required style="height: 42px; font-size: 13px;">
                </div>
                <button type="submit" class="btn-auth-submit" style="height: 42px; font-size: 13px;">
                    <i class="fas fa-paper-plane" style="margin-right: 6px;"></i> Send Diagnostic Email
                </button>
            </form>
        </div>
    </div>

    <!-- Results & Transcript Box -->
    <?php if ($testResult !== null): ?>
        <div style="margin-top: 24px;">
            <div style="padding: 16px 20px; border-radius: var(--radius-md); font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 10px; margin-bottom: 16px; <?= $testResult['success'] ? 'background: #e6fcf5; color: #0ca678; border: 1px solid #c3fae8;' : 'background: #fff5f5; color: #e03131; border: 1px solid #ffe3e3;' ?>">
                <i class="fas <?= $testResult['success'] ? 'fa-circle-check' : 'fa-circle-xmark' ?>" style="font-size: 18px;"></i>
                <?= e($testResult['message']) ?>
            </div>

            <?php if (!empty($diagnosticLogs)): ?>
                <div class="dashboard-card" style="padding: 16px;">
                    <h4 style="font-size: 13px; font-weight: 800; color: var(--color-text); margin: 0 0 12px 0;">
                        <i class="fas fa-terminal" style="margin-right: 6px;"></i> SMTP Protocol Transcript
                    </h4>
                    <pre style="background: #0f172a; color: #38bdf8; padding: 14px; border-radius: var(--radius-sm); font-family: monospace; font-size: 12px; line-height: 1.5; overflow-x: auto; margin: 0; max-height: 350px;"><?= e(implode("\n", $diagnosticLogs)) ?></pre>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/footer.php';
?>
