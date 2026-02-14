<?php
// db_patch_equipment.php
require_once 'config.php';
include 'includes/header.php';

$message = '';

if (isset($_POST['run_patch'])) {
    try {
        $pdo->beginTransaction();

        // 1. Spalten in 'users'
        $cols = ['eq_weapon_id' => 'INT DEFAULT NULL', 'eq_armor_id' => 'INT DEFAULT NULL'];
        foreach ($cols as $col => $def) {
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN $col $def");
                $pdo->exec("ALTER TABLE users ADD FOREIGN KEY ($col) REFERENCES items(id) ON DELETE SET NULL");
            }
            catch (PDOException $e) { /* Ignore if exists */
            }
        }

        // 2. Spalte 'effect_value' in 'items'
        try {
            $pdo->exec("ALTER TABLE items ADD COLUMN effect_value INT DEFAULT 0");
        }
        catch (PDOException $e) { /* Ignore */
        }

        // 3. Werte setzen
        // Waffen (Atk)
        $pdo->exec("UPDATE items SET effect_value = 5 WHERE name = 'Messer'");
        $pdo->exec("UPDATE items SET effect_value = 10 WHERE name = 'Speer'");
        $pdo->exec("UPDATE items SET effect_value = 15 WHERE name = 'Machete'");

        // Rüstung (Def)
        $pdo->exec("UPDATE items SET effect_value = 10 WHERE name = 'Kevlar Weste'");

        // 4. Item-Types vereinheitlichen (falls noch nicht geschehen)
        $pdo->exec("UPDATE items SET type = 'weapon' WHERE name IN ('Messer', 'Speer', 'Machete')");
        $pdo->exec("UPDATE items SET type = 'armor' WHERE name IN ('Kevlar Weste')");

        $pdo->commit();
        $message = "<div class='alert alert-success'>Ausrüstungs-Update erfolgreich! (Slots & Item-Werte)</div>";

    }
    catch (PDOException $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-error'>Fehler: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="content-box">
    <h2>Datenbank Patch: Ausrüstung</h2>
    <p>Legt Ausrüstungs-Slots an und aktualisiert Item-Werte (Angriff/Verteidigung).</p>
    
    <?php echo $message; ?>
    
    <form method="post">
        <button type="submit" name="run_patch" style="background: green;">Update ausführen</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
