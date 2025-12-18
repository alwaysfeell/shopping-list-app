<?php
// app/auth.php
require_once __DIR__ . '/helpers.php';

function auth_user(): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    return $_SESSION['user'] ?? null;
}

function require_auth(): void {
    if (!auth_user()) {
        redirect('login.php');
    }
}

function auth_login(array $userRow): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['user'] = [
        'id' => (int)$userRow['id'],
        'username' => $userRow['username'],
        'twofa_enabled' => (int)$userRow['twofa_enabled'],
    ];
}

function auth_logout(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    session_unset();
    session_destroy();
}
