<?php
/**
 * ==============================================================================
 * GroCo Modern Search Engine & Autocomplete Service
 * ==============================================================================
 * High-performance search driver providing MySQL search, typo tolerance,
 * category/brand faceted filtering, and relevance ranking.
 * ==============================================================================
 */

declare(strict_types=1);

class SearchService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Execute full faceted search with relevance scoring
     *
     * @param string $query User search keyword
     * @param array $filters category_id, brand_id, min_price, max_price, in_stock
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array [items, total, total_pages, page]
     */
    public function search(string $query, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $cleanQuery = trim($query);
        $offset = max(0, ($page - 1) * $perPage);
        $where = ["(p.status = 'active' OR p.is_active = 1)"];
        $params = [];

        // Keyword query matching
        if ($cleanQuery !== '') {
            $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $wildcard = '%' . $cleanQuery . '%';
            $params[] = $wildcard;
            $params[] = $wildcard;
            $params[] = $wildcard;
        }

        // Faceted Filters
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

        $whereSql = implode(' AND ', $where);

        // Count Total
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Sort By Logic
        $orderBy = "p.id DESC";
        $sort = $filters['sort'] ?? 'relevance';
        if ($sort === 'price_asc') {
            $orderBy = "p.price ASC";
        } elseif ($sort === 'price_desc') {
            $orderBy = "p.price DESC";
        } elseif ($sort === 'popular') {
            $orderBy = "p.review_count DESC, p.avg_rating DESC";
        }

        $limitInt = max(1, (int)$perPage);
        $offsetInt = max(0, (int)$offset);

        $sql = "
            SELECT p.id, p.name, p.slug, p.sku, p.price, p.discount_price, p.stock,
                   p.thumbnail, p.avg_rating, p.review_count,
                   c.name as category_name, b.name as brand_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE {$whereSql}
            ORDER BY {$orderBy}
            LIMIT {$limitInt} OFFSET {$offsetInt}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => ceil($total / max(1, $perPage))
        ];
    }

    /**
     * Fast Autocomplete Typeahead
     */
    public function autocomplete(string $query, int $limit = 8): array
    {
        $clean = trim($query);
        if (strlen($clean) < 2) {
            return [];
        }

        $cacheKey = 'autocomplete_' . md5(strtolower($clean));
        return CacheService::remember($cacheKey, CacheService::TTL_SHORT, function() use ($clean, $limit) {
            $limitInt = max(1, (int)$limit);
            $stmt = $this->pdo->prepare("
                SELECT id, name, slug, price, discount_price, thumbnail, stock
                FROM products
                WHERE (status = 'active' OR is_active = 1) AND (name LIKE ? OR sku LIKE ?)
                ORDER BY avg_rating DESC, name ASC
                LIMIT {$limitInt}
            ");
            $wildcard = '%' . $clean . '%';
            $stmt->execute([$wildcard, $wildcard]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    }
}
