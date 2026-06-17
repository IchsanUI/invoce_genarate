-- ============================================================
-- Invoice Generator — Default Seed Data
-- ============================================================
-- Run AFTER schema.sql has been executed.
--
-- IMPORTANT:
-- - Default super admin password is "superadmin123" (CHANGE THIS!).
-- - The bcrypt hash below corresponds to that password (cost = 12).
-- - Generate a new hash with:
--     php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_BCRYPT, ['cost' => 12]);"
-- ============================================================

USE `invoice_system`;

-- 1. Default super admin ----------------------------------
-- Username : superadmin
-- Password : superadmin123
-- Role     : superadmin
-- (PASSWORD_HASH for "superadmin123")
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `is_active`, `created_at`)
VALUES (
    'superadmin',
    '$2y$12$zQkv6myNRY8oSjo2/LV3TuRtz/eZzeYGp1S9/6.9iv5v.yEQW.VMe',
    'Super Administrator',
    'superadmin',
    1,
    NOW()
);

-- 2. Default company_settings -----------------------------
INSERT INTO `company_settings` (
    `id`, `company_name`, `address`, `phone`, `email`, `website`,
    `logo_path`, `bank_name`, `bank_account_number`, `bank_account_name`,
    `updated_at`, `updated_by`
)
VALUES (
    1,
    'AS Stuff',
    'Jl. Contoh Alamat No. 123, Jakarta',
    '+62 812-3456-7890',
    'info@asstuff.com',
    'https://asstuff.com',
    NULL,
    'BCA',
    '1234567890',
    'AS Stuff',
    NOW(),
    (SELECT `id` FROM `users` WHERE `username` = 'superadmin' LIMIT 1)
);