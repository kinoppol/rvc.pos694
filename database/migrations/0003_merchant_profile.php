<?php
declare(strict_types=1);

return new class {
    public function up(\PDO $db): void
    {
        $db->exec("ALTER TABLE merchants
            ADD COLUMN phone VARCHAR(30) NULL AFTER tax_id,
            ADD COLUMN email VARCHAR(191) NULL AFTER phone,
            ADD COLUMN address VARCHAR(255) NULL AFTER email");
    }

    public function down(\PDO $db): void
    {
        $db->exec("ALTER TABLE merchants DROP COLUMN address, DROP COLUMN email, DROP COLUMN phone");
    }
};
