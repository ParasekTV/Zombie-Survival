<?php
// db_patch_base.php
require_once 'config.php';
include 'includes/header.php';

$message = '';

if (isset($_POST['run_patch'])) {
    try {
        $pdo->beginTransaction();

        // 1. Tabelle base_inventory
        $pdo->exec("CREATE TABLE IF NOT EXISTS base_inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            item_id INT NOT NULL,
            amount INT DEFAULT 1,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES items(id)
        )");

        // 2. Spalten für Defense (Prüfen ob existiert via try-catch im Loop oder ignorieren von Fehlern)
        // Einfacher Trick: Duplicate Column Name ist ein Fehlercode, den wir fangen können.

        $cols = ['base_level_traps', 'base_level_turrets'];
        foreach ($cols as $col) {
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN $col INT DEFAULT 0");
            }
            catch (PDOException $e) {
                // Ignore "Column already exists"
                if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                // Echtes Problem?
                }
            }
        }

        $pdo->commit();
        $message = "<div class='alert alert-success'>Datenbank erfolgreich für Basis-Erweiterung aktualisiert!</div>";

    }
    catch (PDOException $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-error'>Fehler: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="content-box">
    <h2>Datenbank Patch: Basis Erweiterung</h2>
    <p>Legt die Tabelle <code>base_inventory</code> an und fügt Spalten für Fallen/Geschütze hinzu.</p>
    
    <?php echo $message; ?>
    
    <form method="post">
        <button type="submit" name="run_patch" style="background: green;">Update ausführen</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
