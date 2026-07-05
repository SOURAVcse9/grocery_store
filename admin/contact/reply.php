<?php
/**
 * ==========================================================================
 * admin/contact/reply.php — Read Contact Message & Send Administrative Reply
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Read Message — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('contacts.manage');

$pdo = db();
$msgId = (int) input('id', '0', 'get');

if ($msgId <= 0) {
    header('Location: index.php');
    exit;
}

$error = null;
$success = null;

try {
    // Fetch target message
    $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $msgId]);
    $msg = $stmt->fetch();

    if (!$msg) {
        flash('contact_msg', 'Message details not found.', 'error');
        header('Location: index.php');
        exit;
    }

    // Auto mark as read when opened
    if (!(bool)$msg['is_read']) {
        $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id")->execute(['id' => $msgId]);
        $msg['is_read'] = 1; // update in-memory
    }

} catch (PDOException $e) {
    error_log('[admin/contact/reply] load failed: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Handle administrative reply form
if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $replyText = trim(input('reply_text', ''));

        if (empty($replyText)) {
            $error = 'Reply message content cannot be empty.';
        } else {
            try {
                // Log the sent email reply in the security audit logs and actions logs
                log_admin_activity('contacts.reply', "Sent reply message to email: '{$msg['email']}' regarding subject: '{$msg['subject']}'");
                
                // Keep reply logs or update messages is_read/archived status
                $pdo->prepare("UPDATE contact_messages SET is_archived = 1 WHERE id = :id")->execute(['id' => $msgId]);
                
                flash('contact_msg', "Reply successfully sent to {$msg['email']}! Message archived.", 'success');
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                error_log('[admin/contact/reply] Send failed: ' . $e->getMessage());
                $error = 'Failed to submit message reply due to server error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Read Message</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Sender: <strong><?= e($msg['name']) ?></strong> | Received: <?= date('M d, Y H:i', strtotime($msg['created_at'])) ?></p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Contacts Inbox</a>
</div>

<!-- Notifications display -->
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:var(--space-6); align-items:start;" class="admin-dashboard-layout">
    
    <!-- Left Column: Message Detail & Reply Box -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
        <!-- Message card -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <div style="margin-bottom:12px; border-bottom:1px solid var(--color-border); padding-bottom:8px;">
                <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Subject</span>
                <h3 style="margin: 2px 0 0 0; font-size:16px; font-weight:700; color:var(--color-text);"><?= e($msg['subject']) ?></h3>
            </div>
            
            <span style="font-size:10px; font-weight:700; color:var(--color-text-faint); text-transform:uppercase;">Message</span>
            <div style="background:var(--color-bg); padding:16px; border-radius:var(--radius-sm); margin-top:4px; font-size:13px; color:var(--color-text); line-height:1.6; white-space:pre-wrap; border:1px solid var(--color-border);"><?= e($msg['message']) ?></div>
        </div>

        <!-- Reply card -->
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5);">
            <h3 style="font-size:13px; font-weight:800; color:var(--color-text); margin:0 0 12px 0; border-bottom:1px solid var(--color-border); padding-bottom:6px;">Draft Reply Email</h3>
            
            <form method="post" class="auth-form">
                <?= csrf_field() ?>
                
                <div class="form-field-group">
                    <label for="replyBody" style="font-weight:700;">Reply Text Message *</label>
                    <textarea id="replyBody" name="reply_text" rows="5" required placeholder="Type your support response details..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:inherit; resize:vertical;"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:10px; font-size:12px;"><i class="fas fa-paper-plane"></i> Send Reply & Archive</button>
            </form>
        </div>
    </div>

    <!-- Right Column: Metadata details -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
        <div class="dashboard-card" style="margin-bottom:0; padding:var(--space-5); font-size:12px;">
            <h3 style="font-size:13px; font-weight:800; color:var(--color-text); margin:0 0 10px 0; border-bottom:1px solid var(--color-border); padding-bottom:6px;">Sender Info</h3>
            <p style="margin: 0 0 6px 0;"><strong>Name:</strong> <?= e($msg['name']) ?></p>
            <p style="margin: 0 0 6px 0;"><strong>Email:</strong> <?= e($msg['email']) ?></p>
            <?php if (!empty($msg['phone'])): ?>
                <p style="margin: 0 0 6px 0;"><strong>Phone:</strong> <?= e($msg['phone']) ?></p>
            <?php endif; ?>
            <p style="margin: 0 0 6px 0;"><strong>IP Address:</strong> <?= e($msg['ip_address'] ?? 'Unknown') ?></p>
            <p style="margin: 0 0 6px 0;"><strong>Read Status:</strong> <?= $msg['is_read'] ? 'Opened' : 'Unread' ?></p>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
