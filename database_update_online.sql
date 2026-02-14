-- ZOMBIE SURVIVAL UPDATE: ONLINE STATUS
-- Bitte dieses Skript in phpMyAdmin importieren!

USE zombie_survival;

-- Spalte für Online-Status
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
