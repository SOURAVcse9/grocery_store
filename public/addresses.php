<?php
/**
 * ==========================================================================
 * public/addresses.php — Saved Address Book Manager
 * ==========================================================================
 * Allows logged-in customers to save multiple billing/shipping addresses,
 * toggle defaults, modify fields, and remove inactive records.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Secure page access
require_login();

$user = current_user();
$userId = (int) $user['id'];
$pdo = db();

// --------------------------------------------------------------------------
// 1. Process POST Request Actions (Add, Edit, Delete, Set Default)
// --------------------------------------------------------------------------
if (method_is('post')) {
    verify_csrf_or_fail();

    $action = input('action', '');

    // ---- A. Add or Edit Address ----
    if ($action === 'add' || $action === 'edit') {
        $label = trim(input('label', 'Home'));
        $recipientName = trim(input('recipient_name', ''));
        $phone = trim(input('phone', ''));
        $addressLine1 = trim(input('address_line1', ''));
        $addressLine2 = trim(input('address_line2', ''));
        $city = trim(input('city', ''));
        $state = trim(input('state', ''));
        $postalCode = trim(input('postal_code', ''));
        $country = trim(input('country', 'Bangladesh'));
        $isDefault = input('is_default', '') === '1' ? 1 : 0;

        // Validation
        $v = new Validator();
        $v->required('recipient_name', $recipientName, 'Recipient name is required.')
          ->length('recipient_name', $recipientName, 2, 100, 'Recipient name must be between 2 and 100 characters.')
          ->required('phone', $phone, 'Phone number is required.')
          ->phone('phone', $phone)
          ->required('address_line1', $addressLine1, 'Street address is required.')
          ->length('address_line1', $addressLine1, 5, 255, 'Street address must be between 5 and 255 characters.')
          ->required('city', $city, 'City/District is required.');

        if ($v->hasErrors()) {
            flash('address_alert', $v->first(), 'error');
            set_old_input($_POST);
            redirect(url_for('addresses.php' . ($action === 'edit' ? '?edit_id=' . input('address_id', '') : '')));
        }

        try {
            $pdo->beginTransaction();

            // If we are setting this address as default, unset others first
            if ($isDefault === 1) {
                $unsetDefaults = $pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = :uid');
                $unsetDefaults->execute(['uid' => $userId]);
            }

            if ($action === 'add') {
                // If this is the user's first address, force it as default
                $countQuery = $pdo->prepare('SELECT COUNT(*) FROM addresses WHERE user_id = :uid');
                $countQuery->execute(['uid' => $userId]);
                if ((int) $countQuery->fetchColumn() === 0) {
                    $isDefault = 1;
                }

                $insert = $pdo->prepare('
                    INSERT INTO addresses (user_id, label, recipient_name, phone, address_line1, address_line2, city, state, postal_code, country, is_default, created_at, updated_at)
                    VALUES (:uid, :label, :name, :phone, :address1, :address2, :city, :state, :postal, :country, :is_default, NOW(), NOW())
                ');
                $insert->execute([
                    'uid'        => $userId,
                    'label'      => $label,
                    'name'       => $recipientName,
                    'phone'      => $phone,
                    'address1'   => $addressLine1,
                    'address2'   => !empty($addressLine2) ? $addressLine2 : null,
                    'city'       => $city,
                    'state'      => !empty($state) ? $state : null,
                    'postal'     => !empty($postalCode) ? $postalCode : null,
                    'country'    => $country,
                    'is_default' => $isDefault
                ]);

                flash('address_alert', 'New address added successfully.', 'success');
            } else {
                $addressId = (int) input('address_id', '0');

                // Verify ownership before editing
                $checkOwner = $pdo->prepare('SELECT id FROM addresses WHERE id = :id AND user_id = :uid LIMIT 1');
                $checkOwner->execute(['id' => $addressId, 'uid' => $userId]);
                if (!$checkOwner->fetch()) {
                    $pdo->rollBack();
                    flash('address_alert', 'Permission denied.', 'error');
                    redirect(url_for('addresses.php'));
                }

                $update = $pdo->prepare('
                    UPDATE addresses 
                    SET label = :label, recipient_name = :name, phone = :phone, 
                        address_line1 = :address1, address_line2 = :address2, 
                        city = :city, state = :state, postal_code = :postal, 
                        country = :country, is_default = :is_default, updated_at = NOW()
                    WHERE id = :id
                ');
                $update->execute([
                    'label'      => $label,
                    'name'       => $recipientName,
                    'phone'      => $phone,
                    'address1'   => $addressLine1,
                    'address2'   => !empty($addressLine2) ? $addressLine2 : null,
                    'city'       => $city,
                    'state'      => !empty($state) ? $state : null,
                    'postal'     => !empty($postalCode) ? $postalCode : null,
                    'country'    => $country,
                    'is_default' => $isDefault,
                    'id'         => $addressId
                ]);

                flash('address_alert', 'Address details updated successfully.', 'success');
            }

            $pdo->commit();
            clear_old_input();

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[addresses.php] Save failed: ' . $e->getMessage());
            flash('address_alert', 'Failed to save address details.', 'error');
        }
    }

    // ---- B. Delete Address ----
    if ($action === 'delete') {
        $addressId = (int) input('address_id', '0');

        try {
            $pdo->beginTransaction();

            // Verify ownership
            $checkOwner = $pdo->prepare('SELECT id, is_default FROM addresses WHERE id = :id AND user_id = :uid LIMIT 1');
            $checkOwner->execute(['id' => $addressId, 'uid' => $userId]);
            $addr = $checkOwner->fetch();

            if (!$addr) {
                $pdo->rollBack();
                flash('address_alert', 'Permission denied.', 'error');
                redirect(url_for('addresses.php'));
            }

            // Delete
            $delete = $pdo->prepare('DELETE FROM addresses WHERE id = :id');
            $delete->execute(['id' => $addressId]);

            // If we deleted the default, set another address as default
            if ((int) $addr['is_default'] === 1) {
                $fallback = $pdo->prepare('
                    UPDATE addresses SET is_default = 1 
                    WHERE user_id = :uid 
                    ORDER BY id DESC LIMIT 1
                ');
                $fallback->execute(['uid' => $userId]);
            }

            $pdo->commit();
            flash('address_alert', 'Address deleted successfully.', 'success');

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[addresses.php] Delete failed: ' . $e->getMessage());
            flash('address_alert', 'Failed to delete address.', 'error');
        }
    }

    // ---- C. Set Address as Default ----
    if ($action === 'set_default') {
        $addressId = (int) input('address_id', '0');

        try {
            $pdo->beginTransaction();

            // Verify ownership
            $checkOwner = $pdo->prepare('SELECT id FROM addresses WHERE id = :id AND user_id = :uid LIMIT 1');
            $checkOwner->execute(['id' => $addressId, 'uid' => $userId]);
            if (!$checkOwner->fetch()) {
                $pdo->rollBack();
                flash('address_alert', 'Permission denied.', 'error');
                redirect(url_for('addresses.php'));
            }

            // Reset other defaults
            $pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = :uid')
                ->execute(['uid' => $userId]);

            // Set default
            $pdo->prepare('UPDATE addresses SET is_default = 1 WHERE id = :id')
                ->execute(['id' => $addressId]);

            $pdo->commit();
            flash('address_alert', 'Default address updated.', 'success');

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[addresses.php] Set default failed: ' . $e->getMessage());
            flash('address_alert', 'Failed to set default address.', 'error');
        }
    }

    redirect(url_for('addresses.php'));
}

// --------------------------------------------------------------------------
// 2. Fetch User Addresses and Prefill Values for Edit Form
// --------------------------------------------------------------------------
$addresses = [];
$editAddress = null;

try {
    // Query saved addresses list
    $stmt = $pdo->prepare('SELECT * FROM addresses WHERE user_id = :uid ORDER BY is_default DESC, id DESC');
    $stmt->execute(['uid' => $userId]);
    $addresses = $stmt->fetchAll();

    // Query prefill if edit requested
    $editId = (int) input('edit_id', '0', 'get');
    if ($editId > 0) {
        $editStmt = $pdo->prepare('SELECT * FROM addresses WHERE id = :id AND user_id = :uid LIMIT 1');
        $editStmt->execute(['id' => $editId, 'uid' => $userId]);
        $editAddress = $editStmt->fetch();
        
        if (!$editAddress) {
            flash('address_alert', 'Address not found or unauthorized.', 'error');
            redirect(url_for('addresses.php'));
        }
    }

} catch (PDOException $e) {
    error_log('[addresses.php] Database load failed: ' . $e->getMessage());
}

$pageTitle = 'Saved Address Book — ' . site_name();
$pageDescription = 'Manage your saved addresses list, set shipping defaults, and add billing info.';

$extraStylesheets = ['css/account.css', 'css/cart.css'];
$extraScripts = ['js/account.js'];

require_once __DIR__ . '/header.php';
?>

<?php
require_once __DIR__ . '/header.php';
?>

<!-- Premium UI Styles for Address Book Redesign -->
<style>
.address-book-wrapper {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-4);
}
@media (min-width: 992px) {
    .address-book-wrapper {
        grid-template-columns: 1fr 1fr;
        gap: var(--space-5);
    }
}
.addresses-premium-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-4);
}
@media (min-width: 600px) {
    .addresses-premium-grid {
        grid-template-columns: 1fr;
    }
}
.address-premium-card {
    background: #ffffff;
    border: 1px solid var(--color-border);
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    position: relative;
    transition: all 250ms ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.address-premium-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(11, 114, 133, 0.08);
    border-color: rgba(11, 114, 133, 0.2);
}
.address-premium-card.is-default-card {
    border-color: var(--color-primary);
    background-color: #f6fcfc;
}
.address-badge-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-3);
}
.address-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background-color: #f1f3f5;
    color: var(--color-text);
    padding: 6px 12px;
    border-radius: var(--radius-pill);
    font-size: 11px;
    font-weight: 700;
}
.is-default-card .address-type-badge {
    background-color: #e6f7f7;
    color: var(--color-primary);
}
.address-default-pill {
    background-color: var(--color-primary);
    color: #ffffff;
    padding: 4px 10px;
    border-radius: var(--radius-pill);
    font-size: 10px;
    font-weight: 800;
}
.address-recipient-info {
    font-size: 15px;
    font-weight: 700;
    color: var(--color-text);
    margin-bottom: 4px;
}
.address-phone-info {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-primary);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.address-body-text {
    font-size: 13px;
    color: var(--color-text-muted);
    line-height: 1.6;
    margin-bottom: 20px;
    flex-grow: 1;
}
.address-premium-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    border-top: 1px solid var(--color-border);
    padding-top: 14px;
    margin-top: auto;
}
.action-icon-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 700;
    color: var(--color-text-muted);
    text-decoration: none;
    background: none;
    border: none;
    cursor: pointer;
    transition: color 150ms ease;
    padding: 6px;
}
.action-icon-btn.btn-edit:hover {
    color: var(--color-primary);
}
.action-icon-btn.btn-delete:hover {
    color: var(--color-danger);
}
.btn-set-default-action {
    font-size: 11px;
    font-weight: 700;
    color: var(--color-primary);
    background: none;
    border: 1px solid var(--color-primary);
    border-radius: var(--radius-pill);
    padding: 4px 12px;
    cursor: pointer;
    transition: all 150ms ease;
}
.btn-set-default-action:hover {
    background-color: var(--color-primary);
    color: #ffffff;
}

/* Floating Input Group Styles */
.premium-form-group {
    position: relative;
    margin-bottom: var(--space-4);
}
.premium-form-group .input-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    font-size: 16px;
    transition: color 150ms ease;
}
.premium-form-group input,
.premium-form-group select {
    width: 100%;
    padding: 18px 14px 6px 42px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    font-size: 14px;
    background-color: #ffffff;
    outline: none;
    transition: border-color 150ms ease, box-shadow 150ms ease;
}
.premium-form-group input:focus,
.premium-form-group select:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(11, 114, 133, 0.08);
}
.premium-form-group input:focus ~ .input-icon,
.premium-form-group select:focus ~ .input-icon {
    color: var(--color-primary);
}
.premium-form-group label {
    position: absolute;
    left: 42px;
    top: 50%;
    transform: translateY(-50%);
    background-color: transparent;
    padding: 0 4px;
    color: var(--color-text-muted);
    font-size: 13px;
    font-weight: 500;
    transition: all 200ms ease;
    pointer-events: none;
}
/* Trigger floating state when input is active or focused */
.premium-form-group input:focus ~ label,
.premium-form-group input:not(:placeholder-shown) ~ label,
.premium-form-group select:focus ~ label,
.premium-form-group select:not([value=""]) ~ label,
.premium-form-group select.has-val ~ label {
    top: 10px;
    left: 42px;
    font-size: 10px;
    font-weight: 700;
    color: var(--color-primary);
}

