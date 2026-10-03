<?php
/**
 * ==============================================================================
 * GroCo Enterprise POS — Customer & Loyalty Service
 * ==============================================================================
 * Customer lookup, fast autocomplete, walk-in resolution, inline registration,
 * wallet deduction, and reward points computation.
 * ==============================================================================
 */

declare(strict_types=1);

namespace Groco\Pos;

use PDO;
use Exception;
use InvalidArgumentException;

class PosCustomerService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Ensure and return the default Walk-in Customer ID.
     */
    public function getWalkinCustomerId(): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE phone = '00000000000' LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            return (int)$user['id'];
        }

        $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $stmtIns = $this->pdo->prepare("
            INSERT INTO users (
                role_id, full_name, email, phone, password, 
                is_verified, is_active, created_at, updated_at
            ) VALUES (
                2, 'Walk-in Customer', 'walkin@grocery.store', '00000000000', ?, 
                1, 1, NOW(), NOW()
            )
        ");
        $stmtIns->execute([$password]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Resolve customer ID safely (fallback to Walk-in if missing or invalid).
     */
    public function resolveCustomerId(?int $customerId): int
    {
        $walkinId = $this->getWalkinCustomerId();
        if (!$customerId || $customerId <= 0) {
            return $walkinId;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$customerId]);
        if ($stmt->fetch()) {
            return $customerId;
        }

        return $walkinId;
    }

    /**
     * Fast customer search by phone, name, or ID.
     */
    public function search(string $query, int $limit = 15): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $searchTerm = '%' . $query . '%';
        $userId = ctype_digit($query) ? (int)$query : 0;

        $stmt = $this->pdo->prepare("
            SELECT id, full_name, phone, email, wallet_balance, reward_points,
                   (SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) AS total_orders
            FROM users 
            WHERE (phone LIKE ? OR full_name LIKE ? OR id = ?)
              AND deleted_at IS NULL 
              AND is_active = 1
            LIMIT ?
        ");

        $stmt->bindValue(1, $searchTerm);
        $stmt->bindValue(2, $searchTerm);
        $stmt->bindValue(3, $userId, PDO::PARAM_INT);
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new customer profile directly from the POS terminal.
     */
    public function createCustomer(
        string $fullName,
        string $phone,
        string $email = '',
        string $address = '',
        string $gender = '',
        string $dob = '',
        bool $enrollLoyalty = true
    ): array {
        $fullName = trim($fullName);
        $phone = trim($phone);
        $email = trim($email);

        if ($fullName === '' || $phone === '') {
            throw new InvalidArgumentException("Customer full name and mobile phone are required.");
        }

        // Validate uniqueness of phone
        $chk = $this->pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        $chk->execute([$phone]);
        if ($chk->fetch()) {
            throw new Exception("A customer with mobile number '{$phone}' already exists.");
        }

        if ($email === '') {
            $email = 'pos_' . preg_replace('/[^0-9]/', '', $phone) . '@grocery.store';
        } else {
            $chkEmail = $this->pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $chkEmail->execute([$email]);
            if ($chkEmail->fetch()) {
                throw new Exception("A customer with email '{$email}' already exists.");
            }
        }

        $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $initialPoints = $enrollLoyalty ? 0 : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO users (
                role_id, full_name, email, phone, password, 
                gender, dob, reward_points, wallet_balance, is_verified, is_active, created_at, updated_at
            ) VALUES (
                2, ?, ?, ?, ?, 
                ?, ?, ?, 0.00, 1, 1, NOW(), NOW()
            )
        ");

        $stmt->execute([
            $fullName,
            $email,
            $phone,
            $password,
            $gender ?: null,
            $dob ?: null,
            $initialPoints
        ]);

        $newId = (int)$this->pdo->lastInsertId();

        if ($address !== '') {
            $stmtAddr = $this->pdo->prepare("
                INSERT INTO addresses (user_id, label, recipient_name, phone, address_line1, city, country, is_default)
                VALUES (?, 'POS Address', ?, ?, ?, 'Dhaka', 'Bangladesh', 1)
            ");
            $stmtAddr->execute([$newId, $fullName, $phone, $address]);
        }

        return [
            'id'             => $newId,
            'full_name'      => $fullName,
            'phone'          => $phone,
            'email'          => $email,
            'wallet_balance' => 0.00,
            'reward_points'  => $initialPoints ?? 0
        ];
    }

    /**
     * Process loyalty points accrual and wallet deduction upon checkout.
     */
    public function processPostSaleLoyalty(int $customerId, float $paidViaWallet, float $totalSpent): void
    {
        $walkinId = $this->getWalkinCustomerId();
        if ($customerId <= 0 || $customerId === $walkinId) {
            return;
        }

        if ($paidViaWallet > 0.00) {
            $stmtWallet = $this->pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
            $stmtWallet->execute([$paidViaWallet, $customerId]);
        }

        // Accrue 1 reward point per ৳100 spent
        $earnedPoints = (int)floor($totalSpent / 100);
        if ($earnedPoints > 0) {
            $stmtPoints = $this->pdo->prepare("UPDATE users SET reward_points = COALESCE(reward_points, 0) + ? WHERE id = ?");
            $stmtPoints->execute([$earnedPoints, $customerId]);
        }
    }
}
