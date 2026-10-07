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

$pageTitle = 'Payments & Gateways Ledger — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
?>

<!-- Header Toolbar -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0; display:flex; align-items:center; gap:10px;">
            <i class="fas fa-credit-card" style="color:var(--color-primary);"></i> Payment Gateway Transactions
        </h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">
            Authoritative online transactions, validation status, audit records, and settlement tracking.
        </p>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <a href="reconciliation.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
            <i class="fas fa-scale-balanced" style="color:var(--color-primary);"></i> Reconciliation Dashboard
        </a>
    </div>
</div>

<!-- Summary Metrics Cards -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:16px; margin-bottom:var(--space-5);">
    <div class="dashboard-card" style="padding:20px; margin:0; border-radius:var(--radius-lg); position:relative; overflow:hidden;">
        <div style="font-size:11px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.5px;">Total Online Collected</div>
        <div style="font-size:24px; font-weight:800; color:#10b981; margin-top:8px;">৳<?= number_format((float)$metrics['total_volume'], 2) ?></div>
        <div style="font-size:12px; color:var(--color-text-muted); margin-top:4px;">
            <i class="fas fa-circle-check" style="color:#10b981;"></i> <?= (int)$metrics['count_paid'] ?> Successful Transactions
        </div>
    </div>

    <div class="dashboard-card" style="padding:20px; margin:0; border-radius:var(--radius-lg); position:relative; overflow:hidden;">
        <div style="font-size:11px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.5px;">Pending / In-Flight</div>
        <div style="font-size:24px; font-weight:800; color:#f59e0b; margin-top:8px;"><?= (int)$metrics['count_pending'] ?></div>
        <div style="font-size:12px; color:var(--color-text-muted); margin-top:4px;">
            <i class="fas fa-clock" style="color:#f59e0b;"></i> Awaiting gateway verification
        </div>
    </div>

    <div class="dashboard-card" style="padding:20px; margin:0; border-radius:var(--radius-lg); position:relative; overflow:hidden;">
        <div style="font-size:11px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.5px;">Failed / Declined</div>
        <div style="font-size:24px; font-weight:800; color:#ef4444; margin-top:8px;"><?= (int)$metrics['count_failed'] ?></div>
        <div style="font-size:12px; color:var(--color-text-muted); margin-top:4px;">
            <i class="fas fa-circle-xmark" style="color:#ef4444;"></i> Declined by customer or bank
        </div>
    </div>

    <div class="dashboard-card" style="padding:20px; margin:0; border-radius:var(--radius-lg); position:relative; overflow:hidden;">
        <div style="font-size:11px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; letter-spacing:0.5px;">Total Refunded</div>
        <div style="font-size:24px; font-weight:800; color:var(--color-text); margin-top:8px;">৳<?= number_format((float)$metrics['total_refunded'], 2) ?></div>
        <div style="font-size:12px; color:var(--color-text-muted); margin-top:4px;">
            <i class="fas fa-rotate-left" style="color:var(--color-primary);"></i> Returned to customers
        </div>
    </div>
</div>

<!-- Filters Form -->
<div class="dashboard-card" style="padding:var(--space-4); margin-bottom:var(--space-5);">
    <form method="get" style="display:grid; grid-template-columns: 2fr 1.2fr 1fr 1fr auto; gap:12px; align-items:end;">
        <div>
            <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; display:block; margin-bottom:4px;">Search Keyword</label>
            <input type="text" name="search" placeholder="Tran ID, Bank ID, Val ID, Order #, Customer..." value="<?= e($search) ?>" style="width:100%; padding:9px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; background:var(--color-surface); color:var(--color-text);">
        </div>

        <div>
            <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; display:block; margin-bottom:4px;">Payment Status</label>
            <select name="status" style="width:100%; padding:9px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; background:var(--color-surface); color:var(--color-text);">
                <option value="">All Statuses</option>
                <option value="PAID" <?= $statusFilter === 'PAID' ? 'selected' : '' ?>>PAID (Captured)</option>
                <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>PENDING (In-Flight)</option>
                <option value="FAILED" <?= $statusFilter === 'FAILED' ? 'selected' : '' ?>>FAILED (Declined)</option>
                <option value="CANCELLED" <?= $statusFilter === 'CANCELLED' ? 'selected' : '' ?>>CANCELLED</option>
                <option value="REFUNDED" <?= $statusFilter === 'REFUNDED' ? 'selected' : '' ?>>REFUNDED</option>
                <option value="PARTIALLY_REFUNDED" <?= $statusFilter === 'PARTIALLY_REFUNDED' ? 'selected' : '' ?>>PARTIALLY REFUNDED</option>
                <option value="REVIEW_REQUIRED" <?= $statusFilter === 'REVIEW_REQUIRED' ? 'selected' : '' ?>>REVIEW REQUIRED</option>
            </select>
        </div>

        <div>
            <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; display:block; margin-bottom:4px;">From Date</label>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>" style="width:100%; padding:9px 10px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; background:var(--color-surface); color:var(--color-text);">
        </div>

        <div>
            <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase; display:block; margin-bottom:4px;">To Date</label>
            <input type="date" name="date_to" value="<?= e($dateTo) ?>" style="width:100%; padding:9px 10px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; outline:none; background:var(--color-surface); color:var(--color-text);">
        </div>

        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary" style="padding:9px 18px; border:none; border-radius:var(--radius-pill); font-weight:700; cursor:pointer;">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="index.php" class="btn btn-secondary" style="padding:9px 14px; border-radius:var(--radius-pill); text-decoration:none; font-weight:700; display:inline-flex; align-items:center;">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Transactions Table Card -->