.btn-loading-state {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.loading-spinner-form {
    display: none;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: #ffffff;
    animation: spin-form-anim 800ms infinite linear;
}
@keyframes spin-form-anim {
    to { transform: rotate(360deg); }
}
</style>

<div class="container" style="margin-top: var(--space-5); margin-bottom: var(--space-5);">
    <div class="account-layout">
        
        <!-- Left Sidebar Navigation Menu -->
        <aside class="account-sidebar">
            <ul class="account-menu-list">
                <li class="account-menu-item">
                    <a href="<?= url_for('account.php') ?>"><i class="fas fa-gauge"></i> Dashboard</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('profile.php') ?>"><i class="fas fa-user-gear"></i> Edit Profile</a>
                </li>
                <li class="account-menu-item active">
                    <a href="<?= url_for('addresses.php') ?>"><i class="fas fa-map-location-dot"></i> Saved Addresses</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('orders.php') ?>"><i class="fas fa-box-open"></i> My Orders</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('analytics.php') ?>"><i class="fas fa-chart-line"></i> Analytics</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('logout.php') ?>" style="color:var(--color-danger);"><i class="fas fa-power-off" style="color:var(--color-danger);"></i> Logout</a>
                </li>
            </ul>
        </aside>

        <!-- Right Main Panel -->
        <main class="account-main-content">
            
            <div class="address-book-wrapper">
                
                <!-- Saved Addresses Column -->
                <div>
                    <div class="dashboard-card" style="margin-bottom:0;">
                        <h3 class="dashboard-card-title"><i class="fas fa-address-book" style="color:var(--color-primary); margin-right:8px;"></i> Saved Address Book</h3>
                        
                        <!-- Display alerts -->
                        <?php display_flash_alerts('address_alert'); ?>

                        <div class="addresses-premium-grid" style="margin-top: var(--space-4);">
                            <?php if (!empty($addresses)): ?>
                                <?php foreach ($addresses as $addr): ?>
                                    <?php
                                    $labelType = strtolower($addr['label']);
                                    $iconClass = 'fa-map-pin';
                                    if ($labelType === 'home') {
                                        $iconClass = 'fa-house';
                                    } elseif ($labelType === 'office') {
                                        $iconClass = 'fa-briefcase';
                                    }
                                    ?>
                                    <div class="address-premium-card <?= $addr['is_default'] ? 'is-default-card' : '' ?>">
                                        
                                        <div class="address-badge-header">
                                            <span class="address-type-badge">
                                                <i class="fas <?= $iconClass ?>"></i> <?= e($addr['label']) ?>
                                            </span>
                                            <?php if ($addr['is_default']): ?>
                                                <span class="address-default-pill">Default</span>
                                            <?php else: ?>
                                                <form action="<?= url_for('addresses.php') ?>" method="post" style="display:inline;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="set_default">
                                                    <input type="hidden" name="address_id" value="<?= $addr['id'] ?>">
                                                    <button type="submit" class="btn-set-default-action">Set Default</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="address-recipient-info"><?= e($addr['recipient_name']) ?></div>
                                        <div class="address-phone-info">
                                            <i class="fas fa-phone" style="font-size:11px;"></i> <?= e($addr['phone']) ?>
                                        </div>
                                        
                                        <div class="address-body-text">
                                            <?= e($addr['address_line1']) ?><?= !empty($addr['address_line2']) ? ', ' . e($addr['address_line2']) : '' ?><br>
                                            <?= e($addr['city']) ?><?= !empty($addr['state']) ? ', ' . e($addr['state']) : '' ?><br>
                                            <?= !empty($addr['postal_code']) ? 'Postal Code: ' . e($addr['postal_code']) . ', ' : '' ?><?= e($addr['country']) ?>
                                        </div>

                                        <div class="address-premium-actions">
                                            <a href="<?= url_for('addresses.php?edit_id=' . (int)$addr['id']) ?>" class="action-icon-btn btn-edit">
                                                <i class="far fa-edit"></i> Edit
                                            </a>
                                            
                                            <form action="<?= url_for('addresses.php') ?>" method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this address?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="address_id" value="<?= $addr['id'] ?>">
                                                <button type="submit" class="action-icon-btn btn-delete">
                                                    <i class="far fa-trash-can"></i> Delete
                                                </button>
                                            </form>
                                        </div>

                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="padding: var(--space-5); text-align: center; color: var(--color-text-muted);">
                                    <i class="far fa-map-location-dot" style="font-size:36px; color:var(--color-border); margin-bottom:12px;"></i>
                                    <p>You have no saved addresses. Add your first address to proceed with orders faster.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Add/Edit Address Form Column -->
                <div>
                    <div class="dashboard-card" style="margin-bottom:0;">
                        <h3 class="dashboard-card-title"><i class="fas fa-location-dot" style="color:var(--color-primary); margin-right:8px;"></i> <?= $editAddress ? 'Modify Address' : 'Add New Address' ?></h3>
                        
                        <form action="<?= url_for('addresses.php') ?>" method="post" id="addressForm" style="margin-top: var(--space-4);">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="<?= $editAddress ? 'edit' : 'add' ?>">
                            <?php if ($editAddress): ?>
                                <input type="hidden" name="address_id" value="<?= $editAddress['id'] ?>">
                            <?php endif; ?>

                            <!-- Address Label Select -->
                            <div class="premium-form-group">
                                <i class="input-icon fas fa-tags"></i>
                                <select id="label" name="label" required class="has-val">
                                    <option value="Home" <?= old('label', $editAddress['label'] ?? '') === 'Home' ? 'selected' : '' ?>>Home</option>
                                    <option value="Office" <?= old('label', $editAddress['label'] ?? '') === 'Office' ? 'selected' : '' ?>>Office</option>
                                    <option value="Shipping" <?= old('label', $editAddress['label'] ?? '') === 'Shipping' ? 'selected' : '' ?>>Shipping</option>
                                    <option value="Billing" <?= old('label', $editAddress['label'] ?? '') === 'Billing' ? 'selected' : '' ?>>Billing</option>
                                </select>
                                <label for="label">Address Label *</label>
                            </div>

                            <!-- Recipient Name Input -->
                            <div class="premium-form-group">
                                <i class="input-icon fas fa-user"></i>
                                <input type="text" id="recipient_name" name="recipient_name" placeholder=" " value="<?= e(old('recipient_name', $editAddress['recipient_name'] ?? '')) ?>" required>
                                <label for="recipient_name">Recipient Name *</label>
                            </div>

                            <!-- Phone Input -->
                            <div class="premium-form-group">
                                <i class="input-icon fas fa-phone"></i>
                                <input type="tel" id="phone" name="phone" placeholder=" " value="<?= e(old('phone', $editAddress['phone'] ?? '')) ?>" required>
                                <label for="phone">Phone Number *</label>
                            </div>

                            <!-- Street Address Input -->
                            <div class="premium-form-group">
                                <i class="input-icon fas fa-map-pin"></i>
                                <input type="text" id="address_line1" name="address_line1" placeholder=" " value="<?= e(old('address_line1', $editAddress['address_line1'] ?? '')) ?>" required>
                                <label for="address_line1">Street Address (House #, Road #, Area) *</label>
                            </div>

                            <!-- Line 2 Input -->
                            <div class="premium-form-group">
                                <i class="input-icon fas fa-building"></i>
                                <input type="text" id="address_line2" name="address_line2" placeholder=" " value="<?= e(old('address_line2', $editAddress['address_line2'] ?? '')) ?>">
                                <label for="address_line2">Apartment / Suite (optional)</label>
                            </div>

                            <!-- State/Division Select Dropdown -->
                            <div class="premium-form-group">
                                <i class="input-icon fas fa-map"></i>
                                <select id="state" name="state" required>
                                    <option value="">Select Division</option>
                                    <?php
                                    $divisions = ['Barishal', 'Chattogram', 'Dhaka', 'Khulna', 'Mymensingh', 'Rajshahi', 'Rangpur', 'Sylhet'];
                                    foreach ($divisions as $div) {
                                        $selected = old('state', $editAddress['state'] ?? '') === $div ? 'selected' : '';
                                        echo "<option value=\"$div\" $selected>$div</option>";
                                    }
                                    ?>
                                </select>
                                <label for="state">State / Division *</label>
                            </div>

                            <!-- City/District Select Dropdown -->
                            <div class="premium-form-group">
                                <i class="input-icon fas fa-city"></i>
                                <select id="city" name="city" required>
                                    <option value="">Select District</option>
                                </select>
                                <label for="city">City / District *</label>
                            </div>

                            <!-- Postal Code Input with Pattern -->
                            <div class="premium-form-group">
                                <i class="input-icon fas fa-envelope-open-text"></i>
                                <input type="text" id="postal_code" name="postal_code" placeholder=" " pattern="[0-9]{4}" maxlength="4" title="Please enter a valid 4-digit postal code (e.g. 1207)" value="<?= e(old('postal_code', $editAddress['postal_code'] ?? '')) ?>">
                                <label for="postal_code">Postal / Zip Code (4 Digits)</label>
                            </div>

                            <!-- Country Input (Read Only) -->
                            <div class="premium-form-group">
                                <i class="input-icon fas fa-globe"></i>
                                <input type="text" id="country" name="country" placeholder=" " value="<?= e(old('country', $editAddress['country'] ?? 'Bangladesh')) ?>" readonly class="has-val">
                                <label for="country">Country</label>
                            </div>

                            <!-- Default Checkbox Design -->
                            <div style="margin-top: var(--space-4); display:flex; align-items:center;">
                                <label class="auth-remember-checkbox" style="display:inline-flex; align-items:center; gap:8px; cursor:pointer;">
                                    <input type="checkbox" name="is_default" value="1" <?= old('is_default', (string) ($editAddress['is_default'] ?? '')) == '1' ? 'checked' : '' ?>>
                                    <span style="font-size:13px; font-weight:600; color:var(--color-text);">Set as default shipping address</span>
                                </label>
                            </div>

                            <!-- Actions Buttons -->
                            <div style="margin-top: var(--space-4); display:flex; gap: var(--space-2);">
                                <button type="submit" class="btn btn-primary btn-loading-state" style="border-radius: var(--radius-pill); font-weight:700; padding:12px 32px; border:none; cursor:pointer;">
                                    <span class="loading-spinner-form"></span>
                                    <span class="btn-text-content"><?= $editAddress ? 'Update Address' : 'Save Address' ?></span>
                                </button>
                                <?php if ($editAddress): ?>
                                    <a href="<?= url_for('addresses.php') ?>" class="btn btn-secondary" style="border-radius: var(--radius-pill); font-weight:700; padding:12px 32px; border:none; text-decoration:none; text-align:center;">Cancel</a>
                                <?php endif; ?>
                            </div>

                        </form>
                    </div>
                </div>

            </div>

        </main>
    </div>
</div>

<!-- Bangladesh Divisions & Districts dynamic mapping -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const districtsMap = {
        'Barishal': ['Barishal', 'Bhola', 'Jhalokati', 'Patuakhali', 'Pirojpur', 'Barguna'],
        'Chattogram': ['Chattogram', 'Cox\'s Bazar', 'Bandarban', 'Khagrachhari', 'Rangamati', 'Noakhali', 'Feni', 'Laxmipur', 'Cumilla', 'Chandpur', 'Brahmanbaria'],
        'Dhaka': ['Dhaka', 'Gazipur', 'Narayanganj', 'Tangail', 'Faridpur', 'Gopalganj', 'Madaripur', 'Rajbari', 'Shariatpur', 'Manikganj', 'Munshiganj', 'Narsingdi', 'Kishoreganj'],
        'Khulna': ['Khulna', 'Jashore', 'Satkhira', 'Bagerhat', 'Kushtia', 'Meherpur', 'Chuadanga', 'Jhenaidah', 'Magura', 'Narail'],
        'Mymensingh': ['Mymensingh', 'Jamalpur', 'Netrokona', 'Sherpur'],
        'Rajshahi': ['Rajshahi', 'Bogura', 'Joypurhat', 'Naogaon', 'Natore', 'Chapainawabganj', 'Pabna', 'Sirajganj'],
        'Rangpur': ['Rangpur', 'Gaibandha', 'Kurigram', 'Lalmonirhat', 'Nilphamari', 'Dinajpur', 'Panchagarh', 'Thakurgaon'],
        'Sylhet': ['Sylhet', 'Maulvibazar', 'Habiganj', 'Sunamganj']
    };

    const divisionSelect = document.getElementById('state');
    const districtSelect = document.getElementById('city');

    // Preset values for prefill
    const savedDistrict = <?= json_encode(old('city', $editAddress['city'] ?? '')) ?>;

    function populateDistricts(division, selectedDistrict = '') {
        districtSelect.innerHTML = '<option value="">Select District</option>';
        if (!division || !districtsMap[division]) {
            districtSelect.classList.remove('has-val');
            return;
        }

        districtsMap[division].forEach(dist => {
            const opt = document.createElement('option');
            opt.value = dist;
            opt.textContent = dist;
            if (dist === selectedDistrict) {
                opt.selected = true;
            }
            districtSelect.appendChild(opt);
        });

        if (districtSelect.value) {
            districtSelect.classList.add('has-val');
        } else {
            districtSelect.classList.remove('has-val');
        }
    }

    // Initialize dropdown lists
    if (divisionSelect.value) {
        divisionSelect.classList.add('has-val');
        populateDistricts(divisionSelect.value, savedDistrict);
    }

    divisionSelect.addEventListener('change', () => {
        populateDistricts(divisionSelect.value);
        if (divisionSelect.value) {
            divisionSelect.classList.add('has-val');
        } else {
            divisionSelect.classList.remove('has-val');
        }
    });

    districtSelect.addEventListener('change', () => {
        if (districtSelect.value) {
            districtSelect.classList.add('has-val');
        } else {
            districtSelect.classList.remove('has-val');
        }
    });

    // Toggle active label class on normal fields
    const fields = document.querySelectorAll('.premium-form-group input, .premium-form-group select');
    fields.forEach(el => {
        el.addEventListener('blur', () => {
            if (el.value.trim() !== '') {
                el.classList.add('has-val');
            } else {
                el.classList.remove('has-val');
            }
        });
        if (el.value.trim() !== '') {
            el.classList.add('has-val');
        }
    });

    // Form submit loading button spinner handler
    const form = document.getElementById('addressForm');
    form.addEventListener('submit', () => {
        const btn = form.querySelector('.btn-loading-state');
        if (btn) {
            const spinner = btn.querySelector('.loading-spinner-form');
            const text = btn.querySelector('.btn-text-content');
            btn.disabled = true;
            if (spinner) spinner.style.display = 'inline-block';
            if (text) text.textContent = 'Saving Address...';
        }
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
