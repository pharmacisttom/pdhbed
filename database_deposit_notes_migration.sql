-- Run this once on the app database (test) with a DB user that has CREATE/ALTER privileges.
-- The runtime user webtomdb only needs SELECT/INSERT/UPDATE/DELETE after this migration.

CREATE TABLE IF NOT EXISTS deposit_due_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    an VARCHAR(20) NOT NULL,
    hn VARCHAR(20) DEFAULT NULL,
    pt_name VARCHAR(255) DEFAULT NULL,
    note TEXT NULL,
    updated_by VARCHAR(100) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_deposit_due_note_an (an)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Run this only if custom_deposits.note does not already exist.
ALTER TABLE custom_deposits ADD COLUMN note TEXT NULL AFTER amount;
