<?php
/**
 * ==========================================================================
 * admin/settings/index.php — Core Store Settings & Configuration Controls
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Store Settings — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('settings.manage');

$pdo = db();
$error = null;
$success = null;

// Fetch all settings key-value rows
try {
    $settingsRaw = $pdo->query("SELECT key_name, value FROM settings")->fetchAll();
    $settings = [];
    foreach ($settingsRaw as $row) {
        $settings[$row['key_name']] = $row['value'];
    }
} catch (PDOException $e) {
    error_log('[admin/settings] load fail: ' . $e->getMessage());
    $settings = [];
}

// Handle Settings Update Submission
if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $updates = [
            'site_name'                 => trim(input('site_name', '')),
            'site_email'                => trim(input('site_email', '')),
            'site_phone'                => trim(input('site_phone', '')),
            'site_address'              => trim(input('site_address', '')),
            'site_business_hours'       => trim(input('site_business_hours', '')),
            'site_currency'             => trim(input('site_currency', 'BDT')),
            'site_currency_symbol'      => trim(input('site_currency_symbol', '৳')),
            'site_timezone'             => trim(input('site_timezone', 'Asia/Dhaka')),
            'site_tax'                  => trim(input('site_tax', '5.00')),
            'site_shipping_charge'      => trim(input('site_shipping_charge', '60.00')),
            'site_min_order'            => trim(input('site_min_order', '100.00')),
            'site_invoice_prefix'       => trim(input('site_invoice_prefix', 'INV-')),
            'site_facebook'             => trim(input('site_facebook', '')),
            'site_twitter'              => trim(input('site_twitter', '')),
            'site_instagram'            => trim(input('site_instagram', '')),
            'site_copyright'            => trim(input('site_copyright', '')),
            'site_meta_title'           => trim(input('site_meta_title', '')),
            'site_meta_description'     => trim(input('site_meta_description', '')),
            'site_meta_keywords'        => trim(input('site_meta_keywords', '')),
            'google_analytics'          => trim(input('google_analytics', '')),
            'facebook_pixel'            => trim(input('facebook_pixel', '')),
            'announcement_bar_text'     => trim(input('announcement_bar_text', '')),
            'announcement_bar_active'   => (string)(int)input('announcement_bar_active', '0'),
            'popup_notice_title'        => trim(input('popup_notice_title', '')),
            'popup_notice_message'      => trim(input('popup_notice_message', '')),
            'popup_notice_active'       => (string)(int)input('popup_notice_active', '0'),
            'holiday_notice_active'     => (string)(int)input('holiday_notice_active', '0'),
            'maintenance_notice_active' => (string)(int)input('maintenance_notice_active', '0'),
            'smtp_host'                 => trim(input('smtp_host', '')),
            'smtp_port'                 => trim(input('smtp_port', '')),
            'smtp_user'                 => trim(input('smtp_user', '')),
            'smtp_pass'                 => trim(input('smtp_pass', '')),
            'smtp_encryption'           => trim(input('smtp_encryption', 'tls'))
        ];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO settings (key_name, value, updated_at)
                VALUES (:key, :val, NOW())
                ON DUPLICATE KEY UPDATE value = :val2, updated_at = NOW()
            ");

            foreach ($updates as $k => $v) {
                $stmt->execute(['key' => $k, 'val' => $v, 'val2' => $v]);
                $settings[$k] = $v; // update in-memory
            }

            log_admin_activity('settings.edit', 'Updated core system and SMTP configurations.');
            $success = 'System configurations successfully updated!';
        } catch (PDOException $e) {
            error_log('[admin/settings] Save failed: ' . $e->getMessage());
            $error = 'Failed to save system configurations due to database error.';
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Store Settings</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Manage announcements, SMTP setups, SEO metadata, tracking pixels, and financial values.</p>
    </div>
</div>

<!-- Notifications -->
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<form method="post" class="auth-form" style="display:grid; grid-template-columns: 2fr 1fr; gap:var(--space-6); align-items:start;" class="admin-dashboard-layout">
    <?= csrf_field() ?>
    
    <!-- Main Left Column -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
        
        <!-- General store info -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h2 style="font-size:14px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-3); border-bottom:1px solid var(--color-border); padding-bottom:6px;">Contact & General Info</h2>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
                <div class="form-field-group">
                    <label style="font-weight:700;">Store Name *</label>
                    <input type="text" name="site_name" required value="<?= e($settings['site_name'] ?? 'GroCo Store') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700;">Store Support Email *</label>
                    <input type="email" name="site_email" required value="<?= e($settings['site_email'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700;">Support Phone *</label>
                    <input type="text" name="site_phone" required value="<?= e($settings['site_phone'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700;">Business Hours</label>
                    <input type="text" name="site_business_hours" value="<?= e($settings['site_business_hours'] ?? '') ?>" placeholder="9:00 AM - 10:00 PM" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
            </div>

            <div class="form-field-group">
                <label style="font-weight:700;">Physical Address</label>
                <input type="text" name="site_address" value="<?= e($settings['site_address'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <!-- System & Financial settings -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h2 style="font-size:14px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-3); border-bottom:1px solid var(--color-border); padding-bottom:6px;">Regional & Financial Settings</h2>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;" class="grid-2">
                <div class="form-field-group">
                    <label style="font-weight:700;">Currency (e.g. BDT)</label>
                    <input type="text" name="site_currency" value="<?= e($settings['site_currency'] ?? 'BDT') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700;">Currency Symbol (e.g. ৳)</label>
                    <input type="text" name="site_currency_symbol" value="<?= e($settings['site_currency_symbol'] ?? '৳') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700;">Timezone</label>
                    <input type="text" name="site_timezone" value="<?= e($settings['site_timezone'] ?? 'Asia/Dhaka') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700;">VAT / Tax Rate (%)</label>
                    <input type="number" name="site_tax" step="0.01" value="<?= e($settings['site_tax'] ?? '5.00') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700;">Shipping Charge (৳)</label>
                    <input type="number" name="site_shipping_charge" step="0.01" value="<?= e($settings['site_shipping_charge'] ?? '60.00') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700;">Minimum Order Total (৳)</label>
                    <input type="number" name="site_min_order" step="0.01" value="<?= e($settings['site_min_order'] ?? '100.00') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
            </div>
        </div>

        <!-- SMTP configurations -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h2 style="font-size:14px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-3); border-bottom:1px solid var(--color-border); padding-bottom:6px;">SMTP Mail Server Configuration</h2>
            
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px;" class="grid-2">
                <div class="form-field-group">
                    <label style="font-weight:700;">SMTP Host</label>
                    <input type="text" name="smtp_host" value="<?= e($settings['smtp_host'] ?? '') ?>" placeholder="mail.yourserver.com" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700;">SMTP Port</label>
                    <input type="text" name="smtp_port" value="<?= e($settings['smtp_port'] ?? '587') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
            </div>
            
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;" class="grid-3">
                <div class="form-field-group">
                    <label style="font-weight:700;">SMTP User</label>
                    <input type="text" name="smtp_user" value="<?= e($settings['smtp_user'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700;">SMTP Password</label>
                    <input type="password" name="smtp_pass" value="<?= e($settings['smtp_pass'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group">
                    <label style="font-weight:700;">Encryption</label>
                    <select name="smtp_encryption" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                        <option value="tls" <?= ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Marketing & Timers -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h2 style="font-size:14px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-3); border-bottom:1px solid var(--color-border); padding-bottom:6px;">Announcement & Alert Bars</h2>
            
            <div class="form-field-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                    <label style="font-weight:700; margin:0;">Header Announcement Text</label>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <input type="checkbox" id="setBarActive" name="announcement_bar_active" value="1" <?= ($settings['announcement_bar_active'] ?? '0') === '1' ? 'checked' : '' ?> style="cursor:pointer; width:14px; height:14px;">
                        <label for="setBarActive" style="margin:0; font-size:11px; font-weight:600; cursor:pointer;">Show Announcement Bar</label>
                    </div>
                </div>
                <input type="text" name="announcement_bar_text" value="<?= e($settings['announcement_bar_text'] ?? '') ?>" placeholder="Mega Deal discount promo banner text..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

    </div>

    <!-- Right Column: Status toggles, SEO & Tracking -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
        
        <!-- Store active states -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h2 style="font-size:13px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-3); border-bottom:1px solid var(--color-border); padding-bottom:6px;">Status Controls</h2>
            
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:12px; font-weight:700; color:var(--color-text);">Holiday Notice Mode</span>
                    <input type="checkbox" name="holiday_notice_active" value="1" <?= ($settings['holiday_notice_active'] ?? '0') === '1' ? 'checked' : '' ?> style="cursor:pointer; width:16px; height:16px;">
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:12px; font-weight:700; color:var(--color-text);">Maintenance Mode</span>
                    <input type="checkbox" name="maintenance_notice_active" value="1" <?= ($settings['maintenance_notice_active'] ?? '0') === '1' ? 'checked' : '' ?> style="cursor:pointer; width:16px; height:16px;">
                </div>
            </div>
        </div>

        <!-- Global SEO details -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h2 style="font-size:13px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-3); border-bottom:1px solid var(--color-border); padding-bottom:6px;">Global SEO Configuration</h2>
            
            <div class="form-field-group">
                <label style="font-weight:700;">Global Meta Title</label>
                <input type="text" name="site_meta_title" value="<?= e($settings['site_meta_title'] ?? '') ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <div class="form-field-group">
                <label style="font-weight:700;">Global Description</label>
                <textarea name="site_meta_description" rows="3" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"><?= e($settings['site_meta_description'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Pixels & Scripts -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h2 style="font-size:13px; font-weight:800; color:var(--color-text); margin-bottom:var(--space-3); border-bottom:1px solid var(--color-border); padding-bottom:6px;">Analytics & Pixels</h2>
            
            <div class="form-field-group">
                <label style="font-weight:700;">Google Analytics Measurement ID</label>
                <input type="text" name="google_analytics" value="<?= e($settings['google_analytics'] ?? '') ?>" placeholder="G-XXXXXXXXXX" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <div class="form-field-group">
                <label style="font-weight:700;">Facebook Pixel ID</label>
                <input type="text" name="facebook_pixel" value="<?= e($settings['facebook_pixel'] ?? '') ?>" placeholder="1234567890" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-check"></i> Save Settings</button>
    </div>

</form>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
