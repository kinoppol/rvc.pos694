<?php
declare(strict_types=1);

return new class {
    public function up(\PDO $db): void
    {
        // track_stock = 0 lets the POS sell any variant regardless of stock_levels
        // (no "out of stock" lock, no deduction on payment).
        $db->exec("ALTER TABLE merchants ADD COLUMN track_stock TINYINT(1) NOT NULL DEFAULT 1 AFTER join_code");
    }

    public function down(\PDO $db): void
    {
        $db->exec("ALTER TABLE merchants DROP COLUMN track_stock");
    }
};
