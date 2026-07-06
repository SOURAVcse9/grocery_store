<?php
/**
 * ==========================================================================
 * admin/pos/ajax/create_customer.php — POS Create Customer AJAX Endpoint
 * ==========================================================================
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../../public/dbconnect.php';
require_once __DIR__ . '/../../includes/auth_helpers.php';

// Safe JSON auth validation checks
if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!has_admin_permission('pos.sale')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

if (!verify_csrf()) {
    echo json_encode(['success' => false, 'error' => 'CSRF verification failed.']);
    exit;
}

$name = trim(input('name', ''));
$mobile = trim(input('mobile', ''));
$email = trim(input('email', ''));
$address = trim(input('address', ''));

if ($name === '' || $mobile === '') {
    echo json_encode(['success' => false, 'error' => 'Name and Mobile Number are required.']);
    exit;
}

// Generate fallback email to satisfy NOT NULL constraints if empty
if ($email === '') {
    $email = 'pos_' . $mobile . '@grocery.store';
}

try {
    $pdo = db();
    
    // Check if mobile already exists
    $chkPhone = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
    $chkPhone->execute([$mobile]);
    if ($chkPhone->fetch()) {
        echo json_encode(['success' => false, 'error' => 'A customer with this mobile number already exists.']);
        exit;
    }

    // Check if email already exists
    $chkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $chkEmail->execute([$email]);
    if ($chkEmail->fetch()) {
        echo json_encode(['success' => false, 'error' => 'A customer with this email address already exists.']);
        exit;
    }

    // Create customer record with safe default values
    $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (role_id, full_name, email, phone, password, is_verified, is_active, created_at, updated_at) 
        VALUES (2, ?, ?, ?, ?, 1, 1, NOW(), NOW())
    ");
    $stmt->execute([$name, $email, $mobile, $password]);
    $newId = (int)$pdo->lastInsertId();

    // Optionally save address record
    if ($address !== '') {
        $addrStmt = $pdo->prepare("
            INSERT INTO addresses (user_id, label, recipient_name, phone, address_line1, city, country, is_default)
            VALUES (?, 'POS Address', ?, ?, ?, 'Dhaka', 'Bangladesh', 1)
        ");
        $addrStmt->execute([$newId, $name, $mobile, $address]);
    }

    echo json_encode([
        'success' => true,
        'customer' => [
            'id' => $newId,
            'full_name' => $name,
            'phone' => $mobile,
            'email' => $email,
            'wallet_balance' => 0.00,
            'reward_points' => 0
        ]
    ]);
} catch (PDOException $e) {
    error_log('[admin/pos/ajax/create_customer] query error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database customer insertion error.']);
}
