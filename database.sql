-- Datenbank erstellen (falls nicht vorhanden)
CREATE DATABASE IF NOT EXISTS zombie_survival;
USE zombie_survival;

-- Tabelle: users
-- Speichert Login-Daten und Spielstatus
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Spiel-Stats
    health INT DEFAULT 100, -- Gesundheit (0-100)
    hunger INT DEFAULT 0,   -- Hunger (0-100, 100=Verhungert)
    energy INT DEFAULT 100, -- Energie (0-100)
    
    -- Position / Status (für später)
    location VARCHAR(50) DEFAULT 'Safehouse',
    is_alive TINYINT(1) DEFAULT 1
);

-- Pinnwand System
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS likes (
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
