<?php
/**
 * ==========================================================================
 * public/api/categories.php
 * ==========================================================================
 * API endpoint to fetch active categories.
 * Responds with JSON.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only GET allowed
require_method('GET');

try {
    $pdo = db();

    $stmt = $pdo->query('
        SELECT id, parent_id, name, slug, image, description 
        FROM categories 
        WHERE is_active = 1 
        ORDER BY name ASC
    ');
    $categories = $stmt->fetchAll();

    json_response(true, 'Categories retrieved successfully.', [
        'categories' => $categories
    ]);

} catch (PDOException $e) {
    error_log('[api/categories.php] Error: ' . $e->getMessage());
    json_response(false, 'An error occurred while fetching categories.', [], 500);
}
