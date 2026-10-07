<?php
/**
 * ==============================================================================
 * admin/payments/index.php — Payment Gateway Ledger & Transactions Manager
 * ==============================================================================
 * Central administrative interface for tracking SSLCOMMERZ payments, bank tran IDs,
 * verification statuses, transaction logs, and financial reconciliation.
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();

$pdo = db();

// Filter & Search Parameters
$search        = trim((string)input('search', '', 'get'));
$statusFilter  = trim((string)input('status', '', 'get'));
$providerFilter= trim((string)input('provider', '', 'get'));
$dateFrom      = trim((string)input('date_from', '', 'get'));
$dateTo        = trim((string)input('date_to', '', 'get'));
$page          = max(1, (int)input('page', '1', 'get'));
$perPage       = 20;
$offset        = ($page - 1) * $perPage;

// Base query builder
$whereClauses = ['1=1'];
$params = [];

if ($search !== '') {
    $whereClauses[] = '(p.tran_id LIKE :s OR p.bank_tran_id LIKE :s OR p.val_id LIKE :s OR o.order_number LIKE :s OR u.full_name LIKE :s OR u.email LIKE :s)';
    $params['s'] = "%{$search}%";
}

if ($statusFilter !== '') {
    $whereClauses[] = 'p.status = :status';
    $params['status'] = $statusFilter;
}

if ($providerFilter !== '') {
    $whereClauses[] = 'p.provider = :provider';
    $params['provider'] = $providerFilter;
}

if ($dateFrom !== '') {
    $whereClauses[] = 'DATE(p.created_at) >= :dfrom';
    $params['dfrom'] = $dateFrom;
}

if ($dateTo !== '') {
    $whereClauses[] = 'DATE(p.created_at) <= :dto';
    $params['dto'] = $dateTo;
}

$whereSql = implode(' AND ', $whereClauses);

// Count Total
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM payments p
    LEFT JOIN orders o ON o.id = p.order_id
    LEFT JOIN users u ON u.id = p.user_id
    WHERE {$whereSql}
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

// Fetch Records
$listStmt = $pdo->prepare("
    SELECT p.*, o.order_number, o.payment_status as order_pay_status, u.full_name as customer_name, u.email as customer_email
    FROM payments p
    LEFT JOIN orders o ON o.id = p.order_id
    LEFT JOIN users u ON u.id = p.user_id
    WHERE {$whereSql}
    ORDER BY p.id DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$listStmt->execute($params);
$payments = $listStmt->fetchAll();

// Metrics Summary
$metricsStmt = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'PAID' THEN amount ELSE 0 END), 0) as total_volume,
        COUNT(CASE WHEN status = 'PAID' THEN 1 END) as count_paid,
        COUNT(CASE WHEN status IN ('INITIATED', 'PENDING') THEN 1 END) as count_pending,
        COUNT(CASE WHEN status = 'FAILED' THEN 1 END) as count_failed,
        COALESCE(SUM(refunded_amount), 0) as total_refunded
    FROM payments
");
$metrics = $metricsStmt->fetch();

$pageTitle = 'Payments & Gateways Ledger';
require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
require_once __DIR__ . '/../layouts/topbar.php';
?>

<div class="admin-content-body">
    <!-- Breadcrumb & Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 800; margin: 0 0 6px 0; color: #1e293b;">
                <i class="fas fa-money-bill-transfer" style="color: #6366f1;"></i> Payment Gateway Transactions
            </h1>
            <p style="margin: 0; color: #64748b; font-size: 14px;">
                Authoritative online transactions, validation status, and audit records.
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="reconciliation.php" class="btn btn-secondary" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 16px; font-weight: 600; text-decoration: none;">
                <i class="fas fa-scale-balanced"></i> Reconciliation Dashboard
            </a>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Online Collected</div>
            <div style="font-size: 24px; font-weight: 800; color: #059669; margin-top: 6px;">৳<?= number_format((float)$metrics['total_volume'], 2) ?></div>
            <div style="font-size: 12px; color: #10b981; margin-top: 4px;"><?= $metrics['count_paid'] ?> Successful Transactions</div>
        </div>

        <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Pending / In-Flight</div>
            <div style="font-size: 24px; font-weight: 800; color: #d97706; margin-top: 6px;"><?= $metrics['count_pending'] ?></div>
            <div style="font-size: 12px; color: #f59e0b; margin-top: 4px;">Awaiting callback or user action</div>
        </div>

        <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Failed / Declined</div>
            <div style="font-size: 24px; font-weight: 800; color: #dc2626; margin-top: 6px;"><?= $metrics['count_failed'] ?></div>
            <div style="font-size: 12px; color: #ef4444; margin-top: 4px;">Customer declined / bank reject</div>
        </div>

        <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Refunded</div>
            <div style="font-size: 24px; font-weight: 800; color: #475569; margin-top: 6px;">৳<?= number_format((float)$metrics['total_refunded'], 2) ?></div>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Returned to customers</div>
        </div>
    </div>

    <!-- Filters & Search Form -->
    <div style="background: #fff; padding: 18px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px;">
        <form method="get" action="index.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 100px; gap: 12px; align-items: end;">
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Search Keyword</label>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Tran ID, Bank ID, Order #, Email..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">Payment Status</label>
                <select name="status" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                    <option value="">All Statuses</option>
                    <option value="PAID" <?= $statusFilter === 'PAID' ? 'selected' : '' ?>>PAID</option>
                    <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>PENDING</option>
                    <option value="FAILED" <?= $statusFilter === 'FAILED' ? 'selected' : '' ?>>FAILED</option>
                    <option value="CANCELLED" <?= $statusFilter === 'CANCELLED' ? 'selected' : '' ?>>CANCELLED</option>
                    <option value="REFUNDED" <?= $statusFilter === 'REFUNDED' ? 'selected' : '' ?>>REFUNDED</option>
                    <option value="PARTIALLY_REFUNDED" <?= $statusFilter === 'PARTIALLY_REFUNDED' ? 'selected' : '' ?>>PARTIALLY_REFUNDED</option>
                    <option value="REVIEW_REQUIRED" <?= $statusFilter === 'REVIEW_REQUIRED' ? 'selected' : '' ?>>REVIEW_REQUIRED</option>
                </select>
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">From Date</label>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
            </div>

            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;">To Date</label>
                <input type="date" name="date_to" value="<?= e($dateTo) ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
            </div>

            <div>
                <button type="submit" style="width: 100%; padding: 9px 16px; background: #6366f1; color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer;">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Payments Ledger Table -->
    <div style="background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11px;">
                    <th style="padding: 14px 16px;">Transaction ID</th>
                    <th style="padding: 14px 16px;">Order #</th>
                    <th style="padding: 14px 16px;">Customer</th>
                    <th style="padding: 14px 16px;">Method / Card</th>
                    <th style="padding: 14px 16px;">Amount</th>
                    <th style="padding: 14px 16px;">Status</th>
                    <th style="padding: 14px 16px;">Validation</th>
                    <th style="padding: 14px 16px;">Bank Ref</th>
                    <th style="padding: 14px 16px;">Date & Time</th>
                    <th style="padding: 14px 16px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="10" style="padding: 40px; text-align: center; color: #64748b;">
                            <i class="fas fa-receipt" style="font-size: 32px; margin-bottom: 10px; color: #cbd5e1;"></i>
                            <div>No payment transactions found matching your criteria.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): 
                        $statusClass = match($p['status']) {
                            'PAID' => 'background: #dcfce7; color: #166534;',
                            'PENDING', 'INITIATED', 'PROCESSING' => 'background: #fef3c7; color: #92400e;',
                            'FAILED', 'CANCELLED' => 'background: #fee2e2; color: #991b1b;',
                            'REFUNDED', 'PARTIALLY_REFUNDED' => 'background: #e0e7ff; color: #3730a3;',
                            'REVIEW_REQUIRED' => 'background: #fef08a; color: #854d0e; font-weight: 800;',
                            default => 'background: #f1f5f9; color: #475569;'
                        };
                    ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 16px; font-family: monospace; font-weight: 700; color: #0f172a;">
                                <a href="view.php?id=<?= $p['id'] ?>" style="color: #6366f1; text-decoration: none;">
                                    <?= e($p['tran_id']) ?>
                                </a>
                            </td>
                            <td style="padding: 12px 16px;">
                                <a href="../orders/view.php?id=<?= $p['order_id'] ?>" style="font-weight: 600; color: #334155; text-decoration: none;">
                                    #<?= e($p['order_number'] ?? $p['order_id']) ?>
                                </a>
                            </td>
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 600; color: #1e293b;"><?= e($p['customer_name'] ?? 'Guest') ?></div>
                                <div style="font-size: 11px; color: #64748b;"><?= e($p['customer_email'] ?? '') ?></div>
                            </td>
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 600; text-transform: uppercase;"><?= e($p['payment_method']) ?></div>
                                <?php if (!empty($p['card_type'])): ?>
                                    <div style="font-size: 11px; color: #64748b;"><?= e($p['card_type']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 16px; font-weight: 700;">
                                ৳<?= number_format((float)$p['amount'], 2) ?>
                                <?php if ((float)$p['refunded_amount'] > 0): ?>
                                    <div style="font-size: 11px; color: #dc2626;">Ref: ৳<?= number_format((float)$p['refunded_amount'], 2) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 16px;">
                                <span style="display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; <?= $statusClass ?>">
                                    <?= e($p['status']) ?>
                                </span>
                            </td>
                            <td style="padding: 12px 16px; font-size: 11px;">
                                <?php if ($p['verification_status'] === 'VALIDATED'): ?>
                                    <span style="color: #166534; font-weight: 700;"><i class="fas fa-check-circle"></i> Validated</span>
                                <?php elseif ($p['verification_status'] === 'AMOUNT_MISMATCH'): ?>
                                    <span style="color: #dc2626; font-weight: 800;"><i class="fas fa-triangle-exclamation"></i> Mismatch</span>
                                <?php else: ?>
                                    <span style="color: #64748b;"><?= e($p['verification_status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 16px; font-family: monospace; font-size: 11px; color: #475569;">
                                <?= e($p['bank_tran_id'] ?? '—') ?>
                            </td>
                            <td style="padding: 12px 16px; font-size: 12px; color: #64748b;">
                                <?= date('M d, Y H:i', strtotime($p['created_at'])) ?>
                            </td>
                            <td style="padding: 12px 16px; text-align: right;">
                                <a href="view.php?id=<?= $p['id'] ?>" style="display: inline-block; padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-weight: 600; text-decoration: none; font-size: 12px;">
                                    <i class="fas fa-eye"></i> Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="padding: 16px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 12px; color: #64748b;">
                    Showing page <?= $page ?> of <?= $totalPages ?> (Total <?= $totalRecords ?> transactions)
                </div>
                <div style="display: flex; gap: 6px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>" style="padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; text-decoration: none; color: #334155;">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>" style="padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; text-decoration: none; color: #334155;">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
