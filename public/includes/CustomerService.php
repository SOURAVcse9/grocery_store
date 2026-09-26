<?php
/**
 * ==============================================================================
 * GroCo Customer Service Layer
 * ==============================================================================
 * Centralized business logic for customer profile management, address books,
 * multi-device session tracking, and customer IDOR protection.
 * ==============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/CacheService.php';

class CustomerService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Retrieve customer profile by ID (excluding password hashes)
     */
    public function getById(int $userId): ?array
    {
        if ($userId <= 0) return null;

        $stmt = $this->pdo->prepare("
            SELECT id, full_name as name, full_name, email, phone, avatar, google_id, email_verified, is_active, session_version, created_at
            FROM users 
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    /**
     * Retrieve customer profile by Email
     */
    public function getByEmail(string $email): ?array
    {
        $cleanEmail = strtolower(trim($email));
        if ($cleanEmail === '') return null;

        $stmt = $this->pdo->prepare("
            SELECT id, full_name as name, full_name, email, phone, avatar, google_id, email_verified, is_active, created_at
            FROM users 
            WHERE LOWER(email) = ?
            LIMIT 1
        ");
        $stmt->execute([$cleanEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    /**
     * Update customer profile info
     */
    public function updateProfile(int $userId, array $data): bool
    {
        if ($userId <= 0) return false;

        $fields = [];
        $params = [];

        if (isset($data['name']) || isset($data['full_name'])) {
            $fields[] = "full_name = ?";
            $params[] = trim((string)($data['full_name'] ?? $data['name']));
        }
        if (isset($data['phone'])) {
            $fields[] = "phone = ?";
            $params[] = trim((string)$data['phone']);
        }
        if (isset($data['avatar'])) {
            $fields[] = "avatar = ?";
            $params[] = trim((string)$data['avatar']);
        }

        if (empty($fields)) return false;

        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Retrieve customer addresses with strict user isolation
     */
    public function getAddresses(int $userId): array
    {
        if ($userId <= 0) return [];

        $stmt = $this->pdo->prepare("
            SELECT id, user_id, label, recipient_name, phone, address_line1, address_line2, city, state, postal_code, country, is_default
            FROM addresses
            WHERE user_id = ?
            ORDER BY is_default DESC, id DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add address for customer
     */
    public function addAddress(int $userId, array $addr): int
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Valid user ID required');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO addresses (user_id, label, recipient_name, phone, address_line1, address_line2, city, state, postal_code, country, is_default, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $userId,
            $addr['label'] ?? ($addr['address_type'] ?? 'Home'),
            $addr['recipient_name'] ?? '',
            $addr['phone'] ?? '',
            $addr['address_line1'] ?? ($addr['address_line'] ?? ''),
            $addr['address_line2'] ?? '',
            $addr['city'] ?? '',
            $addr['state'] ?? '',
            $addr['postal_code'] ?? '',
            $addr['country'] ?? 'Bangladesh',
            !empty($addr['is_default']) ? 1 : 0
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}
