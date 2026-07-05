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

// Set correct plain text header
header('Content-Type: text/plain; charset=utf-8');

// Compile rules
$sitemapUrl = site_url('sitemap.php');

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /ajax/\n";
echo "Disallow: /api/\n";
echo "Disallow: /includes/\n";
echo "Disallow: /components/\n";
echo "\n";
echo "Sitemap: {$sitemapUrl}\n";
