<?php
/**
 * ==============================================================================
 * GroCo Product Service Layer
 * ==============================================================================
 * Centralized business logic for product catalog, search, caching, and inventory.
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/CacheService.php';
require_once __DIR__ . '/MediaService.php';

class ProductService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Retrieve single product by ID with category and brand metadata
     */
    public function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return CacheService::remember("product:id:{$id}", CacheService::TTL_MEDIUM, function() use ($id) {
            $stmt = $this->pdo->prepare("
                SELECT p.*, c.name as category_name, c.slug as category_slug,
                       b.name as brand_name, b.slug as brand_slug
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE p.id = ? AND (p.status = 'active' OR p.is_active = 1)
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $img = (string)($product['thumbnail'] ?? '');
                $product['image_url'] = MediaService::getUrl($img, [], 'products');
                $product['responsive_images'] = MediaService::getResponsiveUrls($img, [300, 600, 900], 'products');
            }

            return $product ?: null;
        }, ['products']);
    }

    /**
     * Retrieve single product by Slug
     */
    public function getBySlug(string $slug): ?array
    {
        $cleanSlug = trim($slug);
        if ($cleanSlug === '') {
            return null;
        }

        return CacheService::remember("product:slug:" . md5($cleanSlug), CacheService::TTL_MEDIUM, function() use ($cleanSlug) {
            $stmt = $this->pdo->prepare("
                SELECT p.*, c.name as category_name, c.slug as category_slug,
                       b.name as brand_name, b.slug as brand_slug
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE p.slug = ? AND (p.status = 'active' OR p.is_active = 1)
                LIMIT 1
            ");
            $stmt->execute([$cleanSlug]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $img = (string)($product['thumbnail'] ?? '');
                $product['image_url'] = MediaService::getUrl($img, [], 'products');
                $product['responsive_images'] = MediaService::getResponsiveUrls($img, [300, 600, 900], 'products');
            }

            return $product ?: null;
        }, ['products']);
    }

    /**
     * Faceted search and paginated listing
     */
    public function getActive(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["(p.status = 'active' OR p.is_active = 1)"];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[] = "p.category_id = ?";
            $params[] = (int)$filters['category_id'];
        }

        if (!empty($filters['brand_id'])) {
            $where[] = "p.brand_id = ?";
            $params[] = (int)$filters['brand_id'];
        }

        if (!empty($filters['min_price'])) {
            $where[] = "p.price >= ?";
            $params[] = (float)$filters['min_price'];
        }

        if (!empty($filters['max_price'])) {
            $where[] = "p.price <= ?";
            $params[] = (float)$filters['max_price'];
        }

        if (!empty($filters['in_stock'])) {
            $where[] = "p.stock > 0";
        }

        if (!empty($filters['search'])) {
            $where[] = "(p.name LIKE ? OR p.sku LIKE ?)";
            $term = '%' . trim((string)$filters['search']) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        $whereSql = implode(' AND ', $where);

        // Count total matching
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Sorting
        $allowedSorts = [
            'price_asc'  => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'rating'     => 'p.avg_rating DESC',
            'newest'     => 'p.id DESC',
            'name_asc'   => 'p.name ASC'
        ];
        $sortOrder = $allowedSorts[$filters['sort'] ?? 'newest'] ?? 'p.id DESC';

        $limitInt = (int)$perPage;
        $offsetInt = (int)$offset;

        $sql = "
            SELECT p.id, p.name, p.slug, p.sku, p.price, p.discount_price, p.stock, 
                   p.thumbnail, p.avg_rating, p.review_count,
                   c.name as category_name, b.name as brand_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE {$whereSql}
            ORDER BY {$sortOrder}
            LIMIT {$limitInt} OFFSET {$offsetInt}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $img = (string)($item['thumbnail'] ?? '');
            $item['image_url'] = MediaService::getUrl($img, [], 'products');
        }

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / max(1, $perPage))
        ];
    }
}
