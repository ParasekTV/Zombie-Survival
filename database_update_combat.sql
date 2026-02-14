-- Stats for Combat
ALTER TABLE users ADD COLUMN attack INT DEFAULT 10;
ALTER TABLE users ADD COLUMN defense INT DEFAULT 0;
ALTER TABLE users ADD COLUMN survival_skill INT DEFAULT 0;

-- New Item Types (Using existing 'weapon' type for Machete)
INSERT INTO items (name, type, description, value) VALUES 
('Machete', 'weapon', 'Scharfe Klinge (Atk +15)', 15),
('Kevlar Weste', 'armor', 'Schützt vor Schaden (Def +10)', 10),
('Medikit', 'consumable', 'Vollständige Heilung (+100 HP)', 100);
