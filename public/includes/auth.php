<?php
/**
 * ==========================================================================
 * includes/auth.php
 * ==========================================================================
 * Session-based authentication against the `users` / `roles` tables.
 * Depends on: dbconnect.php (db(), session already started),
 *             includes/functions.php (redirect, flash),
 *             includes/helpers.php (get_or_create_guest_token).
 *
 * Design notes:
 *   - Only the user id + role id are trusted from the session; the full
 *     user row is fetched fresh (and cached per-request) via current_user()
 *     so that a ban/role-change takes effect on the very next request.
 *   - `carts`, `wishlists`, `compare_items` support anonymous (session_id)
 *     usage per the schema. On login we merge the guest's rows into the
 *     user's rows so nothing in the cart is lost.
 * ==========================================================================
 */

declare(strict_types=1);

const ROLE_ADMIN = 'admin';
const ROLE_CUSTOMER = 'customer';

/**
 * is_logged_in()
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * current_user()
 *
 * Fetches (and per-request caches) the logged-in user's row joined with
 * their role name. Returns null for guests or if the account no longer
 * exists / was deactivated.
 */
function current_user(): ?array
{
    static $cached = null;
    static $resolved = false;

    if ($resolved) {
        return $cached;
    }
    $resolved = true;

    $userId = current_user_id();
    if ($userId === null) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT u.id, u.role_id, u.full_name, u.email, u.phone, u.avatar,
                u.is_verified, u.is_active, r.role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] === 0) {
        // Account deleted/deactivated since the session was created.
        logout_user();
        return null;
    }

    $cached = $user;
    return $cached;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role_name'] === ROLE_ADMIN;
}

/**
 * require_login()
 *
 * Call at the top of any storefront page that requires a logged-in
 * customer (account.php, orders.php, checkout.php, etc.).
 */
function require_login(string $redirectTo = 'login.php'): void
{
    if (!is_logged_in()) {
        $_SESSION['intended_url'] = current_url();
        flash('auth', 'Please log in to continue.', 'info');
        redirect($redirectTo);
    }
}

/**
 * require_admin()
 *
 * Call at the top of every admin/*.php page.
 */
function require_admin(string $redirectTo = 'login.php'): void
{
    if (!is_logged_in() || !is_admin()) {
        redirect($redirectTo);
    }
}

/**
 * attempt_login()
 *
 * Verifies credentials against the users table. Returns the user row on
 * success, or false on failure (bad email, bad password, inactive account).
 * Does NOT start the session itself — call login_user() with the result.
 */
function attempt_login(string $email, string $password): array|false
{
    $stmt = db()->prepare(
        'SELECT id, role_id, full_name, email, password, is_active
         FROM users WHERE email = :email LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] === 0) {
        return false;
    }

    if (!password_verify($password, $user['password'])) {
        return false;
    }

    return $user;
}

/**
 * login_user()
 *
 * Establishes an authenticated session for the given user row, prevents
 * session fixation by regenerating the session id, merges any guest
 * cart/wishlist/compare data, and updates last_login_at.
 */
function login_user(array $user): void
{
    $guestToken = $_SESSION['guest_token'] ?? null;

    // Prevent session fixation.
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role_id'] = (int) $user['role_id'];

    regenerate_csrf_token();

    $stmt = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => (int) $user['id']]);

    if ($guestToken !== null) {
        merge_guest_data_into_user((int) $user['id'], $guestToken);
    }
}

/**
 * merge_guest_data_into_user()
 *
 * Moves a guest's cart, wishlist, and compare-list rows onto their new
 * account. Cart merging adds quantities together when the same product
 * exists in both; wishlist/compare simply skip duplicates (both tables
 * have UNIQUE(user_id, product_id) constraints).
 */
