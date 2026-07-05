<?php
/**
 * ==========================================================================
 * public/components/search-result-card.php
 * ==========================================================================
 * Search results card component wrapper.
 * Reuses the central product-card.php template to avoid code duplication.
 * ==========================================================================
 */

declare(strict_types=1);

if (isset($product) && is_array($product)) {
    include PUBLIC_PATH . '/components/product-card.php';
}
