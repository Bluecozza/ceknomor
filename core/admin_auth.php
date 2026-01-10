<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function admin_require_login()
{
	admin_auto_login();
    if (!admin_logged_in()) {
        http_response_code(401);
        exit('Unauthorized');
    }
}

function admin_login(int $id, string $username)
{
    $_SESSION['admin_id'] = $id;
    $_SESSION['admin_user'] = $username;
}

function admin_logout()
{
    if (!empty($_COOKIE['admin_remember'])) {
    $hash = hash('sha256', $_COOKIE['admin_remember']);
    $db->prepare("
        DELETE FROM admin_remember_tokens WHERE token_hash=?
    ")->execute([$hash]);

    setcookie('admin_remember','',time()-3600,'/');
}
session_destroy();

}

function admin_auto_login() {
    if (!empty($_SESSION['admin_id'])) return;

    if (empty($_COOKIE['admin_remember'])) return;

    $db = db();
    $hash = hash('sha256', $_COOKIE['admin_remember']);

    $stmt = $db->prepare("
        SELECT admin_id
        FROM admin_remember_tokens
        WHERE token_hash = ?
          AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $_SESSION['admin_id'] = $row['admin_id'];
    }
}