function merge_guest_data_into_user(int $userId, string $guestToken): void
{
    $pdo = db();

    try {
        $pdo->beginTransaction();

        // ---- Cart merge -------------------------------------------------
        $guestCartStmt = $pdo->prepare('SELECT id FROM carts WHERE session_id = :sid LIMIT 1');
        $guestCartStmt->execute(['sid' => $guestToken]);
        $guestCartId = $guestCartStmt->fetchColumn();

        if ($guestCartId !== false) {
            $userCartStmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :uid LIMIT 1');
            $userCartStmt->execute(['uid' => $userId]);
            $userCartId = $userCartStmt->fetchColumn();

            if ($userCartId === false) {
                // No existing cart — simply re-assign the guest cart to the user.
                $pdo->prepare('UPDATE carts SET user_id = :uid, session_id = NULL WHERE id = :cid')
                    ->execute(['uid' => $userId, 'cid' => $guestCartId]);
            } else {
                // Merge line items, summing quantities on conflict.
                $itemsStmt = $pdo->prepare('SELECT product_id, quantity, price FROM cart_items WHERE cart_id = :cid');
                $itemsStmt->execute(['cid' => $guestCartId]);

                $upsert = $pdo->prepare(
                    'INSERT INTO cart_items (cart_id, product_id, quantity, price)
                     VALUES (:cart_id, :product_id, :quantity, :price)
                     ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
                );

                foreach ($itemsStmt->fetchAll() as $item) {
                    $upsert->execute([
                        'cart_id'    => $userCartId,
                        'product_id' => $item['product_id'],
                        'quantity'   => $item['quantity'],
                        'price'      => $item['price'],
                    ]);
                }

                $pdo->prepare('DELETE FROM carts WHERE id = :cid')->execute(['cid' => $guestCartId]);
            }
        }

        // ---- Merge session guest wishlist into user DB wishlists table ----
        if (!empty($_SESSION['wishlist'])) {
            $wishlistInsert = $pdo->prepare('INSERT IGNORE INTO wishlists (user_id, product_id, created_at) VALUES (:uid, :pid, NOW())');
            foreach ($_SESSION['wishlist'] as $pid => $val) {
                $wishlistInsert->execute(['uid' => $userId, 'pid' => (int) $pid]);
            }
            unset($_SESSION['wishlist']);
        }

        // ---- Compare list merge -----------------------------------------
        $compareStmt = $pdo->prepare('SELECT product_id FROM compare_items WHERE session_id = :sid');
        $compareStmt->execute(['sid' => $guestToken]);
        $compareInsert = $pdo->prepare(
            'INSERT IGNORE INTO compare_items (user_id, product_id) VALUES (:uid, :pid)'
        );
        foreach ($compareStmt->fetchAll() as $row) {
            $compareInsert->execute(['uid' => $userId, 'pid' => $row['product_id']]);
        }
        $pdo->prepare('DELETE FROM compare_items WHERE session_id = :sid')->execute(['sid' => $guestToken]);

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[merge_guest_data_into_user] ' . $e->getMessage());
    }
}

/**
 * logout_user()
 *
 * Fully destroys the session (not just the user_id key) so no stale
 * flash/old-input/csrf data survives across accounts on a shared machine.
 */
