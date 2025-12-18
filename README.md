# Shopping List Web App (PHP + MySQL, XAMPP)

Функції: реєстрація/логін (з хешем пароля), блокування після 3 невдалих спроб, опціонально 2FA, CRUD товарів, категорії, фільтр, статус "придбано", сума лише непозначених, імпорт/експорт JSON/CSV.

## Швидкий старт (XAMPP)
1) Скопіюй папку `shopping-list-app` в `C:\xampp\htdocs\shopping-list-app`
2) Запусти **Apache** та **MySQL** у XAMPP.
3) Відкрий `http://localhost/phpmyadmin`
4) Створи БД `shopping_list_app` (utf8mb4).
5) Імпортуй `sql/schema.sql`, потім `sql/seed.sql`.
6) Перевір `app/config.php` (логін/пароль MySQL).
7) Відкрий `http://localhost/shopping-list-app/public/`

## Seed логіни
- user123 / Abcdef1!
- lockme / Correct1! (зручно для тесту блокування)
- twofa_user / Abcdef1! (2FA код за замовчуванням: **123456**)

> Якщо хочеш — можу під твій шаблон “Лаб6” зробити окремий docx: архітектура + інструкція інсталяції + “що де лежить”.
