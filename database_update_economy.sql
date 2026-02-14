-- ZOMBIE SURVIVAL UPDATE: ECONOMY & CRAFTING
-- Bitte dieses Skript in phpMyAdmin importieren!

USE zombie_survival;

-- 1. Neue Spalten für User (Währung, Tresor, Schlafen)
ALTER TABLE users ADD COLUMN IF NOT EXISTS taler INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS base_level_vault INT DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_sleep TIMESTAMP NULL;

-- 2. Items für Waffen/Werkzeuge (Falls nicht vorhanden)
INSERT INTO items (name, type, description, value) SELECT * FROM (SELECT 'Messer', 'weapon', 'Einfaches Messer (Atk +5)', 5) AS tmp WHERE NOT EXISTS (SELECT name FROM items WHERE name = 'Messer') LIMIT 1;
INSERT INTO items (name, type, description, value) SELECT * FROM (SELECT 'Speer', 'weapon', 'Spitzer Speer (Atk +10)', 10) AS tmp WHERE NOT EXISTS (SELECT name FROM items WHERE name = 'Speer') LIMIT 1;
INSERT INTO items (name, type, description, value) SELECT * FROM (SELECT 'Axt', 'tool', 'Werkzeug zum Abbauen (Effizienz +)', 1) AS tmp WHERE NOT EXISTS (SELECT name FROM items WHERE name = 'Axt') LIMIT 1;
INSERT INTO items (name, type, description, value) SELECT * FROM (SELECT 'Verband', 'health', 'Heilt Wunden (+50 HP)', 50) AS tmp WHERE NOT EXISTS (SELECT name FROM items WHERE name = 'Verband') LIMIT 1;

-- 3. Clan Tabellen (Vorbereitung für Phase 3)
CREATE TABLE IF NOT EXISTS clans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    tag VARCHAR(5) NOT NULL,
    owner_id INT NOT NULL,
    taler_storage INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clan_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clan_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(20) DEFAULT 'member', -- 'leader', 'officer', 'member'
    FOREIGN KEY (clan_id) REFERENCES clans(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS clan_buildings (
    clan_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    level INT DEFAULT 0,
    PRIMARY KEY (clan_id, type),
    FOREIGN KEY (clan_id) REFERENCES clans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS clan_applications (
    clan_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (clan_id, user_id)
);
