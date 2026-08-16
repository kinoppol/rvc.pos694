<?php
declare(strict_types=1);

return new class {
    public function up(\PDO $db): void
    {
        $db->exec("ALTER TABLE merchants
            ADD COLUMN status ENUM('pending','active','suspended') NOT NULL DEFAULT 'active' AFTER tax_id,
            ADD COLUMN is_platform TINYINT(1) NOT NULL DEFAULT 0 AFTER status");

        // mark the first merchant (created by install.php) as the platform merchant
        $db->exec("UPDATE merchants SET is_platform = 1 ORDER BY id LIMIT 1");

        $db->exec("CREATE TABLE system_settings (
            `key`   VARCHAR(80)  NOT NULL,
            `value` TEXT         NOT NULL DEFAULT '',
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("INSERT INTO system_settings (`key`, `value`) VALUES ('require_approval', '0')");
    }

    public function down(\PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS system_settings");
        $db->exec("ALTER TABLE merchants DROP COLUMN is_platform, DROP COLUMN status");
    }
};
