-- ZOMBIE SURVIVAL UPDATE: ITEMS, INVENTAR & BASE
-- Bitte dieses Skript in phpMyAdmin importieren!

USE zombie_survival;

-- 1. Tabelle für Gegenstände
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(20) NOT NULL, -- 'resource', 'food', 'book', 'weapon'
    description TEXT,
    value INT DEFAULT 1 -- Heilungswert oder Bau-Wert
);

-- 2. Tabelle für Inventar
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    amount INT DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id)
);

-- 3. Base-Stats zum User hinzufügen (falls noch nicht vorhanden)
-- Hinweis: Falls diese Spalten schon da sind, gibt es einen Fehler, den man ignorieren kann.
-- Alternativ einzeln prüfen.
ALTER TABLE users ADD COLUMN base_level_wall INT DEFAULT 0;
ALTER TABLE users ADD COLUMN base_level_storage INT DEFAULT 0;

-- 4. Items befüllen (Nur wenn sie noch nicht existieren)
INSERT INTO items (name, type, description, value) VALUES 
('Holz', 'resource', 'Baumaterial aus dem Wald', 1),
('Stein', 'resource', 'Harter Stein zum Bauen', 1),
('Eisen', 'resource', 'Metall aus der Stadt oder Mine', 2),
('Kohle', 'resource', 'Brennstoff aus der Mine', 1),
('Beton', 'resource', 'Stabiles Baumaterial aus der Stadt', 3),
('Wasser', 'food', 'Erfrischendes Wasser (+10 Energie)', 10),
('Konserve', 'food', 'Alte Bohnen, aber essbar (+20 Hunger)', 20),
('Pilze', 'food', 'Waldpilze (+5 Hunger)', 5),
('Buch: Überleben', 'book', 'Lehrt dich Überlebenstechniken', 50);
