USE zombie_survival;

-- Versuche die Spalte hinzuzufügen.
-- Falls ein Fehler "Duplicate column name" kommt, ist das GUT! (Dann existiert sie schon).
ALTER TABLE users ADD COLUMN vault_taler INT DEFAULT 0;
