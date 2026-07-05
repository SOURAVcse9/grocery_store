<?php
// এটি আপনার প্রজেক্টের রুট ডিরেক্টরিতে রাখুন
require_once 'public/dbconnect.php';
$pdo = db();
$newPass = password_hash("admin123", PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE username = 'superadmin'");
$stmt->execute([$newPass]);
echo "Password reset to: admin123";
?>