<?php
/**
 * ==============================================================================
 * public/includes/pos_autoload.php
 * ==============================================================================
 * Autoloading / Bootstrapping for Enterprise POS domain services
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/pos/PosAuditService.php';
require_once __DIR__ . '/pos/PosCartService.php';
require_once __DIR__ . '/pos/PosPaymentService.php';
require_once __DIR__ . '/pos/PosInventoryService.php';
require_once __DIR__ . '/pos/PosCustomerService.php';
require_once __DIR__ . '/pos/PosDiscountService.php';
require_once __DIR__ . '/pos/PosShiftService.php';
require_once __DIR__ . '/pos/PosTransactionService.php';
require_once __DIR__ . '/pos/PosReturnService.php';
require_once __DIR__ . '/pos/PosSyncService.php';
require_once __DIR__ . '/pos/PosReceiptService.php';
require_once __DIR__ . '/pos/PosService.php';

/**
 * Global helper to access the PosService singleton.
 */
function pos_service(): \Groco\Pos\PosService
{
    static $instance = null;
    if ($instance === null) {
        $instance = new \Groco\Pos\PosService(db());
    }
    return $instance;
}
