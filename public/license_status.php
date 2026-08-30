<?php
/**
 * public/license_status.php
 * 
 * Branded License Status & Diagnostic Screen for GroCo Grocery Store.
 * Displays informative, non-destructive alerts for expired, revoked,
 * suspended, or grace-exceeded licenses.
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$pageTitle = 'License Notice — ' . site_name();
$lic = get_local_license();
$statusParam = strtolower((string)($_GET['status'] ?? ($lic['status'] ?? 'notice')));

$reverifyMsg = '';
$reverifySuccess = false;

// Handle manual re-verification check
if (isset($_POST['action']) && $_POST['action'] === 'reverify') {
    $res = verify_license_remote(true);
    if ($res['valid']) {
        $reverifySuccess = true;
        $reverifyMsg = 'License verified successfully! Redirecting to storefront...';
        header('Refresh: 2; URL=' . url_for('index.php'));
    } else {
        $reverifyMsg = 'Re-verification completed: ' . ($res['reason'] ?? 'License remains inactive.');
        $statusParam = $res['status'] ?? $statusParam;
    }
}

$statusConfig = [
    'revoked' => [
        'title' => 'License Access Revoked',
        'icon' => 'fa-ban',
        'color' => '#fa5252',
        'bg' => 'rgba(250, 82, 82, 0.1)',
        'description' => 'The commercial license associated with this GroCo installation has been revoked by the system administrator. Store operations have been paused.',
    ],
    'suspended' => [
        'title' => 'License Temporarily Suspended',
        'icon' => 'fa-pause',
        'color' => '#fd7e14',
        'bg' => 'rgba(253, 126, 20, 0.1)',
        'description' => 'This software license is currently suspended due to pending account verification or administrative review.',
    ],
    'expired' => [
        'title' => 'License Period Expired',
        'icon' => 'fa-hourglass-end',
        'color' => '#fab005',
        'bg' => 'rgba(250, 176, 5, 0.1)',
        'description' => 'The software support and license validity period for this installation has expired. Please renew your license subscription to continue production usage.',
    ],
    'grace_exceeded' => [
        'title' => 'License Re-verification Required',
        'icon' => 'fa-signal',
        'color' => '#e03131',
        'bg' => 'rgba(224, 49, 49, 0.1)',
        'description' => 'This installation was unable to communicate with the GroCo licensing authority within the allowed offline grace period. Please ensure your web server has internet connectivity.',
    ],
    'domain_mismatch' => [
        'title' => 'Unauthorized Domain Binding',
        'icon' => 'fa-globe',
        'color' => '#fa5252',
        'bg' => 'rgba(250, 82, 82, 0.1)',
        'description' => 'This software license is bound to a different domain name and cannot be used on this host.',
    ],
];

$activeConfig = $statusConfig[$statusParam] ?? [
    'title' => 'Software License Inactive',
    'icon' => 'fa-shield-halved',
    'color' => '#4c6ef5',
    'bg' => 'rgba(76, 110, 245, 0.1)',
    'description' => 'This GroCo installation requires an active license key to operate in production mode.',
];
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
        .status-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            max-width: 520px;
            width: 100%;
            padding: 40px 32px;
            box-sizing: border-box;
            text-align: center;
            position: relative;
        }
        .status-icon-wrapper {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 20px auto;
        }
    </style>
</head>
<body>

<div class="status-card">
    <div style="position:absolute; top:20px; right:20px;">
        <button type="button" class="theme-toggle-btn" aria-label="Toggle theme" onclick="toggleTheme();" style="border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); border-radius:50%; width:32px; height:32px; cursor:pointer; display:flex; align-items:center; justify-content:center;">
            <i class="fas fa-moon"></i>
        </button>
    </div>

    <div class="status-icon-wrapper" style="background:<?= $activeConfig['bg'] ?>; color:<?= $activeConfig['color'] ?>;">
        <i class="fas <?= $activeConfig['icon'] ?>"></i>
    </div>

    <h1 style="font-size:20px; font-weight:800; color:var(--color-text); margin:0 0 12px 0;">
        <?= e($activeConfig['title']) ?>
    </h1>

    <p style="font-size:13px; color:var(--color-text-muted); line-height:1.6; margin:0 0 24px 0;">
        <?= e($activeConfig['description']) ?>
    </p>

    <?php if ($reverifyMsg): ?>
        <div style="background:<?= $reverifySuccess ? 'rgba(64,192,87,0.1)' : 'rgba(250,82,82,0.1)' ?>; border:1px solid <?= $reverifySuccess ? '#40c057' : '#fa5252' ?>; color:<?= $reverifySuccess ? '#2b8a3e' : '#fa5252' ?>; padding:12px; border-radius:var(--radius-sm); font-size:12px; margin-bottom:20px;">
            <?= e($reverifyMsg) ?>
        </div>
    <?php endif; ?>

    <?php if ($lic): ?>
        <div style="background:var(--color-bg); border:1px solid var(--color-border); border-radius:var(--radius-sm); padding:14px; font-size:12px; color:var(--color-text-muted); text-align:left; margin-bottom:24px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span>License Key:</span>
                <strong style="color:var(--color-text); font-family:monospace;"><?= e($lic['license_mask']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span>License Type:</span>
                <strong style="color:var(--color-text); text-transform:uppercase;"><?= e($lic['license_type'] ?? 'production') ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span>Bound Domain:</span>
                <span style="color:var(--color-text);"><?= e($lic['domain']) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between;">
                <span>Last Verified:</span>
                <span style="color:var(--color-text);"><?= e($lic['last_verified_at'] ? date('M d, Y H:i', strtotime($lic['last_verified_at'])) : 'Never') ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div style="display:flex; flex-direction:column; gap:10px;">
        <form method="post">
            <input type="hidden" name="action" value="reverify">
            <button type="submit" class="btn btn-primary" style="padding:12px 20px; font-size:13px; font-weight:700; border-radius:var(--radius-pill); border:none; cursor:pointer; width:100%; display:flex; align-items:center; justify-content:center; gap:8px;">
                <i class="fas fa-rotate"></i> Re-verify License Now
            </button>
        </form>

        <a href="<?= url_for('activate.php') ?>" style="padding:12px 20px; font-size:13px; font-weight:700; border-radius:var(--radius-pill); border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); text-decoration:none; display:inline-block;">
            <i class="fas fa-key"></i> Enter New License Key
        </a>
    </div>

    <div style="margin-top:24px; font-size:11px; color:var(--color-text-muted); border-top:1px solid var(--color-border); padding-top:16px;">
        For questions or license renewal inquiries, please email <a href="mailto:<?= e(CONTACT_EMAIL) ?>" style="color:var(--color-primary); font-weight:600; text-decoration:none;"><?= e(CONTACT_EMAIL) ?></a>
    </div>
</div>

</body>
</html>
