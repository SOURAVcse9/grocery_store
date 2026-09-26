<?php
/**
 * ==============================================================================
 * GroCo Review Service Layer
 * ==============================================================================
 * Business logic for customer reviews, rating aggregations, and moderation workflows.
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/CacheService.php';

class ReviewService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Retrieve approved reviews for a product
     */
    public function getProductReviews(int $productId, int $limit = 20): array
    {
        if ($productId <= 0) return [];

        $stmt = $this->pdo->prepare("
            SELECT r.*, u.name as customer_name, u.avatar as customer_avatar
            FROM product_reviews r
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.product_id = ? AND (r.status = 'approved' OR r.is_approved = 1)
            ORDER BY r.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $productId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a review with rating updates
     */
    public function addReview(int $productId, int $userId, int $rating, string $comment, string $title = ''): int
    {
        if ($productId <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('Invalid product or user ID');
        }

        $rating = min(5, max(1, $rating));
        $comment = trim($comment);

        $stmt = $this->pdo->prepare("
            INSERT INTO product_reviews (product_id, user_id, rating, review_title, review_comment, status, is_approved, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'approved', 1, NOW(), NOW())
        ");
        $stmt->execute([$productId, $userId, $rating, $title ?: 'Product Review', $comment]);
        $reviewId = (int)$this->pdo->lastInsertId();

        // Update product average rating and review count
        $this->recalculateRating($productId);

        return $reviewId;
    }

    /**
     * Recalculate average rating for a product
     */
    public function recalculateRating(int $productId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT AVG(rating) as avg_r, COUNT(*) as cnt
            FROM product_reviews
            WHERE product_id = ? AND (status = 'approved' OR is_approved = 1)
        ");
        $stmt->execute([$productId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        $avgRating = round((float)($res['avg_r'] ?? 0), 1);
        $reviewCount = (int)($res['cnt'] ?? 0);

        $updateStmt = $this->pdo->prepare("
            UPDATE products 
            SET avg_rating = ?, review_count = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$avgRating, $reviewCount, $productId]);

        CacheService::invalidateTag('products');
    }
}
