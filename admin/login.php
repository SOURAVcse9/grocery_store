<?php
/**
 * ==========================================================================
 * admin/login.php — Administrative Login Page
 * ==========================================================================
 */

declare(strict_types=1);

define('BYPASS_AUTH', true);

require_once __DIR__ . '/../public/dbconnect.php';
require_once __DIR__ . '/middleware/auth_middleware.php';

// Redirect if already logged in
require_admin_guest();

$pdo = db();
$error = null;
$success = null;

// Brute-Force Rate Limiting (Max 5 failures per IP within 15 minutes)
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
try {
    $rateStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM admin_login_logs 
        WHERE ip_address = :ip 
          AND success = 0 
          AND login_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $rateStmt->execute(['ip' => $ipAddress]);
    $failedAttempts = (int) $rateStmt->fetchColumn();
} catch (PDOException $e) {
    error_log('[admin/login] Rate check fail: ' . $e->getMessage());
    $failedAttempts = 0;
}
// } catch (PDOException $e) {
//     // আগের কোডটি মুছে দিন এবং এটি দিন
//     die("Data Query Error: " . $e->getMessage() . "<br>File: " . $e->getFile() . " Line: " . $e->getLine());
// }

if ($failedAttempts >= 5) {
    $error = 'Too many login failures detected from your IP. Access locked for 15 minutes.';
}

