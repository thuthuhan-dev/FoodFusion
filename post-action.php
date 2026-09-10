<?php
/**
 * Small JSON endpoint for Community Cookbook actions.
 *
 * The like and repost buttons post here in the background (fetch), so the
 * feed updates in place instead of reloading and jumping back to the top.
 * The plain <form> in community.php still works if JavaScript is off —
 * this endpoint is an enhancement, not a replacement.
 */

require_once 'includes/db.php';

header('Content-Type: application/json; charset=utf-8');

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$action = $_POST['action'] ?? '';
$postId = (int) ($_POST['post_id'] ?? 0);
$token  = $_POST['csrf_token'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    respond(['ok' => false, 'error' => 'Your session expired. Please refresh the page.'], 403);
}

if (empty($_SESSION['user_id'])) {
    respond(['ok' => false, 'error' => 'Log in to do that.', 'needsLogin' => true], 401);
}

if ($postId <= 0 || !$pdo) {
    respond(['ok' => false, 'error' => 'That post could not be found.'], 400);
}

$userId = (int) $_SESSION['user_id'];

try {
    if ($action === 'toggle_like') {
        $check = $pdo->prepare('SELECT like_id FROM POST_LIKE WHERE post_id = ? AND user_id = ?');
        $check->execute([$postId, $userId]);
        $existing = $check->fetch();

        if ($existing) {
            $pdo->prepare('DELETE FROM POST_LIKE WHERE like_id = ?')->execute([$existing['like_id']]);
            $active = false;
        } else {
            $pdo->prepare('INSERT INTO POST_LIKE (post_id, user_id) VALUES (?, ?)')->execute([$postId, $userId]);
            $active = true;
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM POST_LIKE WHERE post_id = ?');
        $countStmt->execute([$postId]);

        respond([
            'ok'     => true,
            'active' => $active,
            'count'  => (int) $countStmt->fetchColumn(),
        ]);
    }

    if ($action === 'toggle_repost') {
        // A member cannot repost their own post.
        $ownerStmt = $pdo->prepare('SELECT user_id FROM COMMUNITY_POST WHERE post_id = ?');
        $ownerStmt->execute([$postId]);
        $owner = $ownerStmt->fetchColumn();

        if ($owner === false) {
            respond(['ok' => false, 'error' => 'That post no longer exists.'], 404);
        }
        if ((int) $owner === $userId) {
            respond(['ok' => false, 'error' => 'You cannot repost your own post.'], 400);
        }

        $check = $pdo->prepare('SELECT share_id FROM POST_SHARE WHERE post_id = ? AND shared_by_user_id = ?');
        $check->execute([$postId, $userId]);
        $existing = $check->fetch();

        if ($existing) {
            $pdo->prepare('DELETE FROM POST_SHARE WHERE share_id = ?')->execute([$existing['share_id']]);
            $active = false;
        } else {
            $pdo->prepare('INSERT INTO POST_SHARE (post_id, shared_by_user_id) VALUES (?, ?)')->execute([$postId, $userId]);
            $active = true;
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM POST_SHARE WHERE post_id = ?');
        $countStmt->execute([$postId]);

        respond([
            'ok'     => true,
            'active' => $active,
            'count'  => (int) $countStmt->fetchColumn(),
        ]);
    }

    respond(['ok' => false, 'error' => 'Unknown action.'], 400);

} catch (PDOException $e) {
    respond(['ok' => false, 'error' => 'Something went wrong. Please try again.'], 500);
}