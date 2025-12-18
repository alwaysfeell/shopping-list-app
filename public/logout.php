<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';
auth_logout();
flash_set('info', 'Вийшов. Повернись, коли будеш готовий 😄');
redirect('login.php');