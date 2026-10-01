<?php
require_once 'includes/db.php';
// There is only one join FORM in the whole site — the Join Us modal in
// includes/header.php. This file just processes the submission. See
// includes/header.php and includes/footer.php for the modal reopen logic.
function fail_join($message, $old) {
    $_SESSION['auth_error'] = $message;
    $_SESSION['auth_old'] = $old;
    header('Location: index.php?auth=join');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?auth=join');
    exit;
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$password  = $_POST['password'] ?? '';
$old = ['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email];

$submittedToken = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
    fail_join('Your session expired before this form was submitted. Please try again.', $old);
}

if ($firstName === '' || mb_strlen($firstName) > 50) {
    fail_join('First name is required and must be under 50 characters.', $old);
}
if (!preg_match("/^[\p{L}\s'\-]+$/u", $firstName)) {
    fail_join('First name may only contain letters, spaces, and hyphens.', $old);
}

if ($lastName === '' || mb_strlen($lastName) > 50) {
    fail_join('Last name is required and must be under 50 characters.', $old);
}
if (!preg_match("/^[\p{L}\s'\-]+$/u", $lastName)) {
    fail_join('Last name may only contain letters, spaces, and hyphens.', $old);
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
    fail_join('Please enter a valid email address.', $old);
}

if (mb_strlen($password) < 8) {
    fail_join('Password must be at least 8 characters long.', $old);
}
if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    fail_join('Password must include at least one letter and one number.', $old);
}

if (!$pdo) {
    fail_join('The database is not connected right now, so accounts cannot be created. Check includes/db.php.', $old);
}

try {
    $check = $pdo->prepare('SELECT user_id FROM USERS WHERE email = ?');
    $check->execute([$email]);
    if ($check->fetch()) {
        fail_join('An account with that email already exists. Try logging in instead.', $old);
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    $insert = $pdo->prepare(
        'INSERT INTO USERS (first_name, last_name, email, password_hash, role, status)
        VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([$firstName, $lastName, $email, $passwordHash, 'Member', 'Active']);
    $newUserId = (int) $pdo->lastInsertId();

    // Log the new account straight in — no reason to make them retype
    // credentials they just entered thirty seconds ago.
    $logStmt = $pdo->prepare(
        'INSERT INTO SESSION_LOG (user_id, login_time, ip_address, device_info) VALUES (?, NOW(), ?, ?)'
    );
    $logStmt->execute([
        $newUserId,
        $_SERVER['REMOTE_ADDR'] ?? '',
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    session_regenerate_id(true);
    $_SESSION['user_id']        = $newUserId;
    $_SESSION['first_name']     = $firstName;
    $_SESSION['role']           = 'Member';
    $_SESSION['session_log_id'] = $pdo->lastInsertId();
    unset($_SESSION['csrf_token']);

    header('Location: index.php');
    exit;

} catch (PDOException $e) {
    error_log('FoodFusion register: ' . $e->getMessage());
    fail_join('Something went wrong creating your account. Please try again.', $old);
}