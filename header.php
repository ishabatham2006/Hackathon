<?php require_once __DIR__ . '/../functions.php';
page_start($pageTitle ?? 'StockSense');
$__noNavPages = ['Login', 'Register', 'Forgot Password', 'Reset Password'];
if (!empty($_SESSION['user_id']) && !in_array($pageTitle ?? '', $__noNavPages, true)) nav();
echo '<main class="page-wrap">';
show_flash();
