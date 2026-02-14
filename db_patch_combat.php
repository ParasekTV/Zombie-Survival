<?php
// db_patch_combat.php
require_once 'config.php';
include 'includes/header.php';

$message = '';

if (isset($_POST['run_patch'])) {
    try {
        $pdo->beginTransaction();

        // 1. Spalten
        $cols = ['attack' => 'INT DEFAULT 10', 'defense' => 'INT DEFAULT 0', 'survival_skill' => 'INT DEFAULT 0'];
        foreach ($cols as $col => $def) {
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN $col $def");
            }
            catch (PDOException $e) { /* Ignore duplicate column */
            }
        }

        // 2. Items
        $newItems = [
            ['Machete', 'weapon', 'Scharfe Klinge (Atk +15)', 15],
            ['Kevlar Weste', 'armor', 'Schützt vor Schaden (Def +10)', 10],
            ['Medikit', 'consumable', 'Vollständige Heilung (+100 HP)', 100]
        ];

        foreach ($newItems as $item) {
            $stmt = $pdo->prepare("SELECT id FROM items WHERE name = ?");
            $stmt->execute([$item[0]]);
            if (!$stmt->fetch()) {
                $pdo->prepare("INSERT INTO items (name, type, description, value) VALUES (?, ?, ?, ?)")->execute($item);
            }
        }

        $pdo->commit();
        $message = "<div class='alert alert-success'>Kampf-Update erfolgreich! (Attack, Defense, Survival Skill, Neue Items)</div>";

    }
    catch (PDOException $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-error'>Fehler: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="content-box">
    <h2>Datenbank Patch: Kampf-Update</h2>
    <p>Fügt Angriffs- und Verteidigungswerte, den Überlebens-Skill und neue Items hinzu.</p>
    
    <?php echo $message; ?>
    
    <form method="post">
        <button type="submit" name="run_patch" style="background: green;">Update ausführen</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
