<?php
declare(strict_types=1);

return new class {
    public function up(\PDO $db): void
    {
        // The "store group" join code: a headquarters merchant hands this out so a
        // manager of another physical shop can register their shop as an extra
        // branch of the same merchant instead of creating a separate merchant.
        $db->exec("ALTER TABLE merchants
            ADD COLUMN join_code VARCHAR(12) NULL AFTER is_platform,
            ADD UNIQUE KEY uq_merchants_join_code (join_code)");
    }

    public function down(\PDO $db): void
    {
        $db->exec("ALTER TABLE merchants
            DROP KEY uq_merchants_join_code,
            DROP COLUMN join_code");
    }
};
