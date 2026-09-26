<?php
/**
 * ==============================================================================
 * GroCo Category Service Layer
 * ==============================================================================
 * Centralized business logic for hierarchical categories, navigation trees, and caching.
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/CacheService.php';
require_once __DIR__ . '/MediaService.php';

class CategoryService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Retrieve all active categories with product counts
     */
    public function getAll(bool $onlyActive = true): array
    {
        return CacheService::remember('categories:all:' . ($onlyActive ? 'active' : 'all'), CacheService::TTL_MEDIUM, function() use ($onlyActive) {
            $sql = "SELECT c.*, COUNT(p.id) as product_count 
                    FROM categories c 
                    LEFT JOIN products p ON c.id = p.category_id AND (p.status = 'active' OR p.is_active = 1)";
            if ($onlyActive) {
                $sql .= " WHERE c.is_active = 1";
            }
            $sql .= " GROUP BY c.id ORDER BY c.name ASC";

            $stmt = $this->pdo->query($sql);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($categories as &$cat) {
                $img = (string)($cat['image'] ?? '');
                $cat['image_url'] = MediaService::getUrl($img, [], 'categories');
            }

            return $categories;
        }, ['categories']);
    }

    /**
     * Build nested category tree for menu navigation
     */
    public function getTree(): array
    {
        return CacheService::remember('categories:tree:nested', CacheService::TTL_MEDIUM, function() {
            $categories = $this->getAll(true);
            $tree = [];
            $byParent = [];

            foreach ($categories as $cat) {
                $parentId = (int)($cat['parent_id'] ?? 0);
                $byParent[$parentId][] = $cat;
            }

            foreach ($byParent[0] ?? [] as $root) {
                $rootId = (int)$root['id'];
                $root['children'] = $byParent[$rootId] ?? [];
                $tree[] = $root;
            }

            return $tree;
        }, ['categories']);
    }

    /**
     * Get category by ID
     */
    public function getById(int $id): ?array
    {
        if ($id <= 0) return null;
        $categories = $this->getAll(false);
        foreach ($categories as $cat) {
            if ((int)$cat['id'] === $id) {
                return $cat;
            }
        }
        return null;
    }

    /**
     * Get category by Slug
     */
    public function getBySlug(string $slug): ?array
    {
        $cleanSlug = trim($slug);
        if ($cleanSlug === '') return null;
        $categories = $this->getAll(true);
        foreach ($categories as $cat) {
            if (($cat['slug'] ?? '') === $cleanSlug) {
                return $cat;
            }
        }
        return null;
    }
}
