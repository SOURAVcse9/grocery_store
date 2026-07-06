<?php
/**
 * ==========================================================================
 * admin/pos/ajax/search_customer.php — POS Customer Search Endpoint
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

$query = trim(input('q', '', 'get'));

if ($query === '') {
    echo json_encode(['success' => true, 'customers' => []]);
    exit;
}

try {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT id, full_name, phone, email, wallet_balance, reward_points 
        FROM users 
        WHERE (phone LIKE ? OR full_name LIKE ? OR id = ?)
          AND deleted_at IS NULL 
          AND is_active = 1
        LIMIT 15
    ");
    
    $searchTerm = '%' . $query . '%';
    $userId = is_numeric($query) ? (int)$query : 0;
    
    $stmt->execute([$searchTerm, $searchTerm, $userId]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'customers' => $customers
    ]);
} catch (PDOException $e) {
    error_log('[admin/pos/ajax/search_customer] query error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database search error.']);
}
