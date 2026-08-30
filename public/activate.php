<?php
/**
 * public/activate.php
 * 
 * Production License Activation Screen for GroCo Grocery Store.
 * Allows administrators to bind and cryptographically activate this installation
 * against the authoritative licensing server.
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$pageTitle = 'Activate License — ' . site_name();
$currentDomain = get_current_domain();
$installationId = get_installation_id();

$error = '';
$success = '';

// Check if already active and valid
$existingLicense = get_local_license();
if ($existingLicense && $existingLicense['is_signature_valid'] && $existingLicense['status'] === 'active') {
    $typeLabel = strtoupper($existingLicense['license_type'] ?? 'PRODUCTION');
    $success = "This GroCo installation is already active with a valid [{$typeLabel}] license bound to: " . e($existingLicense['domain']);
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $licenseKey = trim((string)($_POST['license_key'] ?? ''));
    $email = trim((string)($_POST['customer_email'] ?? ''));
    $domain = trim((string)($_POST['domain'] ?? $currentDomain));

    if (empty($licenseKey)) {
        $error = 'Please enter your GroCo License Key.';
    } elseif (!preg_match('/^GRCO-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/i', $licenseKey)) {
        $error = 'Invalid license key format. Expected format: GRCO-XXXX-XXXX-XXXX-XXXX';
    } else {
        $res = activate_license_remote($licenseKey, $domain, $email);
        if ($res['success']) {
            $success = $res['message'] . ' Redirecting to homepage...';
            // Redirect after 2 seconds or if AJAX return JSON
            if (is_ajax_or_api_request()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect' => url_for('index.php')]);
                exit;
            }
            header('Refresh: 2; URL=' . url_for('index.php'));
        } else {
            $error = $res['message'] ?? 'Activation failed. Please check your key and domain.';
            if (is_ajax_or_api_request()) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $res['error'] ?? 'FAILED', 'message' => $error]);
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <script src="<?= asset('js/theme.js') ?>"></script>
    <style>
        body {
            background-color: var(--color-bg);
            color: var(--color-text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-4);
            margin: 0;
            font-family: 'Inter', system-ui, sans-serif;
        }
        .activation-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            max-width: 520px;
            width: 100%;
            padding: 36px 32px;
            box-sizing: border-box;
            position: relative;
        }
        .activation-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            color: var(--color-primary);
            font-size: 20px;
            font-weight: 800;
        }
        .activation-input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            background: var(--color-surface);
            color: var(--color-text);
            box-sizing: border-box;
            outline: none;
            transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
        }
        .activation-input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px var(--color-primary-light);
        }
        .license-key-input {
            font-family: 'Courier New', Courier, monospace;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            text-align: center;
        }
        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: var(--radius-pill);
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            color: var(--color-text-muted);
        }
    </style>
</head>
<body>

<div class="activation-card">
    <div style="position:absolute; top:20px; right:20px;">
        <button type="button" class="theme-toggle-btn" aria-label="Toggle theme" onclick="toggleTheme();" style="border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); border-radius:50%; width:32px; height:32px; cursor:pointer; display:flex; align-items:center; justify-content:center;">
            <i class="fas fa-moon"></i>
        </button>
    </div>

    <div class="activation-brand">
        <i class="fas fa-shield-halved"></i>
        <span>GroCo Installation Activation</span>
    </div>

    <p style="font-size:13px; color:var(--color-text-muted); line-height:1.6; margin:0 0 20px 0;">
        Welcome to your GroCo Grocery Store installation. Please enter your commercial license key to verify and register this production instance.
    </p>

    <?php if ($error): ?>
        <div style="background:rgba(250,82,82,0.1); border:1px solid #fa5252; color:#fa5252; padding:12px 16px; border-radius:var(--radius-sm); font-size:13px; margin-bottom:20px; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-circle-exclamation"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="background:rgba(64,192,87,0.1); border:1px solid #40c057; color:#2b8a3e; padding:12px 16px; border-radius:var(--radius-sm); font-size:13px; margin-bottom:20px; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-circle-check"></i>
            <span><?= e($success) ?></span>
        </div>
    <?php endif; ?>

    <form method="post" action="activate.php" id="frmActivation" style="display:flex; flex-direction:column; gap:16px;">
        <div>
            <label for="license_key" style="display:block; font-size:12px; font-weight:700; margin-bottom:6px; color:var(--color-text);">
                Software License Key *
            </label>
            <input type="text" 
                   id="license_key" 
                   name="license_key" 
                   class="activation-input license-key-input" 
                   placeholder="GRCO-XXXX-XXXX-XXXX-XXXX" 
                   autocomplete="off" 
                   required
                   maxlength="24"
                   value="<?= e($_POST['license_key'] ?? '') ?>">
            <span style="font-size:11px; color:var(--color-text-faint); margin-top:4px; display:block;">
                Provided in your GroCo customer onboarding purchase receipt.
            </span>
        </div>

        <div>
            <label for="customer_email" style="display:block; font-size:12px; font-weight:700; margin-bottom:6px; color:var(--color-text);">
                Contact Email Address
            </label>
            <input type="email" 
                   id="customer_email" 
                   name="customer_email" 
                   class="activation-input" 
                   placeholder="admin@yourdomain.com" 
                   value="<?= e($_POST['customer_email'] ?? '') ?>">
        </div>

        <div>
            <label for="domain" style="display:block; font-size:12px; font-weight:700; margin-bottom:6px; color:var(--color-text);">
                Production Domain / Hostname
            </label>
            <input type="text" 
                   id="domain" 
                   name="domain" 
                   class="activation-input" 
                   readonly 
                   style="background:var(--color-bg); cursor:not-allowed;" 
                   value="<?= e($currentDomain) ?>">
        </div>

        <div style="background:var(--color-bg); border:1px solid var(--color-border); border-radius:var(--radius-sm); padding:12px; font-size:11px; color:var(--color-text-muted); line-height:1.5;">
            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span>Installation Node ID:</span>
                <span class="meta-pill"><i class="fas fa-server"></i> <?= e($installationId) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between;">
                <span>Verification Authority:</span>
                <span>RSA-2048 Cryptographic API</span>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="padding:14px; font-size:14px; font-weight:700; border-radius:var(--radius-pill); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; width:100%; margin-top:8px;">
            <i class="fas fa-key"></i> Activate Installation
        </button>
    </form>

    <div style="margin-top:24px; text-align:center; font-size:11px; color:var(--color-text-muted); border-top:1px solid var(--color-border); padding-top:16px;">
        Need help or lost your license key? Contact <a href="mailto:<?= e(CONTACT_EMAIL) ?>" style="color:var(--color-primary); text-decoration:none; font-weight:600;">GroCo Support</a>
    </div>
</div>

<script>
// Auto-format license key with hyphens
document.getElementById('license_key').addEventListener('input', function(e) {
    let val = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (val.startsWith('GRCO')) {
        val = val.substring(4);
    }
    let parts = ['GRCO'];
    for (let i = 0; i < val.length && i < 16; i += 4) {
        parts.push(val.substring(i, i + 4));
    }
    this.value = parts.join('-');
});
</script>

</body>
</html>
