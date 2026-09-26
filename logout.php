<?php require_once 'functions.php';
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
