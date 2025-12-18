<?php
require_once __DIR__ . '/../app/auth.php';
if (auth_user()) {
    header('Location: app.php');
} else {
    header('Location: login.php');
}
exit;
