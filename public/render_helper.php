<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $argv[1] ?? '/grocery-store/public/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = $argv[1] ?? '/grocery-store/public/index.php';

require $argv[2];