function logout_user(): void
{
    clear_remember_me_cookie();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

/**
 * handle_remember_me_cookie()
 *
 * Generates a secure cookie and stores its SHA-256 hash in the database.
 */
function handle_remember_me_cookie(int $userId): void
{
    $rawToken = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $rawToken);

    // Save hashed token in DB
    $stmt = db()->prepare('UPDATE users SET remember_token = :token WHERE id = :id');
    $stmt->execute(['token' => $hashedToken, 'id' => $userId]);

    // Set secure cookie for 30 days
    $cookieValue = $userId . ':' . $rawToken;
    $expireTime = time() + (30 * 24 * 60 * 60); // 30 days
    
    setcookie(
        'remember_me',
        $cookieValue,
        [
            'expires' => $expireTime,
            'path' => '/',
            'domain' => '',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

/**
 * clear_remember_me_cookie()
 *
 * Clears the remember_me cookie and nulls it out in the user DB.
 */
function clear_remember_me_cookie(): void
{
    $userId = current_user_id();
    if ($userId !== null) {
        try {
            $stmt = db()->prepare('UPDATE users SET remember_token = NULL WHERE id = :id');
            $stmt->execute(['id' => $userId]);
        } catch (PDOException $e) {
            // Ignore DB errors on forced logout
        }
    }
    
    setcookie(
        'remember_me',
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

/**
 * check_remember_me_autologin()
 *
 * Scans cookies for active remember_me data, logs in on match, and rotates token.
 */
function check_remember_me_autologin(): void
{
    if (is_logged_in()) {
        return;
    }

    $cookie = $_COOKIE['remember_me'] ?? null;
    if ($cookie === null) {
        return;
    }

    $parts = explode(':', $cookie, 2);
    if (count($parts) !== 2) {
        setcookie('remember_me', '', time() - 3600, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
        return;
    }

    [$userId, $rawToken] = $parts;
    $userId = (int) $userId;

    if ($userId <= 0) {
        return;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user && $user['remember_token'] !== null) {
            $hashedToken = hash('sha256', $rawToken);
            if (hash_equals($user['remember_token'], $hashedToken)) {
                login_user($user);
                handle_remember_me_cookie($userId);
            } else {
                // Token mismatch/hijack attempt, wipe DB token and clear cookie
                $stmt = $pdo->prepare('UPDATE users SET remember_token = NULL WHERE id = :id');
                $stmt->execute(['id' => $userId]);
                setcookie('remember_me', '', time() - 3600, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
            }
        }
    } catch (PDOException $e) {
        error_log('[check_remember_me_autologin] ' . $e->getMessage());
    }
}

/**
 * current_cart_id()
 *
 * Returns the id of the current visitor's cart row (creating one if
 * needed) — logged-in users get a user_id-keyed cart, guests get a
 * session_id-keyed cart via get_or_create_guest_token().
 */
function current_cart_id(): int
{
    $pdo = db();
    $userId = current_user_id();

    if ($userId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $cartId = $stmt->fetchColumn();

        if ($cartId === false) {
            $insert = $pdo->prepare('INSERT INTO carts (user_id) VALUES (:uid)');
            $insert->execute(['uid' => $userId]);
            return (int) $pdo->lastInsertId();
        }

        return (int) $cartId;
    }

    $guestToken = get_or_create_guest_token();
    $stmt = $pdo->prepare('SELECT id FROM carts WHERE session_id = :sid LIMIT 1');
    $stmt->execute(['sid' => $guestToken]);
    $cartId = $stmt->fetchColumn();

    if ($cartId === false) {
        $insert = $pdo->prepare('INSERT INTO carts (session_id) VALUES (:sid)');
        $insert->execute(['sid' => $guestToken]);
        return (int) $pdo->lastInsertId();
    }

    return (int) $cartId;
}

/**
 * cart_item_count() / unread_notification_count()
 *
 * Small badge-count helpers used by header.php. Kept here (rather than
 * functions.php) since they're auth/identity-aware.
 */
function cart_item_count(): int
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(ci.quantity), 0) FROM cart_items ci WHERE ci.cart_id = :cart_id');
    $stmt->execute(['cart_id' => current_cart_id()]);
    return (int) $stmt->fetchColumn();
}

function wishlist_item_count(): int
{
    if (!is_logged_in()) {
        return empty($_SESSION['wishlist']) ? 0 : count($_SESSION['wishlist']);
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id = :uid');
    $stmt->execute(['uid' => current_user_id()]);
    return (int) $stmt->fetchColumn();
}

function unread_notification_count(): int
{
    if (!is_logged_in()) {
        return 0;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
    $stmt->execute(['uid' => current_user_id()]);
    return (int) $stmt->fetchColumn();
}

/**
 * create_notification()
 *
 * Appends a new notification row to the notifications log table.
 */
function create_notification(int $userId, string $title, string $message, string $type = 'general'): bool
{
    try {
        $stmt = db()->prepare('
            INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
            VALUES (:uid, :title, :msg, :type, 0, NOW())
        ');
        return $stmt->execute([
            'uid'   => $userId,
            'title' => $title,
            'msg'   => $message,
            'type'  => $type
        ]);
    } catch (PDOException $e) {
        error_log('[create_notification] Fail: ' . $e->getMessage());
        return false;
    }
}
