<?php
/**
 * ==========================================================================
 * public/ajax/compare.php
 * ==========================================================================
 * AJAX mutations controller for product comparisons (Add, Remove, Clear).
 * Enforces a maximum limit of 4 items, storing comparisons in DB compare_items.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only POST allowed
require_method('POST');

// Verify CSRF
verify_csrf_or_fail(true);

$action = input('action', '');
$productId = (int) input('product_id', '0');

try {
    $pdo = db();

    // 1. Resolve product existence for additions
    if ($action === 'add') {
        if ($productId <= 0) {
            json_response(false, 'Invalid product selection.', [], 400);
        }
        $prodCheck = $pdo->prepare('SELECT id FROM products WHERE id = :id AND is_active = 1 LIMIT 1');
        $prodCheck->execute(['id' => $productId]);
        if (!$prodCheck->fetch()) {
            json_response(false, 'Product not found or unavailable.', [], 404);
        }
    }

    // 2. Resolve selector constraints (user_id or session_id)
    $userId = current_user_id();
    $sqlSelector = '';
    $selectorVal = null;
    $column = '';

    if ($userId !== null) {
        $sqlSelector = 'user_id = :uid';
        $selectorVal = $userId;
        $column = 'user_id';
    } else {
        $sqlSelector = 'session_id = :sid';
        $selectorVal = get_or_create_guest_token();
        $column = 'session_id';
    }

    // A. Add to Compare list
    if ($action === 'add') {
        // Count active compared items
        $countQuery = $pdo->prepare("SELECT COUNT(*) FROM compare_items WHERE {$sqlSelector}");
        $countQuery->execute([($userId !== null ? 'uid' : 'sid') => $selectorVal]);
        $currentCount = (int) $countQuery->fetchColumn();

        if ($currentCount >= 4) {
            json_response(false, 'You can compare a maximum of 4 products. Please remove one first.', [], 422);
        }

        // Check if already in comparison list
        $existQuery = $pdo->prepare("SELECT id FROM compare_items WHERE {$sqlSelector} AND product_id = :pid LIMIT 1");
        $params = ['pid' => $productId];
        $params[$userId !== null ? 'uid' : 'sid'] = $selectorVal;
        $existQuery->execute($params);
        if ($existQuery->fetch()) {
            json_response(true, 'Product is already in your comparison list.', ['compare_count' => $currentCount]);
        }

        // Insert
        $insert = $pdo->prepare("INSERT INTO compare_items ({$column}, product_id, created_at) VALUES (:val, :pid, NOW())");
        $insert->execute([
            'val' => $selectorVal,
            'pid' => $productId
        ]);

        $newCount = $currentCount + 1;
        json_response(true, 'Product added to comparison list.', ['compare_count' => $newCount]);
    }

    // B. Remove from Compare list
    elseif ($action === 'remove') {
        $delete = $pdo->prepare("DELETE FROM compare_items WHERE {$sqlSelector} AND product_id = :pid");
        $params = ['pid' => $productId];
        $params[$userId !== null ? 'uid' : 'sid'] = $selectorVal;
        $delete->execute($params);

        // Fetch remaining count
        $countQuery = $pdo->prepare("SELECT COUNT(*) FROM compare_items WHERE {$sqlSelector}");
        $countQuery->execute([($userId !== null ? 'uid' : 'sid') => $selectorVal]);
        $newCount = (int) $countQuery->fetchColumn();

        json_response(true, 'Product removed from comparison list.', ['compare_count' => $newCount]);
    }

    // C. Clear Compare list
    elseif ($action === 'clear') {
        $clear = $pdo->prepare("DELETE FROM compare_items WHERE {$sqlSelector}");
        $clear->execute([($userId !== null ? 'uid' : 'sid') => $selectorVal]);

        json_response(true, 'Comparison list cleared successfully.', ['compare_count' => 0]);
    }

    json_response(false, 'Invalid comparison action.', [], 400);

} catch (PDOException $e) {
    error_log('[ajax/compare.php] Error: ' . $e->getMessage());
    json_response(false, 'Failed to update comparison list due to database error.', [], 500);
}
