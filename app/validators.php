<?php
// app/validators.php

function validate_username(string $username): ?string {
    $u = trim($username);
    if (strlen($u) < 3 || strlen($u) > 20) return "Username має бути 3–20 символів.";
    if (!preg_match('/^[A-Za-z0-9_]+$/', $u)) return "Username: тільки латиниця/цифри/_";
    return null;
}

function validate_password(string $password): ?string {
    if (strlen($password) < 8) return "Пароль мінімум 8 символів.";
    return null;
}

// Назва: літери/цифри/пробіли, 1..100 (unicode)
function validate_item_name(string $name): ?string {
    $n = trim($name);
    if ($n === '') return "Назва обов'язкова.";
    if (mb_strlen($n) > 100) return "Назва максимум 100 символів.";
    if (!preg_match('/^[\p{L}\p{N} ]+$/u', $n)) return "Назва: дозволено лише літери/цифри/пробіли.";
    return null;
}

function normalize_price(string $price): ?string {
    $p = trim($price);
    $p = str_replace(',', '.', $p);
    return $p;
}

function validate_price(string $price): ?string {
    $p = normalize_price($price);
    if ($p === '') return "Ціна обов'язкова.";
    if (!preg_match('/^\d+(\.\d{1,3})?$/', $p)) return "Ціна має бути числом (можна з крапкою).";
    $val = floatval($p);
    if ($val < 0 || $val > 9999.99) return "Ціна має бути в діапазоні 0–9999.99.";
    return null;
}
