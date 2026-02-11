<?php
require_once 'functions.php';
require_once '../config/dbconfig.php';

if (!isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit;
}

$user = getCurrentUser($pdo);
if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php?error=inactive");
    exit;
}
?>