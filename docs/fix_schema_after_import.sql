-- ============================================================
-- FIX-ALL: Sync schema setelah import DB lama
-- Jalankan di phpMyAdmin / MySQL client
-- Idempotent: aman di-run berkali-kali
-- ============================================================

-- 1. USERS: kolom WhatsApp
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
     AND COLUMN_NAME = 'receives_transaction_whatsapp') = 0,
    'ALTER TABLE `users` ADD COLUMN `receives_transaction_whatsapp` TINYINT(1) NOT NULL DEFAULT 1 AFTER `receives_transaction_email`',
    'SELECT "kolom receives_transaction_whatsapp sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. PEMBELIANS: kontak_id
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembelians'
     AND COLUMN_NAME = 'kontak_id') = 0,
    'ALTER TABLE `pembelians` ADD COLUMN `kontak_id` BIGINT UNSIGNED NULL AFTER `gudang_id`',
    'SELECT "kolom kontak_id sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. PEMBELIANS: tipe_harga
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembelians'
     AND COLUMN_NAME = 'tipe_harga') = 0,
    'ALTER TABLE `pembelians` ADD COLUMN `tipe_harga` VARCHAR(255) NOT NULL DEFAULT ''retail''',
    'SELECT "kolom tipe_harga sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. PEMBELIANS: no_referensi
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembelians'
     AND COLUMN_NAME = 'no_referensi') = 0,
    'ALTER TABLE `pembelians` ADD COLUMN `no_referensi` VARCHAR(255) NULL',
    'SELECT "kolom no_referensi sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. PEMBELIANS: no_resi
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembelians'
     AND COLUMN_NAME = 'no_resi') = 0,
    'ALTER TABLE `pembelians` ADD COLUMN `no_resi` VARCHAR(255) NULL',
    'SELECT "kolom no_resi sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6. PEMBELIANS: biaya_pengiriman
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembelians'
     AND COLUMN_NAME = 'biaya_pengiriman') = 0,
    'ALTER TABLE `pembelians` ADD COLUMN `biaya_pengiriman` DECIMAL(15,2) NULL DEFAULT 0',
    'SELECT "kolom biaya_pengiriman sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7. PENJUALANS: no_resi
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'penjualans'
     AND COLUMN_NAME = 'no_resi') = 0,
    'ALTER TABLE `penjualans` ADD COLUMN `no_resi` VARCHAR(255) NULL',
    'SELECT "kolom no_resi sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 8. PENJUALANS: biaya_pengiriman
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'penjualans'
     AND COLUMN_NAME = 'biaya_pengiriman') = 0,
    'ALTER TABLE `penjualans` ADD COLUMN `biaya_pengiriman` DECIMAL(15,2) NULL DEFAULT 0',
    'SELECT "kolom biaya_pengiriman sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 9. PEMBAYARANS: type
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayarans'
     AND COLUMN_NAME = 'type') = 0,
    'ALTER TABLE `pembayarans` ADD COLUMN `type` VARCHAR(255) NOT NULL DEFAULT ''piutang''',
    'SELECT "kolom type sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 10. PEMBAYARANS: pembelian_id
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayarans'
     AND COLUMN_NAME = 'pembelian_id') = 0,
    'ALTER TABLE `pembayarans` ADD COLUMN `pembelian_id` BIGINT UNSIGNED NULL',
    'SELECT "kolom pembelian_id sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 11. STOCK OPNAMES: tabel baru
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_opnames') = 0,
    'CREATE TABLE `stock_opnames` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `uuid` CHAR(36) NOT NULL,
        `user_id` BIGINT UNSIGNED NOT NULL,
        `approver_id` BIGINT UNSIGNED NULL,
        `gudang_id` BIGINT UNSIGNED NOT NULL,
        `no_urut_harian` INT NULL,
        `nomor` VARCHAR(255) NULL,
        `tgl_opname` DATE NULL,
        `status` VARCHAR(255) NOT NULL DEFAULT ''Draft'',
        `memo` TEXT NULL,
        `lampiran_paths` JSON NULL,
        `created_at` TIMESTAMP NULL,
        `updated_at` TIMESTAMP NULL,
        UNIQUE KEY `stock_opnames_uuid_unique` (`uuid`),
        INDEX `stock_opnames_nomor_index` (`nomor`),
        INDEX `stock_opnames_tgl_opname_index` (`tgl_opname`),
        INDEX `stock_opnames_status_index` (`status`),
        INDEX `stock_opnames_user_id_status_index` (`user_id`, `status`),
        INDEX `stock_opnames_created_at_index` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "tabel stock_opnames sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 12. STOCK OPNAME ITEMS: tabel baru
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_opname_items') = 0,
    'CREATE TABLE `stock_opname_items` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `stock_opname_id` BIGINT UNSIGNED NOT NULL,
        `produk_id` BIGINT UNSIGNED NOT NULL,
        `batch_number` VARCHAR(255) NULL,
        `expired_date` DATE NULL,
        `qty_system` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `qty_aktual` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `selisih` DECIMAL(10,2) NOT NULL DEFAULT 0,
        `keterangan` TEXT NULL,
        `created_at` TIMESTAMP NULL,
        `updated_at` TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "tabel stock_opname_items sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 13. TUTUP BUKU: tabel baru
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tutup_buku') = 0,
    'CREATE TABLE `tutup_buku` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT UNSIGNED NOT NULL,
        `gudang_id` BIGINT UNSIGNED NOT NULL,
        `tgl_tutup_buku` DATE NOT NULL,
        `total_penjualan` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `total_pembelian` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `total_biaya` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `total_piutang` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `total_hutang` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `catatan` TEXT NULL,
        `status` VARCHAR(255) NOT NULL DEFAULT ''Draft'',
        `created_at` TIMESTAMP NULL,
        `updated_at` TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "tabel tutup_buku sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 14. NOTIFICATIONS: tabel baru
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications') = 0,
    'CREATE TABLE `notifications` (
        `id` CHAR(36) NOT NULL PRIMARY KEY,
        `type` VARCHAR(255) NOT NULL,
        `notifiable_type` VARCHAR(255) NOT NULL,
        `notifiable_id` BIGINT UNSIGNED NOT NULL,
        `data` TEXT NOT NULL,
        `read_at` TIMESTAMP NULL,
        `created_at` TIMESTAMP NULL,
        `updated_at` TIMESTAMP NULL,
        INDEX `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`, `notifiable_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "tabel notifications sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 15. SESSIONS: tabel baru
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sessions') = 0,
    'CREATE TABLE `sessions` (
        `id` VARCHAR(255) NOT NULL PRIMARY KEY,
        `user_id` BIGINT UNSIGNED NULL,
        `ip_address` VARCHAR(45) NULL,
        `user_agent` TEXT NULL,
        `payload` LONGTEXT NOT NULL,
        `last_activity` INT NOT NULL,
        INDEX `sessions_user_id_index` (`user_id`),
        INDEX `sessions_last_activity_index` (`last_activity`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "tabel sessions sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 16. CACHE: tabel baru
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cache') = 0,
    'CREATE TABLE `cache` (
        `key` VARCHAR(255) NOT NULL PRIMARY KEY,
        `value` MEDIUMTEXT NOT NULL,
        `expiration` INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "tabel cache sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cache_locks') = 0,
    'CREATE TABLE `cache_locks` (
        `key` VARCHAR(255) NOT NULL PRIMARY KEY,
        `owner` VARCHAR(255) NOT NULL,
        `expiration` INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "tabel cache_locks sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 17. JOBS: tabel baru
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs') = 0,
    'CREATE TABLE `jobs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `queue` VARCHAR(255) NOT NULL,
        `payload` LONGTEXT NOT NULL,
        `attempts` TINYINT UNSIGNED NOT NULL,
        `reserved_at` INT UNSIGNED NULL,
        `available_at` INT UNSIGNED NOT NULL,
        `created_at` INT UNSIGNED NOT NULL,
        INDEX `jobs_queue_index` (`queue`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "tabel jobs sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'failed_jobs') = 0,
    'CREATE TABLE `failed_jobs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `uuid` VARCHAR(255) NOT NULL,
        `connection` TEXT NOT NULL,
        `queue` TEXT NOT NULL,
        `payload` LONGTEXT NOT NULL,
        `exception` LONGTEXT NOT NULL,
        `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    'SELECT "tabel failed_jobs sudah ada" AS status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'DONE: Semua schema sudah di-sync!' AS result;
