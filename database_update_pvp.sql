-- ZOMBIE SURVIVAL UPDATE: PVP
-- Bitte dieses Skript in phpMyAdmin importieren!

USE zombie_survival;

-- Spalte für Angriffs-Cooldown
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_attack TIMESTAMP NULL;