<div class="dashboard-card" style="padding:0; overflow:hidden; border-radius:var(--radius-lg); margin-bottom:var(--space-6);">
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
            <thead>
                <tr style="background:rgba(0,0,0,0.02); border-bottom:1px solid var(--color-border); color:var(--color-text-muted); font-size:11px; text-transform:uppercase; letter-spacing:0.5px;">
                    <th style="padding:14px 16px;">Transaction ID</th>
                    <th style="padding:14px 16px;">Order #</th>
                    <th style="padding:14px 16px;">Customer</th>
                    <th style="padding:14px 16px;">Method / Card</th>
                    <th style="padding:14px 16px;">Amount</th>
                    <th style="padding:14px 16px;">Status</th>
                    <th style="padding:14px 16px;">Val ID</th>
                    <th style="padding:14px 16px;">Date & Time</th>
                    <th style="padding:14px 16px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="9" style="padding:40px; text-align:center; color:var(--color-text-muted);">
                            <i class="fas fa-receipt" style="font-size:32px; margin-bottom:8px; opacity:0.4; display:block;"></i>
                            No transaction records matching the specified criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                        <?php
                        $statusBadgeColor = '#64748b';
                        $statusBg = 'rgba(100,116,139,0.1)';
                        if ($p['status'] === 'PAID') {
                            $statusBadgeColor = '#059669';
                            $statusBg = 'rgba(16,185,129,0.12)';
                        } elseif (in_array($p['status'], ['PENDING', 'INITIATED'])) {
                            $statusBadgeColor = '#d97706';
                            $statusBg = 'rgba(245,158,11,0.12)';
                        } elseif ($p['status'] === 'FAILED') {
                            $statusBadgeColor = '#dc2626';
                            $statusBg = 'rgba(239,68,68,0.12)';
                        } elseif ($p['status'] === 'CANCELLED') {
                            $statusBadgeColor = '#e11d48';
                            $statusBg = 'rgba(225,29,72,0.12)';
                        } elseif (in_array($p['status'], ['REFUNDED', 'PARTIALLY_REFUNDED'])) {
                            $statusBadgeColor = '#4f46e5';
                            $statusBg = 'rgba(79,70,229,0.12)';
                        } elseif ($p['status'] === 'REVIEW_REQUIRED') {
                            $statusBadgeColor = '#b91c1c';
                            $statusBg = 'rgba(185,28,28,0.15)';
                        }
                        ?>
                        <tr style="border-bottom:1px solid var(--color-border); transition:background 0.15s ease;" onmouseover="this.style.background='rgba(0,0,0,0.015)'" onmouseout="this.style.background='transparent'">
                            <td style="padding:14px 16px; font-family:monospace; font-weight:700; color:var(--color-primary);">
                                <?= e($p['tran_id']) ?>
                            </td>
                            <td style="padding:14px 16px; font-weight:600; color:var(--color-text);">
                                <?php if ($p['order_number']): ?>
                                    <a href="../orders/view.php?id=<?= $p['order_id'] ?>" style="color:var(--color-text); text-decoration:none;">
                                        #<?= e($p['order_number']) ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--color-text-muted);">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:14px 16px;">
                                <div style="font-weight:600; color:var(--color-text);"><?= e($p['customer_name'] ?: 'Guest') ?></div>
                                <div style="font-size:11px; color:var(--color-text-muted);"><?= e($p['customer_email'] ?: '-') ?></div>
                            </td>
                            <td style="padding:14px 16px;">
                                <span style="font-size:11px; font-weight:700; color:var(--color-text); background:rgba(0,0,0,0.05); padding:3px 8px; border-radius:4px; text-transform:uppercase;">
                                    <?= e($p['card_type'] ?: $p['payment_method'] ?: 'SSLCOMMERZ') ?>
                                </span>
                            </td>
                            <td style="padding:14px 16px; font-weight:700; color:var(--color-text);">
                                ৳<?= number_format((float)$p['amount'], 2) ?>
                            </td>
                            <td style="padding:14px 16px;">
                                <span style="display:inline-block; padding:4px 8px; border-radius:var(--radius-pill); font-size:11px; font-weight:700; color:<?= $statusBadgeColor ?>; background:<?= $statusBg ?>;">
                                    <?= e($p['status']) ?>
                                </span>
                            </td>
                            <td style="padding:14px 16px; font-family:monospace; font-size:11px; color:var(--color-text-muted);">
                                <?= e($p['val_id'] ?: '—') ?>
                            </td>
                            <td style="padding:14px 16px; font-size:12px; color:var(--color-text-muted);">
                                <?= date('M d, Y H:i', strtotime($p['created_at'])) ?>
                            </td>
                            <td style="padding:14px 16px; text-align:right;">
                                <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-secondary" style="padding:6px 12px; border-radius:var(--radius-pill); font-size:11px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                                    <i class="fas fa-eye"></i> Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="padding:16px; border-top:1px solid var(--color-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div style="font-size:12px; color:var(--color-text-muted);">
                Showing page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (Total <?= $totalRecords ?> transactions)
            </div>
            <div style="display:flex; gap:6px;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-secondary" style="padding:6px 12px; border-radius:var(--radius-pill); font-size:12px; text-decoration:none;">Previous</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-secondary" style="padding:6px 12px; border-radius:var(--radius-pill); font-size:12px; text-decoration:none;">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
