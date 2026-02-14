<?php
// db_patch.php
require_once 'config.php';
include 'includes/header.php';

$message = '';

if (isset($_POST['run_patch'])) {
    try {
        // Prüfen ob Spalte existiert
        $exists = false;
        try {
            $pdo->query("SELECT vault_taler FROM users LIMIT 1");
            $exists = true;
        }
        catch (PDOException $e) {
        // Spalte existiert nicht
        }

        if (!$exists) {
            $pdo->exec("ALTER TABLE users ADD COLUMN vault_taler INT DEFAULT 0");
            $message = "<div class='alert alert-success'>Datenbank erfolgreich aktualisiert! (Spalte 'vault_taler' hinzugefügt)</div>";
        }
        else {
            $message = "<div class='alert alert-info'>Datenbank ist bereits aktuell. Nichts zu tun.</div>";
        }

    }
    catch (PDOException $e) {
        $message = "<div class='alert alert-error'>Fehler: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="content-box">
    <h2>Datenbank Reparatur</h2>
    <p>Klicke auf den Button, um die fehlenden Datenbank-Spalten für den Tresor automatisch anzulegen.</p>
    
    <?php echo $message; ?>
    
    <form method="post">
        <button type="submit" name="run_patch" style="background: green;">Update ausführen</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
