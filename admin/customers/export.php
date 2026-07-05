<?php
/**
 * ==========================================================================
 * admin/customers/export.php — Export Customers Database Records
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('customers.export');

$pdo = db();
$format = input('format', 'csv');

// Reconstruct conditions to match filter exports
$search = trim(input('search', ''));
$statusFilter = trim(input('status', ''));
$verificationFilter = trim(input('verification', ''));
$engagementFilter = trim(input('engagement', ''));
$sortBy = trim(input('sort_by', 'newest'));

$where = ['u.role_id != 1']; // Exclude admins
$params = [];

if (!empty($search)) {
    $where[] = '(u.full_name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search OR u.username LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($statusFilter === 'active') {
    $where[] = 'u.is_active = 1 AND u.is_banned = 0 AND u.deleted_at IS NULL';
} elseif ($statusFilter === 'inactive') {
    $where[] = 'u.is_active = 0 AND u.deleted_at IS NULL';
} elseif ($statusFilter === 'banned') {
    $where[] = 'u.is_banned = 1 AND u.deleted_at IS NULL';
} elseif ($statusFilter === 'trash') {
    $where[] = 'u.deleted_at IS NOT NULL';
} else {
    $where[] = 'u.deleted_at IS NULL';
}

if ($verificationFilter === 'verified') {
    $where[] = 'u.is_verified = 1';
} elseif ($verificationFilter === 'email_verified') {
    $where[] = 'u.email_verified = 1';
} elseif ($verificationFilter === 'phone_verified') {
    $where[] = 'u.phone_verified = 1';
}

if ($engagementFilter === 'with_orders') {
    $where[] = '(SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) > 0';
} elseif ($engagementFilter === 'without_orders') {
    $where[] = '(SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) = 0';
} elseif ($engagementFilter === 'with_reviews') {
    $where[] = '(SELECT COUNT(*) FROM product_reviews pr WHERE pr.user_id = u.id) > 0';
} elseif ($engagementFilter === 'with_wishlist') {
    $where[] = '(SELECT COUNT(*) FROM wishlists w WHERE w.user_id = u.id) > 0';
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

$orderClause = 'ORDER BY u.created_at DESC';
if ($sortBy === 'oldest') {
    $orderClause = 'ORDER BY u.created_at ASC';
} elseif ($sortBy === 'highest_spending') {
    $orderClause = 'ORDER BY lifetime_spending DESC';
} elseif ($sortBy === 'top_buyers') {
    $orderClause = 'ORDER BY total_orders DESC';
}

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.email, u.phone, u.created_at, u.is_active, u.is_banned,
               (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS total_orders,
               (SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o WHERE o.user_id = u.id AND o.status = 'delivered') AS lifetime_spending
        FROM users u
        {$whereClause}
        {$orderClause}
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[admin/customers/export] query failed: ' . $e->getMessage());
    $records = [];
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customers_export_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    if ($output) {
        fputcsv($output, ['Customer ID', 'Full Name', 'Email', 'Phone', 'Registration Date', 'Total Orders', 'Lifetime Spending (৳)', 'Status']);
        foreach ($records as $r) {
            $status = 'Active';
            if ($r['is_banned']) {
                $status = 'Banned';
            } elseif (!$r['is_active']) {
                $status = 'Suspended';
            }
            fputcsv($output, [
                $r['id'],
                $r['full_name'],
                $r['email'],
                $r['phone'] ?? 'N/A',
                $r['created_at'],
                $r['total_orders'],
                number_format((float)$r['lifetime_spending'], 2, '.', ''),
                $status
            ]);
        }
        fclose($output);
    }
    exit;
}

// Default Fallback: Print friendly layout (acts as print / PDF view)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customers Directory Sheet</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; margin: 30px; color: #333; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body onload="window.print();">
    <h1>Customers Directory</h1>
    <p>Export Date: <?= date('Y-m-d H:i:s') ?> | Total Records: <?= count($records) ?></p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Registration Date</th>
                <th>Total Orders</th>
                <th>Lifetime Spending</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $r): 
                $status = 'Active';
                if ($r['is_banned']) {
                    $status = 'Banned';
                } elseif (!$r['is_active']) {
                    $status = 'Suspended';
                }
            ?>
                <tr>
                    <td>#<?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['email'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['phone'] ?: 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= date('Y-m-d', strtotime($r['created_at'])) ?></td>
                    <td><?= $r['total_orders'] ?></td>
                    <td>৳<?= number_format((float)$r['lifetime_spending'], 2) ?></td>
                    <td><?= $status ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
