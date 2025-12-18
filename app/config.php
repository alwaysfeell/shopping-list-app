<?php
// app/config.php
// Налаштування БД під XAMPP (за замовчуванням root без паролю)
return [
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'shopping_list_app',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    // після 3 фейлів блокуємо на N хвилин
    'lock_minutes' => 10,
    // 2FA (для навчальних цілей): код зберігається у БД (users.twofa_code)
];
