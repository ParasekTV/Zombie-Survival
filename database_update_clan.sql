-- ZOMBIE SURVIVAL UPDATE: CLAN SYSTEM
-- Bitte dieses Skript in phpMyAdmin importieren!

USE zombie_survival;

-- 1. Clan Haupt-Tabelle
CREATE TABLE IF NOT EXISTS clans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    tag VARCHAR(6) NOT NULL, -- e.g. [BOSS]
    owner_id INT NOT NULL,
    taler INT DEFAULT 0, -- Clan-Kasse
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Clan Mitglieder
CREATE TABLE IF NOT EXISTS clan_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clan_id INT NOT NULL,
    user_id INT NOT NULL UNIQUE, -- Ein User kann nur in einem Clan sein
    role VARCHAR(20) DEFAULT 'member', -- 'leader', 'officer', 'member'
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (clan_id) REFERENCES clans(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Clan Gebäude (Stadt)
CREATE TABLE IF NOT EXISTS clan_buildings (
    clan_id INT NOT NULL,
    type VARCHAR(50) NOT NULL, -- 'mauer', 'lager', 'hauptquartier'
    level INT DEFAULT 0,
    PRIMARY KEY (clan_id, type),
    FOREIGN KEY (clan_id) REFERENCES clans(id) ON DELETE CASCADE
);

-- 4. Clan Inventar (Gemeinsames Lager)
CREATE TABLE IF NOT EXISTS clan_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clan_id INT NOT NULL,
    item_id INT NOT NULL,
    amount INT DEFAULT 0,
    FOREIGN KEY (clan_id) REFERENCES clans(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id)
);

-- 5. Bewerbungen
CREATE TABLE IF NOT EXISTS clan_applications (
    clan_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (clan_id, user_id),
    FOREIGN KEY (clan_id) REFERENCES clans(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. Clan Pinnwand (Interner Chat)
CREATE TABLE IF NOT EXISTS clan_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clan_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (clan_id) REFERENCES clans(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
