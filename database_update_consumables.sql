-- Insert or Ignore new items
INSERT IGNORE INTO items (name, type, description, value) VALUES 
('Kaffee', 'consumable', 'Ein heißer Wachmacher. +10 Energie.', 10),
('Energy Drink', 'consumable', 'Flüssige Energie. +10 Energie.', 10);

-- Update existing items to be consumable if they aren't already
UPDATE items SET type='consumable', value=10, description='Stillt den Hunger. +10 Nahrung.' WHERE name IN ('Konserve', 'Wasser', 'Pilze', 'Fleisch');

-- Ensure they exist if they were only hardcoded in looten.php before (safety net)
INSERT IGNORE INTO items (name, type, description, value) VALUES 
('Konserve', 'consumable', 'Eine Dose Bohnen. +10 Nahrung.', 10),
('Wasser', 'consumable', 'Frisches Wasser. +10 Nahrung.', 10),
('Pilze', 'consumable', 'Waldpilze. +10 Nahrung.', 10),
('Fleisch', 'consumable', 'Gebratenes Fleisch. +10 Nahrung.', 10);
