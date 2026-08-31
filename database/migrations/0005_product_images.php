<?php
declare(strict_types=1);

return new class {
    public function up(\PDO $db): void
    {
        // Uploaded image filenames (stored under storage/uploads/products/, served
        // through MediaController since storage/ is not web-readable). image_note
        // stays as the emoji/text fallback shown when there is no uploaded image.
        $db->exec("ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL AFTER image_note");
        $db->exec("ALTER TABLE product_variants ADD COLUMN image_path VARCHAR(255) NULL AFTER barcode");
    }

    public function down(\PDO $db): void
    {
        $db->exec("ALTER TABLE product_variants DROP COLUMN image_path");
        $db->exec("ALTER TABLE products DROP COLUMN image_path");
    }
};
