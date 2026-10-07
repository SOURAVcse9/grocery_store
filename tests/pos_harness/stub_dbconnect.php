<?php
// TEST-ONLY STUB of the main project's public/dbconnect.php (NOT shipped in the zip).
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!defined('BASE_URL')) define('BASE_URL', '/public');
function db(): PDO { static $p=null; if(!$p){ $p=new PDO('mysql:host=127.0.0.1;dbname='.(getenv('TEST_DB_NAME') ?: 'groco_test').';charset=utf8mb4',getenv('TEST_DB_USER') ?: 'CHANGE_ME',getenv('TEST_DB_PASS') ?: 'CHANGE_ME',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); } return $p; }
function method_is(string $m): bool { return strtolower($_SERVER['REQUEST_METHOD'] ?? 'get') === strtolower($m); }
function input(string $k, $d = '', string $src = 'post') { $a = strtolower($src)==='get' ? $_GET : $_POST; if(!isset($a[$k]) && $src==='post' && isset($_GET[$k])) {$a=$_GET;} $v=$a[$k]??$d; return is_string($v)?$v:$d; }
function csrf_token(): string { if(empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(16)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="'.csrf_token().'">'; }
function verify_csrf(): bool { $t=$_POST['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??''); return $t!=='' && hash_equals(csrf_token(),$t); }
function verify_csrf_or_fail(): void { if(!verify_csrf()){ http_response_code(419); exit('CSRF'); } }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function flash(string $k,?string $m=null,string $t='info') { if($m===null){ $v=$_SESSION['_flash'][$k][0]??''; unset($_SESSION['_flash'][$k]); return $v; } $_SESSION['_flash'][$k]=[$m,$t]; }
function has_flash(?string $k=null): bool { return $k===null ? !empty($_SESSION['_flash']) : isset($_SESSION['_flash'][$k]); }
function display_flash_alerts(): void { foreach(($_SESSION['_flash']??[]) as $f){ echo '<div class="flash">'.e($f[0]).'</div>'; } unset($_SESSION['_flash']); }
function image_url($f,$d=''): string { return $f ? "/uploads/$d/$f" : '/assets/img/placeholder.png'; }
function site_name(): string { return 'GroCo'; }
function asset(string $p): string { return '/'.$p; }
function url_for(string $p=''): string { return '/'.$p; }
function current_url(): string { return $_SERVER['REQUEST_URI'] ?? ''; }
function old(string $k,$d=''){ return $_POST[$k]??$d; }
function redirect(string $u): void { header('Location: '.$u); exit; }
function redirect_admin(string $p): void { header('Location: /admin/'.$p); exit; }
function current_admin_role_id(): ?int { return $_SESSION['admin_role_id'] ?? null; }
function time_ago($d): string { $s=time()-strtotime((string)$d); return $s<60?'just now':($s<3600?floor($s/60).'m ago':($s<86400?floor($s/3600).'h ago':floor($s/86400).'d ago')); }