// Process POST submission
if (method_is('post') && $failedAttempts < 5) {
    // CSRF verification
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $identity = trim(input('identity', ''));
        $password = input('password', '');
        $remember = isset($_POST['remember']);

        if (empty($identity) || empty($password)) {
            $error = 'Please enter both your login ID and password.';
        } else {
            try {
                // Fetch admin matching username or email
                $stmt = $pdo->prepare("
                    SELECT a.*, r.name AS role_name 
                    FROM admins a
                    JOIN admin_roles r ON r.id = a.role_id
                    WHERE (a.username = :identity1 OR a.email = :identity2)
                    LIMIT 1
                ");
                $stmt->execute(['identity1' => $identity, 'identity2' => $identity]);
                $admin = $stmt->fetch();

                if ($admin && (int) $admin['is_active'] === 0) {
                    log_admin_login(null, $identity, false, 'Account deactivated by Super Admin');
                    $error = 'Your administrative account has been suspended.';
                } elseif ($admin && password_verify($password, $admin['password'])) {
                    // Password is correct! Let's login
                    $adminId = (int) $admin['id'];
                    
                    // Session regeneration
                    session_regenerate_id(true);
                    
                    $_SESSION['admin_id'] = $adminId;
                    $_SESSION['admin_last_activity'] = time();
                    $_SESSION['admin_fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] ?? '');

                    // Remember Me Cookie Logic
                    if ($remember) {
                        $rawToken = bin2hex(random_bytes(32));
                        $hashedToken = hash('sha256', $rawToken);
                        
                        // Save in database
                        $cookieStmt = $pdo->prepare("UPDATE admins SET remember_token = :token WHERE id = :id");
                        $cookieStmt->execute(['token' => $hashedToken, 'id' => $adminId]);

                        // Send Cookie (Expires in 30 days)
                        setcookie('admin_remember', $rawToken, [
                            'expires' => time() + (30 * 86400),
                            'path' => '/admin',
                            'secure' => true,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    }

                    // Update last login timestamp
                    $pdo->prepare("UPDATE admins SET last_login_at = NOW() WHERE id = :id")->execute(['id' => $adminId]);

                    // Write logs
                    log_admin_login($adminId, $identity, true);
                    log_admin_activity('login', 'Administrator logged in successfully via Form');

                    // Redirect to dashboard
                    header('Location: ' . BASE_URL . '/../admin/index.php');
                    exit;
                } else {
                    // Log failure details
                    $adminId = $admin ? (int) $admin['id'] : null;
                    log_admin_login($adminId, $identity, false, 'Invalid credentials');
                    
                    // Increment failedAttempts dynamically for the immediate page display
                    $failedAttempts++;
                    if ($failedAttempts >= 5) {
                        $error = 'Too many login failures detected from your IP. Access locked for 15 minutes.';
                    } else {
                        $error = 'Invalid username/email or password combination.';
                    }
                }
            } catch (PDOException $e) {
                error_log('[admin/login] DB transaction failed: ' . $e->getMessage());
                $error = 'An internal system error occurred. Please try again later.';
            }
        }
    }
}

// SEO Details
$pageTitle = 'Admin Portal Login — ' . site_name();
require_once __DIR__ . '/layouts/header.php';
?>

<div class="container" style="margin-top: 80px; margin-bottom: 80px; display: flex; justify-content: center;">
    <div class="dashboard-card" style="width: 100%; max-width: 420px; padding: var(--space-6); box-shadow: var(--shadow-md);">
        
        <!-- Brand Header -->
        <div style="text-align: center; margin-bottom: var(--space-5);">
            <div style="font-size: 36px; color: var(--color-primary); margin-bottom: var(--space-2);"><i class="fas fa-shield-halved"></i></div>
            <h2 style="font-size: var(--fs-lg); font-weight: 800; color: var(--color-text); margin:0;">Admin Portal</h2>
            <p style="font-size: var(--fs-xs); color: var(--color-text-muted); margin: 4px 0 0 0;">Enter administrative credentials to sign in.</p>
        </div>

        <!-- Feedback Alert banners -->
        <?php if ($error !== null): ?>
            <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-xs); font-weight:600; line-height:1.4; margin-bottom:var(--space-4);">
                <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Form fields wrapper -->
        <?php if ($failedAttempts < 5): ?>
            <form method="post" id="adminLoginForm" onsubmit="showAdminLoader();">
                <?= csrf_field() ?>
                
                <!-- Email or Username Identity input -->
                <div class="form-field-group">
                    <label for="loginIdentity" style="font-weight:700;">Username or Email *</label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <i class="fas fa-user" style="position:absolute; left:12px; color:var(--color-text-faint); font-size:13px;"></i>
                        <input type="text" id="loginIdentity" name="identity" placeholder="Enter username or email address" required style="width:100%; padding:10px 12px 10px 36px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                    </div>
                </div>

                <!-- Password input with Show toggle -->
                <div class="form-field-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <label for="loginPassword" style="font-weight:700; margin:0;">Password *</label>
                        <a href="forgot-password.php" style="font-size:11px; color:var(--color-primary); font-weight:700; text-decoration:none;">Forgot Password?</a>
                    </div>
                    <div style="position:relative; display:flex; align-items:center;">
                        <i class="fas fa-lock" style="position:absolute; left:12px; color:var(--color-text-faint); font-size:13px;"></i>
                        <input type="password" id="loginPassword" name="password" placeholder="Enter password" required style="width:100%; padding:10px 36px 10px 36px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                        <button type="button" onclick="toggleAdminPasswordVis()" style="position:absolute; right:12px; border:none; background:none; color:var(--color-text-faint); font-size:13px; cursor:pointer;" aria-label="Toggle Password Visibility">
                            <i class="fas fa-eye" id="passwordEyeIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me checkbox -->
                <div class="form-field-group" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" id="rememberMeInput" name="remember" style="accent-color:var(--color-primary); width:14px; height:14px;">
                    <label for="rememberMeInput" style="font-size:12px; color:var(--color-text-muted); cursor:pointer; font-weight:600; margin:0;">Keep me signed in for 30 days</label>
                </div>

                <!-- Submit trigger -->
                <button type="submit" id="btnAdminLogin" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <span id="loginBtnText">Sign In</span>
                    <i class="fas fa-spinner fa-spin" id="loginBtnSpinner" style="display:none;"></i>
                </button>
            </form>
        <?php else: ?>
            <div style="text-align:center; padding:16px 0;">
                <p style="font-size:var(--fs-sm); color:var(--color-text-muted); line-height:1.6; margin-bottom:var(--space-4);">For security reasons, login is locked temporarily. Please check back in a few minutes.</p>
                <a href="<?= url_for('index.php') ?>" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:8px 18px; text-decoration:none;">Go back to Home</a>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
function toggleAdminPasswordVis() {
    const passwordInput = document.getElementById('loginPassword');
    const eyeIcon = document.getElementById('passwordEyeIcon');
    if (passwordInput && eyeIcon) {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.className = 'fas fa-eye-slash';
        } else {
            passwordInput.type = 'password';
            eyeIcon.className = 'fas fa-eye';
        }
    }
}

function showAdminLoader() {
    const btnText = document.getElementById('loginBtnText');
    const btnSpinner = document.getElementById('loginBtnSpinner');
    const btn = document.getElementById('btnAdminLogin');
    if (btnText && btnSpinner && btn) {
        btn.disabled = true;
        btnText.textContent = 'Verifying...';
        btnSpinner.style.display = 'inline-block';
    }
}
</script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
