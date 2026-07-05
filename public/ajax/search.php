<?php
/**
 * ==========================================================================
 * public/ajax/search.php
 * ==========================================================================
 * Autocomplete/live search suggestions endpoint.
 * Called by app.js when users type in the header search input.
 * Responds with JSON.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only GET allowed
require_method('GET');

$query = trim(input('q', '', 'get'));

if (mb_strlen($query) < 2) {
    json_response(true, 'Query too short.', ['products' => []]);
}

try {
    $pdo = db();
    
    // Match product name, SKU, or brand
    $stmt = $pdo->prepare('
        SELECT p.name, p.slug, p.thumbnail 
        FROM products p
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.is_active = 1 
          AND (p.name LIKE :q1 
               OR p.sku LIKE :q2 
               OR b.name LIKE :q3 
               OR c.name LIKE :q4)
        ORDER BY p.is_featured DESC, p.name ASC
        LIMIT 8
    ');
    $term = '%' . $query . '%';
    $stmt->execute([
        'q1' => $term,
        'q2' => $term,
        'q3' => $term,
        'q4' => $term
    ]);
    $products = $stmt->fetchAll();

    $suggestions = [];
    foreach ($products as $p) {
        $suggestions[] = [
            'name'      => $p['name'],
            'slug'      => $p['slug'],
            'thumbnail' => image_url($p['thumbnail'], 'products')
        ];
    }

    json_response(true, 'Suggestions retrieved.', [
        'products' => $suggestions
    ]);

} catch (PDOException $e) {
    error_log('[ajax/search.php] Error: ' . $e->getMessage());
    json_response(false, 'Database error.', ['products' => []], 500);
}
