<?php
require_once __DIR__ . '/../public/dbconnect.php';
$stmt = db()->query('SELECT r.name AS role_name, p.permission_key FROM admin_roles r JOIN admin_role_permissions rp ON rp.role_id = r.id JOIN admin_permissions p ON p.id = rp.permission_id ORDER BY r.name, p.permission_key');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Role: " . $row['role_name'] . " -> Permission: " . $row['permission_key'] . "\n";
}
