<?php
require_once 'includes/db.php';

if (!empty($_SESSION['session_log_id']) && $pdo) {
    try {
        $stmt = $pdo->prepare('UPDATE SESSION_LOG SET logout_time = NOW() WHERE session_id = ?');
        $stmt->execute([$_SESSION['session_log_id']]);
    } catch (PDOException $e) {
        // Nothing the user can do about this — log out client-side regardless.
    }
}

$_SESSION = [];
session_destroy();

header('Location: index.php');
exit;