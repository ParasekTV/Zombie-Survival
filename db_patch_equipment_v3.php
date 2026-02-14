<?php
// db_patch_equipment_v3.php
require_once 'config.php';

echo "<h2>Applying Equipment Expansion Patch (Helmet & Shoes)...</h2>";

try {
    // 1. ADD COLUMNS TO USERS
    $columns = [
        'eq_helmet_id' => 'INT DEFAULT NULL',
        'eq_shoes_id' => 'INT DEFAULT NULL'
    ];

    foreach ($columns as $col => $def) {
        try {
            $pdo->query("ALTER TABLE users ADD COLUMN $col $def");
            echo "Added column: $col<br>";
        }
        catch (PDOException $e) {
            echo "Column $col already exists or error: " . $e->getMessage() . "<br>";
        }
    }

    // 2. ADD FOREIGN KEYS
    try {
        $pdo->query("ALTER TABLE users ADD CONSTRAINT fk_user_helmet FOREIGN KEY (eq_helmet_id) REFERENCES items(id) ON DELETE SET NULL");
        echo "Added FK: fk_user_helmet<br>";
    }
    catch (PDOException $e) {
        echo "FK helmet error/exists: " . $e->getMessage() . "<br>";
    }

    try {
        $pdo->query("ALTER TABLE users ADD CONSTRAINT fk_user_shoes FOREIGN KEY (eq_shoes_id) REFERENCES items(id) ON DELETE SET NULL");
        echo "Added FK: fk_user_shoes<br>";
    }
    catch (PDOException $e) {
        echo "FK shoes error/exists: " . $e->getMessage() . "<br>";
    }

    // 3. ADD NEW ITEMS
    $newItems = [
        ['Helm', 'helmet', 5], // +5 Defense
        ['Stiefel', 'shoes', 3] // +3 Defense
    ];

    foreach ($newItems as $item) {
        $stmt = $pdo->prepare("SELECT id FROM items WHERE name = ?");
        $stmt->execute([$item[0]]);
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO items (name, type, effect_value) VALUES (?, ?, ?)")
                ->execute($item);
            echo "Inserted item: {$item[0]}<br>";
        }
        else {
            echo "Item {$item[0]} already exists.<br>";
        }
    }

    echo "<h3 style='color: green;'>Patch applied successfully!</h3>";

}
catch (PDOException $e) {
    echo "<h3 style='color: red;'>Critical Error: " . $e->getMessage() . "</h3>";
}
?>
