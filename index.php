<?php require_once 'functions.php';
header('Location: ' . (empty($_SESSION['user_id']) ? 'login.php' : 'inventory_dashboard.php'));
exit;
