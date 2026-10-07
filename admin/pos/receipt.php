<?php
/**
 * admin/pos/receipt.php — legacy URL kept for compatibility.
 * The old copy hardcoded store details and allowed printing ANY order by id.
 * It now forwards to the single hardened renderer in receipts.php.
 */
declare(strict_types=1);
$id = (int) ($_GET['id'] ?? 0);
header('Location: receipts.php?id=' . $id . (isset($_GET['w']) ? '&w=' . rawurlencode((string) $_GET['w']) : ''));
exit;
