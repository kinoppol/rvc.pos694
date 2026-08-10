<?php
declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE merchants (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            tax_id VARCHAR(20) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE branches (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            merchant_id INT UNSIGNED NOT NULL,
            name VARCHAR(191) NOT NULL,
            address VARCHAR(255) NULL,
            lat DECIMAL(10,7) NULL,
            lng DECIMAL(10,7) NULL,
            geofence_radius_m INT UNSIGNED NOT NULL DEFAULT 50,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_branches_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE pos_terminals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_id INT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            device_fingerprint VARCHAR(191) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_terminals_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            merchant_id INT UNSIGNED NOT NULL,
            branch_id INT UNSIGNED NULL,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(191) NULL,
            full_name VARCHAR(191) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            pin_hash VARCHAR(255) NULL,
            role ENUM('owner','manager','cashier','staff') NOT NULL DEFAULT 'staff',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_users_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE,
            CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            merchant_id INT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            CONSTRAINT fk_categories_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE products (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            merchant_id INT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NULL,
            name VARCHAR(191) NOT NULL,
            base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            markdown_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            image_note VARCHAR(191) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_products_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE,
            CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE product_variants (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id INT UNSIGNED NOT NULL,
            sku VARCHAR(64) NOT NULL UNIQUE,
            size VARCHAR(20) NULL,
            color VARCHAR(40) NULL,
            barcode VARCHAR(64) NULL,
            price_override DECIMAL(10,2) NULL,
            CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE stock_levels (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_id INT UNSIGNED NOT NULL,
            variant_id INT UNSIGNED NOT NULL,
            quantity INT NOT NULL DEFAULT 0,
            reorder_point INT UNSIGNED NOT NULL DEFAULT 10,
            UNIQUE KEY uniq_branch_variant (branch_id, variant_id),
            CONSTRAINT fk_stock_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
            CONSTRAINT fk_stock_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE stock_transfers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            merchant_id INT UNSIGNED NOT NULL,
            code VARCHAR(30) NOT NULL UNIQUE,
            from_branch_id INT UNSIGNED NOT NULL,
            to_branch_id INT UNSIGNED NOT NULL,
            status ENUM('pending','shipped','received','cancelled') NOT NULL DEFAULT 'pending',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_transfers_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE,
            CONSTRAINT fk_transfers_from FOREIGN KEY (from_branch_id) REFERENCES branches(id) ON DELETE CASCADE,
            CONSTRAINT fk_transfers_to FOREIGN KEY (to_branch_id) REFERENCES branches(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE stock_transfer_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            transfer_id INT UNSIGNED NOT NULL,
            variant_id INT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            CONSTRAINT fk_transfer_items_transfer FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
            CONSTRAINT fk_transfer_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE member_tiers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            merchant_id INT UNSIGNED NOT NULL,
            name VARCHAR(50) NOT NULL,
            min_spend DECIMAL(10,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_tiers_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE members (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            merchant_id INT UNSIGNED NOT NULL,
            tier_id INT UNSIGNED NULL,
            full_name VARCHAR(191) NOT NULL,
            phone VARCHAR(20) NULL,
            birthdate DATE NULL,
            points INT NOT NULL DEFAULT 0,
            total_spend DECIMAL(12,2) NOT NULL DEFAULT 0,
            member_since DATE NOT NULL,
            CONSTRAINT fk_members_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE,
            CONSTRAINT fk_members_tier FOREIGN KEY (tier_id) REFERENCES member_tiers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE coupons (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            merchant_id INT UNSIGNED NOT NULL,
            code VARCHAR(40) NOT NULL,
            discount_type ENUM('percent','amount') NOT NULL DEFAULT 'amount',
            discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE KEY uniq_merchant_code (merchant_id, code),
            CONSTRAINT fk_coupons_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE sales (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            merchant_id INT UNSIGNED NOT NULL,
            branch_id INT UNSIGNED NOT NULL,
            member_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NOT NULL,
            invoice_no VARCHAR(40) NOT NULL UNIQUE,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            vat_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            grand_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            coupon_code VARCHAR(40) NULL,
            status ENUM('held','completed','refunded','voided') NOT NULL DEFAULT 'held',
            cart_snapshot LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_sales_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id) ON DELETE CASCADE,
            CONSTRAINT fk_sales_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
            CONSTRAINT fk_sales_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
            CONSTRAINT fk_sales_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE sale_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sale_id INT UNSIGNED NOT NULL,
            variant_id INT UNSIGNED NULL,
            product_name_snapshot VARCHAR(191) NOT NULL,
            variant_label_snapshot VARCHAR(100) NULL,
            qty INT UNSIGNED NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            line_discount DECIMAL(10,2) NOT NULL DEFAULT 0,
            CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            CONSTRAINT fk_sale_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sale_id INT UNSIGNED NOT NULL,
            method ENUM('promptpay','cash','card') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            tendered DECIMAL(10,2) NULL,
            change_amount DECIMAL(10,2) NULL,
            reference VARCHAR(60) NULL,
            paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_payments_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE attendance_shifts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_id INT UNSIGNED NOT NULL,
            name VARCHAR(50) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            CONSTRAINT fk_shifts_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE attendance_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            branch_id INT UNSIGNED NOT NULL,
            shift_id INT UNSIGNED NULL,
            clock_type ENUM('in','out') NOT NULL,
            lat DECIMAL(10,7) NOT NULL,
            lng DECIMAL(10,7) NOT NULL,
            distance_m DECIMAL(8,2) NOT NULL,
            within_geofence TINYINT(1) NOT NULL,
            device_fingerprint VARCHAR(191) NULL,
            clocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_logs_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
            CONSTRAINT fk_logs_shift FOREIGN KEY (shift_id) REFERENCES attendance_shifts(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(PDO $db): void
    {
        $tables = [
            'attendance_logs', 'attendance_shifts', 'payments', 'sale_items', 'sales',
            'coupons', 'members', 'member_tiers', 'stock_transfer_items', 'stock_transfers',
            'stock_levels', 'product_variants', 'products', 'categories', 'users',
            'pos_terminals', 'branches', 'merchants',
        ];
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $t) {
            $db->exec("DROP TABLE IF EXISTS `$t`");
        }
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    }
};
