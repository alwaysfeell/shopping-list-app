USE shopping_list_app;

-- Categories
INSERT INTO categories (name) VALUES ('Продукти'),('Одяг'),('Інше')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Users (seed)
INSERT INTO users (username, password_hash, twofa_enabled, twofa_code, failed_attempts, lock_until, created_at) VALUES
('user123', '$2b$12$qDGy16Gj.bmLJ6nPVD7m.O3QKFqPmUuklnmw557lQnckgmB9ZDthO', 0, NULL, 0, NULL, NOW()),
('edge_case_20_chars__', '$2b$12$DWxSs1N4Gj70n.WxOe9nwu8ziOz1s5TUcv07J6io9gV5ypFwzUx8G', 0, NULL, 0, NULL, NOW()),
('lockme', '$2b$12$1vsLWZEBsvTsNxM4LFZW8.41NvDgOJGUV/69Jv1TnYWwVRGLg7nUG', 0, NULL, 0, NULL, NOW()),
('twofa_user', '$2b$12$1rkq.VLZb2YMgN2QWEzIte0ta5MafP9EJUFfeJfV52Y4ozH0bdoTi', 1, '123456', 0, NULL, NOW())
ON DUPLICATE KEY UPDATE username=VALUES(username);

-- Items for user123
SET @uid := (SELECT id FROM users WHERE username='user123' LIMIT 1);
SET @c_food := (SELECT id FROM categories WHERE name='Продукти' LIMIT 1);
SET @c_cloth := (SELECT id FROM categories WHERE name='Одяг' LIMIT 1);
SET @c_other := (SELECT id FROM categories WHERE name='Інше' LIMIT 1);

INSERT INTO items (user_id, category_id, name, price, is_purchased, created_at) VALUES
(@uid, @c_food, 'Молоко 2л', 45.50, 0, NOW()),
(@uid, @c_food, 'Хліб', 19.99, 0, NOW()),
(@uid, @c_food, 'Сир', 120.00, 0, NOW()),
(@uid, @c_cloth, 'Куртка', 1500.00, 0, NOW()),
(@uid, @c_other, 'Ноутбук', 9999.99, 0, NOW()),
(@uid, @c_other, 'Батарейки AA 4шт', 89.90, 1, NOW()),
(@uid, @c_food, 'Вода 0.5л', 0.00, 0, NOW()),
(@uid, @c_food, 'Йогурт полуниця 290г', 28.75, 1, NOW()),
(@uid, @c_other, 'Кабель USB C 2м', 199.00, 0, NOW()),
(@uid, @c_food, 'Назва рівно 100 символів', 10.00, 0, NOW());
