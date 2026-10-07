<?php
/**
 * admin/pos/ajax/process_sale.php — legacy endpoint kept for URL compatibility.
 * It previously duplicated checkout.php (and lacked its permission checks). It now
 * delegates to the single hardened implementation so there is ONE code path.
 */

declare(strict_types=1);

require __DIR__ . '/../checkout.php';
