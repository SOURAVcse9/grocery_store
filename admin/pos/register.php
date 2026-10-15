<?php
/**
 * ==========================================================================
 * admin/pos/register.php — Cash Register & Shift Drawer Controller (Extended)
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_auth();
require_admin_permission('pos.cash');
require_once __DIR__ . '/../includes/pos_lib.php';

$pdo = db();
$adminId = (int) current_admin_id();

// ==============================================================================
// 1. Standalone Printable Report View (X-Reading or Z-Reading Audit Sheet)
// ==============================================================================
// Intercept BEFORE dashboard_layout.php to prevent layout wrapping or 280px squashing.
$reportAction = (string) input('action', '', 'get');
if (in_array($reportAction, ['x_report', 'z_report', 'report'], true)) {
    $targetShiftId = (int) input('shift_id', '0', 'get');
    if ($targetShiftId > 0) {
        $stmtShift = $pdo->prepare("
            SELECT ps.*, a.username, a.full_name, a.email
            FROM pos_shifts ps
            JOIN admins a ON a.id = ps.admin_id
            WHERE ps.id = ?
            LIMIT 1
        ");
        $stmtShift->execute([$targetShiftId]);
        $reportShift = $stmtShift->fetch(PDO::FETCH_ASSOC);
    } else {
        // Default to active shift for current cashier
        $stmtShift = $pdo->prepare("
            SELECT ps.*, a.username, a.full_name, a.email
            FROM pos_shifts ps
            JOIN admins a ON a.id = ps.admin_id
            WHERE ps.admin_id = ? AND ps.status = 'open'
            ORDER BY ps.id DESC
            LIMIT 1
        ");
        $stmtShift->execute([$adminId]);
        $reportShift = $stmtShift->fetch(PDO::FETCH_ASSOC);

        if (!$reportShift) {
            // Fallback to most recent shift for current cashier
            $stmtShift = $pdo->prepare("
                SELECT ps.*, a.username, a.full_name, a.email
                FROM pos_shifts ps
                JOIN admins a ON a.id = ps.admin_id
                WHERE ps.admin_id = ?
                ORDER BY ps.id DESC
                LIMIT 1
            ");
            $stmtShift->execute([$adminId]);
            $reportShift = $stmtShift->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$reportShift) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>No Shift Found — GroCo Admin</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
            <link rel="stylesheet" href="<?= BASE_URL ?>/../admin/assets/css/admin.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        </head>
        <body style="background:#f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; font-family:'Inter',sans-serif; margin:0; padding:20px;">
            <div style="background:#fff; border-radius:12px; padding:32px; max-width:440px; text-align:center; box-shadow:0 10px 25px rgba(0,0,0,0.08); border:1px solid #e2e8f0;">
                <div style="font-size:36px; color:#f59e0b; margin-bottom:12px;"><i class="fas fa-cash-register"></i></div>
                <h3 style="margin:0 0 8px 0; font-size:18px; font-weight:800; color:#1e293b;">No Cash Register Shift Found</h3>
                <p style="color:#64748b; font-size:13px; margin:0 0 20px 0;">There is no open cash register shift or valid shift ID specified to generate an audit report.</p>
                <a href="register.php" class="btn btn-primary" style="display:inline-block; text-decoration:none; padding:10px 20px; border-radius:30px; font-weight:700; font-size:13px;">Return to Cash Register</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    $shiftSum = pos_shift_summary($pdo, $reportShift);
    $stmtTx = $pdo->prepare("SELECT * FROM pos_drawer_transactions WHERE shift_id = ? ORDER BY created_at ASC");
    $stmtTx->execute([$reportShift['id']]);
    $drawerTxLogs = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

    $isOpen = ($reportShift['status'] === 'open');
    $reportTitle = $isOpen ? 'REGISTER MID-DAY X-REPORT' : 'SHIFT Z-CLOSING AUDIT REPORT';
    $reportSubtitle = $isOpen ? 'Current In-Progress Shift Reading (Drawer Remains Open)' : 'Final Reconciled Shift Audit (Drawer Closed & Verified)';

    $openingCash = (float) $reportShift['opening_cash'];
    $cashSales = (float) $shiftSum['cash_sales'];
    $cashIn = (float) $shiftSum['cash_in'];
    $cashOut = (float) $shiftSum['cash_out'];
    $cashRefunds = (float) $shiftSum['cash_refunds'];
    $expectedCash = (float) $shiftSum['expected_cash'];
    $actualCash = $reportShift['actual_cash'] !== null ? (float) $reportShift['actual_cash'] : null;
    $discrepancy = $actualCash !== null ? ($actualCash - $expectedCash) : null;

    $nonCashTotal = (float) ($shiftSum['card'] + $shiftSum['bkash'] + $shiftSum['bank'] + $shiftSum['wallet']);
    $grossTurnover = (float) $shiftSum['gross_sales'];
    $txCount = (int) $shiftSum['sales_count'];
    $atv = $txCount > 0 ? ($grossTurnover / $txCount) : 0.0;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $reportTitle ?> — Shift #<?= $reportShift['id'] ?> — GroCo POS</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            :root {
                --primary: #047857;
                --primary-dark: #065f46;
                --border-color: #e2e8f0;
                --text-main: #0f172a;
                --text-muted: #64748b;
                --bg-paper: #ffffff;
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                padding: 0;
                background: #f1f5f9;
                font-family: 'Inter', system-ui, -apple-system, sans-serif;
                color: var(--text-main);
                font-size: 13px;
                line-height: 1.5;
            }

            /* Top Actions Toolbar (.no-print) */
            .top-toolbar {
                position: sticky;
                top: 0;
                z-index: 1000;
                background: #1e293b;
                color: #fff;
                padding: 12px 24px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                gap: 16px;
                flex-wrap: wrap;
            }
            .toolbar-group {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .btn-action {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 7px 14px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 700;
                cursor: pointer;
                text-decoration: none;
                border: 1px solid transparent;
                transition: all 0.15s ease;
            }
            .btn-back {
                background: rgba(255,255,255,0.12);
                color: #fff;
            }
            .btn-back:hover { background: rgba(255,255,255,0.2); color: #fff; }
            .btn-mode {
                background: transparent;
                color: #cbd5e1;
                border: 1px solid #475569;
            }
            .btn-mode.active, .btn-mode:hover {
                background: #3b82f6;
                color: #fff;
                border-color: #3b82f6;
            }
            .btn-print {
                background: #10b981;
                color: #fff;
            }
            .btn-print:hover { background: #059669; }

            /* Report Paper Container */
            .report-wrapper {
                padding: 30px 20px 60px 20px;
                display: flex;
                justify-content: center;
            }
            .report-container {
                background: var(--bg-paper);
                width: 100%;
                max-width: 820px;
                border-radius: 10px;
                padding: 40px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.06);
                border: 1px solid var(--border-color);
                transition: max-width 0.2s ease;
            }

            /* Mode 80mm Thermal Receipt */
            .report-container.mode-thermal {
                max-width: 320px;
                padding: 16px 12px;
                font-family: 'JetBrains Mono', 'Courier New', Courier, monospace;
                font-size: 11px;
                border-radius: 0;
                box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            }
            .report-container.mode-thermal .thermal-hide { display: none !important; }
            .report-container.mode-thermal .report-header h1 { font-size: 14px; margin-bottom: 2px; }
            .report-container.mode-thermal .report-header h2 { font-size: 12px; margin-bottom: 8px; }
            .report-container.mode-thermal .meta-grid { grid-template-columns: 1fr; gap: 4px; padding: 8px; margin-bottom: 12px; }
            .report-container.mode-thermal table { font-size: 11px; margin-bottom: 12px; }
            .report-container.mode-thermal th, .report-container.mode-thermal td { padding: 4px 2px; }
            .report-container.mode-thermal .section-title { font-size: 11px; margin: 10px 0 4px 0; border-bottom: 1px dashed #000; padding-bottom: 2px; }

            /* Typography & Header */
            .report-header {
                text-align: center;
                border-bottom: 2px solid #0f172a;
                padding-bottom: 16px;
                margin-bottom: 20px;
            }
            .report-header .store-title {
                font-size: 22px;
                font-weight: 900;
                letter-spacing: -0.5px;
                color: #0f172a;
                margin: 0 0 4px 0;
                text-transform: uppercase;
            }
            .report-header .store-sub {
                font-size: 12px;
                color: var(--text-muted);
                margin: 0 0 10px 0;
            }
            .report-header .report-badge {
                display: inline-block;
                padding: 4px 14px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 6px;
            }
            .badge-open { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; }
            .badge-closed { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }

            .report-header h1 {
                font-size: 18px;
                font-weight: 800;
                margin: 4px 0 2px 0;
                color: #0f172a;
            }
            .report-header .report-desc {
                font-size: 12px;
                color: var(--text-muted);
                margin: 0;
            }

            /* Meta Info Grid */
            .meta-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 12px;
                background: #f8fafc;
                border: 1px solid var(--border-color);
                border-radius: 8px;
                padding: 14px 18px;
                margin-bottom: 24px;
                font-size: 12px;
            }
            .meta-item { display: flex; flex-direction: column; }
            .meta-label { font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
            .meta-val { font-weight: 700; color: #0f172a; margin-top: 2px; }

            /* Section Tables */
            .section-title {
                font-size: 13px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #1e293b;
                margin: 22px 0 8px 0;
                padding-bottom: 6px;
                border-bottom: 1px solid #cbd5e1;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .section-title i { color: var(--primary); margin-right: 6px; }

            .audit-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 18px;
            }
            .audit-table th {
                text-align: left;
                padding: 8px 10px;
                background: #f8fafc;
                border-bottom: 1px solid #cbd5e1;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                color: #475569;
            }
            .audit-table td {
                padding: 8px 10px;
                border-bottom: 1px solid #f1f5f9;
                color: #1e293b;
            }
            .audit-table tr.total-row td {
                border-top: 2px solid #0f172a;
                border-bottom: 2px solid #0f172a;
                font-weight: 800;
                font-size: 13px;
                background: #f8fafc;
            }
            .text-right { text-align: right; }
            .text-center { text-align: center; }

            /* Highlight Boxes */
            .variance-box {
                padding: 12px 16px;
                border-radius: 8px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-weight: 800;
                margin: 14px 0 20px 0;
                border: 1px solid transparent;
            }
            .var-balanced { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
            .var-over { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
            .var-short { background: #fef2f2; border-color: #fecaca; color: #991b1b; }

            /* Signatures Block */
            .sign-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 40px;
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px dashed #cbd5e1;
            }
            .sign-line {
                border-top: 1px solid #0f172a;
                margin-top: 50px;
                padding-top: 6px;
                text-align: center;
                font-size: 11px;
                font-weight: 700;
                color: var(--text-muted);
            }

            /* Media Print Optimization */
            @media print {
                .no-print { display: none !important; }
                body {
                    background: #fff !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    color: #000 !important;
                }
                .report-wrapper { padding: 0 !important; }
                .report-container {
                    box-shadow: none !important;
                    border: none !important;
                    padding: 0 !important;
                    width: 100% !important;
                    max-width: 100% !important;
                }
                .report-container.mode-thermal {
                    width: 80mm !important;
                    max-width: 80mm !important;
                    padding: 2mm !important;
                }
                @page {
                    size: auto;
                    margin: 12mm 15mm;
                }
            }
        </style>
    </head>
    <body>

        <!-- Top Actions Toolbar -->
        <div class="top-toolbar no-print">
            <div class="toolbar-group">
                <a href="register.php" class="btn-action btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Register
                </a>
                <span style="font-size:12px; color:#94a3b8; margin-left:8px;">
                    Shift #<strong><?= $reportShift['id'] ?></strong> (<?= strtoupper($reportShift['status']) ?>)
                </span>
            </div>
            <div class="toolbar-group">
                <span style="font-size:11px; color:#cbd5e1; font-weight:600; text-transform:uppercase;">Format:</span>
                <button type="button" class="btn-action btn-mode active" id="btnModeA4" onclick="setMode('a4')">
                    <i class="fas fa-file-alt"></i> A4 Audit Sheet
                </button>
                <button type="button" class="btn-action btn-mode" id="btnModeThermal" onclick="setMode('thermal')">
                    <i class="fas fa-receipt"></i> 80mm Thermal
                </button>
                <button type="button" class="btn-action btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>

        <div class="report-wrapper">
            <div class="report-container" id="reportContainer">

                <!-- Header -->
                <div class="report-header">
                    <div class="store-title">GroCo Supermarket</div>
                    <div class="store-sub">Retail Point of Sale &bull; Cash Register Drawer Audit</div>
                    <div>
                        <span class="report-badge <?= $isOpen ? 'badge-open' : 'badge-closed' ?>">
                            <?= $isOpen ? '● ACTIVE SHIFT (MID-DAY READING)' : '✓ FINAL SHIFT AUDIT (CLOSED)' ?>
                        </span>
                    </div>
                    <h1><?= $reportTitle ?></h1>
                    <p class="report-desc"><?= $reportSubtitle ?></p>
                </div>

                <!-- Metadata Grid -->
                <div class="meta-grid">
                    <div class="meta-item">
                        <span class="meta-label">Shift ID</span>
                        <span class="meta-val">#<?= $reportShift['id'] ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Cashier Operator</span>
                        <span class="meta-val"><?= e($reportShift['full_name'] ?: $reportShift['username']) ?> (@<?= e($reportShift['username']) ?>)</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Terminal / Register</span>
                        <span class="meta-val"><?= !empty($reportShift['terminal_id']) ? e($reportShift['terminal_id']) : ('Register #' . ($reportShift['register_id'] ?? '1')) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Shift Opened</span>
                        <span class="meta-val"><?= date('M d, Y H:i:s', strtotime($reportShift['start_time'])) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label"><?= $isOpen ? 'Current Reading Time' : 'Shift Closed At' ?></span>
                        <span class="meta-val">
                            <?= !empty($reportShift['end_time']) ? date('M d, Y H:i:s', strtotime($reportShift['end_time'])) : date('M d, Y H:i:s') ?>
                        </span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Audit Printed By</span>
                        <span class="meta-val"><?= e(current_admin()['username'] ?? 'admin') ?> on <?= date('Y-m-d H:i') ?></span>
                    </div>
                </div>

                <!-- Section 1: Physical Cash Drawer Reconciliation -->
                <div class="section-title">
                    <span><i class="fas fa-coins"></i> 1. Physical Cash Drawer Reconciliation</span>
                    <span style="font-size:11px; font-weight:600; text-transform:none; color:var(--text-muted);">(Cash In Drawer Only)</span>
                </div>
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>Drawer Movement Description</th>
                            <th class="text-center" style="width:110px;">Operation</th>
                            <th class="text-right" style="width:150px;">Amount (৳)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Opening Drawer Float</strong> (Starting Cash Count)</td>
                            <td class="text-center"><span style="color:#059669; font-weight:700;">[ + ] Start</span></td>
                            <td class="text-right font-mono">৳<?= number_format($openingCash, 2) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Counter Cash Sales</strong> (Physical cash tendered by customers)</td>
                            <td class="text-center"><span style="color:#059669; font-weight:700;">[ + ] Add</span></td>
                            <td class="text-right font-mono">৳<?= number_format($cashSales, 2) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Drawer Cash-Ins</strong> (Float additions, petty cash deposit)</td>
                            <td class="text-center"><span style="color:#059669; font-weight:700;">[ + ] Inflow</span></td>
                            <td class="text-right font-mono">৳<?= number_format($cashIn, 2) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Drawer Cash-Outs</strong> (Safe drops, shift payouts, expenses)</td>
                            <td class="text-center"><span style="color:#dc2626; font-weight:700;">[ - ] Outflow</span></td>
                            <td class="text-right font-mono">-৳<?= number_format($cashOut, 2) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Cash Refunds Paid Out</strong> (Merchandise returns paid in cash)</td>
                            <td class="text-center"><span style="color:#dc2626; font-weight:700;">[ - ] Refund</span></td>
                            <td class="text-right font-mono">-৳<?= number_format($cashRefunds, 2) ?></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>EXPECTED PHYSICAL DRAWER CASH</strong></td>
                            <td class="text-center"><strong>[ = ] Expected</strong></td>
                            <td class="text-right font-mono"><strong>৳<?= number_format($expectedCash, 2) ?></strong></td>
                        </tr>
                        <?php if ($actualCash !== null): ?>
                            <tr>
                                <td><strong>Actual Counted Cash</strong> (Cashier physically counted in drawer)</td>
                                <td class="text-center"><strong>Counted</strong></td>
                                <td class="text-right font-mono"><strong>৳<?= number_format($actualCash, 2) ?></strong></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($actualCash !== null): 
                    $varClass = ($discrepancy == 0.0) ? 'var-balanced' : (($discrepancy > 0) ? 'var-over' : 'var-short');
                    $varText = ($discrepancy == 0.0) ? 'PERFECTLY BALANCED (৳0.00)' : (($discrepancy > 0) ? ('OVER BY +৳' . number_format($discrepancy, 2)) : ('SHORT BY -৳' . number_format(abs($discrepancy), 2)));
                ?>
                    <div class="variance-box <?= $varClass ?>">
                        <span><i class="fas fa-scale-balanced" style="margin-right:8px;"></i> DRAWER RECONCILIATION RESULT:</span>
                        <span style="font-size:15px;"><?= $varText ?></span>
                    </div>
                <?php else: ?>
                    <div style="background:#f8fafc; border:1px dashed #cbd5e1; padding:10px 14px; border-radius:6px; font-size:12px; color:var(--text-muted); margin-bottom:18px;">
                        <i class="fas fa-info-circle"></i> Active Shift: Physical drawer count is finalized at end-of-shift during register closing (Z-Report).
                    </div>
                <?php endif; ?>

                <!-- Section 2: Non-Cash Electronic Tenders -->
                <div class="section-title">
                    <span><i class="fas fa-credit-card"></i> 2. Non-Cash Electronic Tenders</span>
                    <span style="font-size:11px; font-weight:600; text-transform:none; color:var(--text-muted);">(Deposited Directly to Accounts, Not in Drawer)</span>
                </div>
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>Payment Channel / Tender Method</th>
                            <th class="text-center" style="width:110px;">Type</th>
                            <th class="text-right" style="width:150px;">Settled Volume (৳)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Credit / Debit Card (Visa, MasterCard, Amex, Nexus)</td>
                            <td class="text-center">Electronic</td>
                            <td class="text-right font-mono">৳<?= number_format((float)$shiftSum['card'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Mobile Financial Services (bKash, Nagad, Rocket)</td>
                            <td class="text-center">MFS / QR</td>
                            <td class="text-right font-mono">৳<?= number_format((float)$shiftSum['bkash'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Direct Bank Transfer (EFT / Wire / Online)</td>
                            <td class="text-center">Bank</td>
                            <td class="text-right font-mono">৳<?= number_format((float)$shiftSum['bank'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Customer Wallet Credits (Store credit redemption)</td>
                            <td class="text-center">Digital Wallet</td>
                            <td class="text-right font-mono">৳<?= number_format((float)$shiftSum['wallet'], 2) ?></td>
                        </tr>
                        <tr class="total-row">
                            <td colspan="2"><strong>TOTAL NON-CASH ELECTRONIC TENDERS</strong></td>
                            <td class="text-right font-mono"><strong>৳<?= number_format($nonCashTotal, 2) ?></strong></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Section 3: Shift Turnover & Volume Summary -->
                <div class="section-title">
                    <span><i class="fas fa-chart-line"></i> 3. Shift Turnover &amp; Basket Analytics</span>
                    <span style="font-size:11px; font-weight:600; text-transform:none; color:var(--text-muted);">(Ref: Supermarket Cashier Metrics)</span>
                </div>
                <table class="audit-table">
                    <tbody>
                        <tr>
                            <td><strong>Total Gross Shift Turnover</strong> (Cash + Electronic Sales)</td>
                            <td class="text-right font-mono" style="font-size:14px; font-weight:800; color:var(--primary);">
                                ৳<?= number_format($grossTurnover, 2) ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Total Completed Paid Transactions</td>
                            <td class="text-right font-mono"><strong><?= $txCount ?></strong> receipts</td>
                        </tr>
                        <tr>
                            <td>Average Transaction Value (ATV / Average Basket Size)</td>
                            <td class="text-right font-mono">৳<?= number_format($atv, 2) ?></td>
                        </tr>
                        <tr>
                            <td>Voided / Cancelled Orders During Shift Window</td>
                            <td class="text-right font-mono" style="color:<?= $shiftSum['voided_count'] > 0 ? '#dc2626' : 'inherit' ?>;">
                                <?= (int)$shiftSum['voided_count'] ?> void(s) &bull; ৳<?= number_format((float)$shiftSum['voided_total'], 2) ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Section 4: Itemized Cash Drawer Movements Log -->
                <?php if (!empty($drawerTxLogs)): ?>
                    <div class="section-title">
                        <span><i class="fas fa-list-check"></i> 4. Itemized Drawer Cash Movements Log</span>
                        <span style="font-size:11px; font-weight:600; text-transform:none; color:var(--text-muted);">(<?= count($drawerTxLogs) ?> recorded)</span>
                    </div>
                    <table class="audit-table" style="font-size:12px;">
                        <thead>
                            <tr>
                                <th style="width:90px;">Time</th>
                                <th style="width:110px;">Type</th>
                                <th>Reason / Reference Notes</th>
                                <th class="text-right" style="width:120px;">Amount (৳)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drawerTxLogs as $tx): 
                                $isTxIn = ($tx['type'] === 'cash_in');
                            ?>
                                <tr>
                                    <td class="font-mono"><?= date('H:i:s', strtotime($tx['created_at'])) ?></td>
                                    <td>
                                        <strong style="color:<?= $isTxIn ? '#059669' : '#dc2626' ?>;">
                                            <?= $isTxIn ? 'CASH-IN' : 'CASH-OUT' ?>
                                        </strong>
                                    </td>
                                    <td style="color:var(--text-muted);"><?= e($tx['notes'] ?: 'No notes provided') ?></td>
                                    <td class="text-right font-mono" style="font-weight:700; color:<?= $isTxIn ? '#059669' : '#dc2626' ?>;">
                                        <?= $isTxIn ? '+' : '-' ?>৳<?= number_format((float)$tx['amount'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Section 5: Verification & Signatures -->
                <div class="sign-grid thermal-hide">
                    <div>
                        <div class="sign-line">
                            CASHIER SIGNATURE (<?= e($reportShift['full_name'] ?: $reportShift['username']) ?>)
                        </div>
                    </div>
                    <div>
                        <div class="sign-line">
                            DUTY SUPERVISOR / MANAGER SIGNATURE
                        </div>
                    </div>
                </div>

                <div class="text-center" style="margin-top:30px; font-size:11px; color:var(--text-muted); border-top:1px solid #f1f5f9; padding-top:12px;">
                    GroCo Supermarket Retail POS Audit Document &bull; Generated <?= date('Y-m-d H:i:s') ?> &bull; System Shift #<?= $reportShift['id'] ?>
                </div>

            </div>
        </div>

        <script>
        function setMode(mode) {
            const container = document.getElementById('reportContainer');
            const btnA4 = document.getElementById('btnModeA4');
            const btnThermal = document.getElementById('btnModeThermal');
            if (!container) return;

            if (mode === 'thermal') {
                container.classList.add('mode-thermal');
                if (btnThermal) btnThermal.classList.add('active');
                if (btnA4) btnA4.classList.remove('active');
            } else {
                container.classList.remove('mode-thermal');
                if (btnA4) btnA4.classList.add('active');
                if (btnThermal) btnThermal.classList.remove('active');
            }
        }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ==============================================================================
// 2. Standard Admin Dashboard View: Cash Register Controller
// ==============================================================================
$error = null;
$success = null;

// Handle Cash In / Cash Out Drawer transaction
$activeShift = pos_active_shift($pdo, $adminId);
if (method_is('post') && input('pos_action', '') === 'drawer_tx' && $activeShift) {
    verify_csrf_or_fail();
    try {
        pos_drawer_tx($pdo, (int) $adminId, (string) input('tx_type', ''), (float) input('amount', '0'), (string) input('notes', ''));
        log_admin_activity('pos.drawer_tx', 'Logged register drawer ' . input('tx_type', '') . ' of ৳' . (float) input('amount', '0'));
        flash('pos_msg', 'Register drawer transaction logged successfully!', 'success');
        header('Location: register.php');
        exit;
    } catch (PosException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('[admin/pos/register] drawer tx failed: ' . $e->getMessage());
        $error = 'Failed to record drawer transaction.';
    }
}

// Handle Opening Shift
if (method_is('post') && input('pos_action', '') === 'open_shift') {
    verify_csrf_or_fail();
    try {
        $newShiftId = pos_open_shift($pdo, (int) $adminId, (float) input('opening_cash', '0'));
        log_admin_activity('pos.open_shift', 'Opened cash register shift #' . $newShiftId . ' with opening cash ৳' . (float) input('opening_cash', '0'));
        flash('pos_msg', 'Cash Register opened successfully!', 'success');
        header('Location: index.php');
        exit;
    } catch (PosException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('[admin/pos/register] open failed: ' . $e->getMessage());
        $error = 'Failed to open register.';
    }
}

// Handle Closing Shift (Z-Report)
if (method_is('post') && input('pos_action', '') === 'close_shift' && $activeShift) {
    verify_csrf_or_fail();
    try {
        $closed = pos_close_shift($pdo, (int) $adminId, (float) input('actual_cash', '0'));
        $diff = $closed['difference'];
        log_admin_activity('pos.close_shift', "Closed shift #{$closed['shift_id']}: expected ৳{$closed['expected']}, counted ৳{$closed['actual']}, difference ৳{$diff}");
        flash('pos_msg', 'Shift closed. Expected ৳' . number_format($closed['expected'], 2) . ', counted ৳' . number_format($closed['actual'], 2) . ', difference ' . ($diff >= 0 ? '+' : '-') . '৳' . number_format(abs($diff), 2), $diff == 0.0 ? 'success' : 'warning');
        header('Location: index.php');
        exit;
    } catch (PosException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('[admin/pos/register] close shift failed: ' . $e->getMessage());
        $error = 'Failed to close register due to a database error.';
    }
}

// Active shift calculations
$shiftSales = 0.0;
$cashInflow = 0.0;
$cashOutflow = 0.0;
$drawerTxLogs = [];
$shiftSum = null;

if ($activeShift) {
    $shiftSum = pos_shift_summary($pdo, $activeShift);
    $shiftSales = $shiftSum['cash_sales'];
    $cashInflow = $shiftSum['cash_in'];
    $cashOutflow = $shiftSum['cash_out'];

    $stmtTx = $pdo->prepare("SELECT * FROM pos_drawer_transactions WHERE shift_id = ? ORDER BY created_at DESC");
    $stmtTx->execute([$activeShift['id']]);
    $drawerTxLogs = $stmtTx->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch past shifts log history
try {
    $shifts = $pdo->query("
        SELECT ps.*, a.username 
        FROM pos_shifts ps
        JOIN admins a ON a.id = ps.admin_id
        ORDER BY ps.created_at DESC 
        LIMIT 25
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[admin/pos/register] history load failed: ' . $e->getMessage());
    $shifts = [];
}

$pageTitle = 'Cash Register Management — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Register Drawer &amp; Shift Controller</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Manage cash registers, log drawer cash-ins / safe drops, and generate corporate X/Z audits.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> POS Terminal</a>
</div>

<!-- Alerts -->
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1.3fr 2.7fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left Column: Open/Close Drawer Form & Cash In/Out -->
    <div style="display:flex; flex-direction:column; gap:16px;">
        <div class="dashboard-card" style="padding:var(--space-5); margin:0;">
            <?php if ($activeShift): ?>
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Reconcile &amp; Close Shift</h3>
                
                <div style="font-size:13px; color:var(--color-text-muted); display:flex; flex-direction:column; gap:10px; margin-bottom:20px;">
                    <div style="display:flex; justify-content:space-between;">
                        <span>Shift Started:</span>
                        <strong><?= date('M d, H:i', strtotime($activeShift['start_time'])) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>Opening Cash:</span>
                        <strong>৳<?= number_format((float)$activeShift['opening_cash'], 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#0ca678;">
                        <span>Cash Sales:</span>
                        <strong>+৳<?= number_format($shiftSales, 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#0ca678;">
                        <span>Drawer Inflow:</span>
                        <strong>+৳<?= number_format($cashInflow, 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#e03131;">
                        <span>Drawer Outflow:</span>
                        <strong>-৳<?= number_format($cashOutflow, 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; color:#e03131;">
                        <span>Cash Refunds:</span>
                        <strong>-৳<?= number_format((float)$shiftSum['cash_refunds'], 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:12px;">
                        <span>Card / Mobile / Bank / Wallet (Not in drawer):</span>
                        <strong>৳<?= number_format((float)($shiftSum['card'] + $shiftSum['bkash'] + $shiftSum['bank'] + $shiftSum['wallet']), 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; border-top:1px dashed var(--color-border); padding-top:8px; font-size:14px; color:var(--color-text);">
                        <span>Expected Drawer Cash:</span>
                        <strong style="color:var(--color-primary);">৳<?= number_format((float)$shiftSum['expected_cash'], 2) ?></strong>
                    </div>
                </div>

                <form method="post" class="auth-form" style="margin-bottom:12px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="pos_action" value="close_shift">
                    
                    <div class="form-field-group">
                        <label style="font-weight:700;">Counted Actual Cash (৳) *</label>
                        <input type="number" name="actual_cash" step="0.01" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; background:#f03e3e; font-size:13px;"><i class="fas fa-cash-register"></i> Close Register (Z-Report)</button>
                </form>

                <a href="?action=x_report" target="_blank" class="btn btn-secondary" style="width:100%; text-align:center; font-weight:700; text-decoration:none; padding:10px; border-radius:var(--radius-pill); font-size:12px; display:block;"><i class="fas fa-print"></i> View Mid-day X-Report</a>
            <?php else: ?>
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Open New Cash Register</h3>
                
                <form method="post" class="auth-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="pos_action" value="open_shift">

                    <div class="form-field-group">
                        <label style="font-weight:700;">Opening Drawer Cash (৳) *</label>
                        <input type="number" name="opening_cash" step="0.01" value="1000.00" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-cash-register"></i> Open Cash Drawer</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($activeShift): ?>
            <!-- Cash In / Cash Out drawer transactions form -->
            <div class="dashboard-card" style="padding:var(--space-5); margin:0;">
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Log Drawer Cash In / Out</h3>
                <form method="post" class="auth-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="pos_action" value="drawer_tx">

                    <div class="form-field-group">
                        <label style="font-weight:700;">Type of entry *</label>
                        <select name="tx_type" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; background:var(--color-surface); color:var(--color-text);">
                            <option value="cash_in">Cash-In (Add Drawer Change)</option>
                            <option value="cash_out">Cash-Out (Safe Drop / Drawer Payout)</option>
                        </select>
                    </div>

                    <div class="form-field-group">
                        <label style="font-weight:700;">Amount (৳) *</label>
                        <input type="number" name="amount" step="0.01" required style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px;">
                    </div>

                    <div class="form-field-group">
                        <label style="font-weight:700;">Notes / Reference</label>
                        <input type="text" name="notes" placeholder="E.g. Safe Drop at 3PM" style="width:100%; padding:8px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px;">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; padding:10px; border-radius:var(--radius-pill); font-weight:700;">Log Drawer transaction</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Historic shifts log table -->
    <div style="display:flex; flex-direction:column; gap:16px;">
        <!-- Drawer Transactions List -->
        <?php if ($activeShift && !empty($drawerTxLogs)): ?>
            <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
                <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
                    <h3 style="font-size:14px; font-weight:800; margin:0;">Active Shift Drawer Log entries</h3>
                </div>
                <div class="admin-table-wrapper" style="border:none;">
                    <table class="admin-data-table" style="font-size:12px;">
                        <thead>
                            <tr>
                                <th style="padding:10px;">Type</th>
                                <th style="padding:10px; text-align:right;">Amount</th>
                                <th style="padding:10px;">Reference Notes</th>
                                <th style="padding:10px;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drawerTxLogs as $tx): ?>
                                <tr style="border-bottom:1px solid var(--color-border);">
                                    <td style="padding:8px; font-weight:700; color:<?= ($tx['type'] === 'cash_in') ? '#0ca678' : '#e03131' ?>;"><?= strtoupper(str_replace('_', ' ', $tx['type'])) ?></td>
                                    <td style="padding:8px; text-align:right; font-weight:700;">৳<?= number_format((float)$tx['amount'], 2) ?></td>
                                    <td style="padding:8px; color:var(--color-text-muted);"><?= e($tx['notes']) ?></td>
                                    <td style="padding:8px; color:var(--color-text-faint);"><?= date('H:i', strtotime($tx['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Shift History -->
        <div class="dashboard-card" style="padding:0; overflow:hidden; margin:0;">
            <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
                <h3 style="font-size:14px; font-weight:800; margin:0;">POS Cash Register Shifts History</h3>
            </div>
            <div class="admin-table-wrapper" style="border:none;">
                <table class="admin-data-table" style="font-size:12px;">
                    <thead>
                        <tr>
                            <th style="padding:10px 15px;">Cashier Operator</th>
                            <th style="padding:10px 15px; text-align:right;">Opening</th>
                            <th style="padding:10px 15px; text-align:right;">Expected</th>
                            <th style="padding:10px 15px; text-align:right;">Counted</th>
                            <th style="padding:10px 15px; text-align:center;">Variance</th>
                            <th style="padding:10px 15px;">Shift Date</th>
                            <th style="padding:10px 15px; text-align:center;">Audit Report</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($shifts)): ?>
                            <?php foreach ($shifts as $row): 
                                $expected = (float)$row['closing_cash'];
                                $actual = (float)$row['actual_cash'];
                                $diff = $actual - $expected;
                                
                                $color = '#339af0';
                                if ($row['status'] === 'closed') {
                                    $color = ($diff === 0.0) ? '#0ca678' : '#e03131';
                                }
                            ?>
                                <tr style="border-bottom:1px solid var(--color-border);">
                                    <td style="padding:8px 15px;"><strong>@<?= e($row['username']) ?></strong></td>
                                    <td style="padding:8px 15px; text-align:right;">৳<?= number_format((float)$row['opening_cash'], 2) ?></td>
                                    <td style="padding:8px 15px; text-align:right;">৳<?= number_format($expected, 2) ?></td>
                                    <td style="padding:8px 15px; text-align:right; font-weight:700;">৳<?= number_format($actual, 2) ?></td>
                                    <td style="padding:8px 15px; text-align:center; font-weight:800; color:<?= $color ?>;">
                                        <?php if ($row['status'] === 'open'): ?>
                                            <span class="status-pill pill-pending" style="font-size:8px;">ACTIVE SHIFT</span>
                                        <?php else: ?>
                                            <?= ($diff >= 0) ? '+' : '' ?>৳<?= number_format($diff, 2) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:8px 15px; color:var(--color-text-faint);"><?= date('M d, H:i', strtotime($row['start_time'])) ?></td>
                                    <td style="padding:8px 15px; text-align:center;">
                                        <a href="register.php?action=<?= $row['status'] === 'open' ? 'x_report' : 'z_report' ?>&shift_id=<?= (int)$row['id'] ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding:3px 8px; font-size:11px; border-radius:var(--radius-pill); font-weight:700; white-space:nowrap;" title="View Shift Audit Report">
                                            <i class="fas fa-file-invoice"></i> <?= $row['status'] === 'open' ? 'X-Report' : 'Z-Report' ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="padding:20px; text-align:center; color:var(--color-text-faint);">No historic cashier drawer shifts logged.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
