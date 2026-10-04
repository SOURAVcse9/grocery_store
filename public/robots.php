<?php
/**
 * ==========================================================================
 * public/robots.php — Dynamic Robots.txt Generator
 * ==========================================================================
 * Outputs dynamic instruction sets for search engine indexers.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/includes/seo.php';

// Set correct plain text header
if (!headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

$baseUrl = get_canonical_base_url();
$sitemapUrl = $baseUrl . '/sitemap.xml';

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Allow: /product/\n";
echo "Allow: /category/\n";
echo "Allow: /brand/\n";
echo "Allow: /product.php\n";
echo "Allow: /products.php\n";
echo "Allow: /assets/\n";
echo "Allow: /uploads/\n\n";

echo "# Disallow Administrative & Internal Folders\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n";
echo "Disallow: /ajax/\n";
echo "Disallow: /includes/\n";
echo "Disallow: /components/\n";
echo "Disallow: /licensing_server/\n";
echo "Disallow: /tests/\n\n";

echo "# Disallow Customer Private Session & Checkout Routes\n";
echo "Disallow: /cart.php\n";
echo "Disallow: /checkout.php\n";
echo "Disallow: /thank-you.php\n";
echo "Disallow: /account.php\n";
echo "Disallow: /profile.php\n";
echo "Disallow: /orders.php\n";
echo "Disallow: /order-details.php\n";
echo "Disallow: /wishlist.php\n";
echo "Disallow: /compare.php\n";
echo "Disallow: /login.php\n";
echo "Disallow: /register.php\n";
echo "Disallow: /forgot-password.php\n";
echo "Disallow: /reset-password.php\n";
echo "Disallow: /activate.php\n";
echo "Disallow: /offline.php\n\n";

echo "# Disallow Search Results & Filter Duplicates\n";
echo "Disallow: /search\n";
echo "Disallow: /search.php\n";
echo "Disallow: /*?*sort=\n";
echo "Disallow: /*?*filter=\n";
echo "Disallow: /*?*min_price=\n";
echo "Disallow: /*?*max_price=\n\n";

echo "Sitemap: {$sitemapUrl}\n";
