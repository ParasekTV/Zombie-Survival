<?php
// admin.php
require_once 'config.php';
include 'includes/header.php';

// Access Control: Nur ID 1 (Admin)
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    echo "<div class='content-box'><h2>Zugriff verweigert</h2><p>Du hast keine Berechtigung für diesen Bereich.</p></div>";
    include 'includes/footer.php';
    exit;
}

$message = '';

// UPDATE USER STATS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $uid = (int)$_POST['user_id'];
    $taler = (int)$_POST['taler'];
    $health = (int)$_POST['health'];
    $energy = (int)$_POST['energy'];
    $hunger = (int)$_POST['hunger'];
    $wall = (int)$_POST['wall'];
    $storage = (int)$_POST['storage'];
    $vault = (int)$_POST['vault'];

    $stmt = $pdo->prepare("UPDATE users SET taler=?, health=?, energy=?, hunger=?, base_level_wall=?, base_level_storage=?, base_level_vault=? WHERE id=?");
    if ($stmt->execute([$taler, $health, $energy, $hunger, $wall, $storage, $vault, $uid])) {
        $message = "<div class='alert alert-success'>User ID $uid erfolgreich aktualisiert.</div>";
    }
    else {
        $message = "<div class='alert alert-error'>Fehler beim Speichern.</div>";
    }
}

// DELETE ITEM (Optional helper)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['give_item'])) {
// ... logic could follow
}

// UPDATE CLAN (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_clan'])) {
    $cid = (int)$_POST['clan_id'];
    $ctaler = (int)$_POST['clan_taler'];

    $stmt = $pdo->prepare("UPDATE clans SET taler=? WHERE id=?");
    if ($stmt->execute([$ctaler, $cid])) {
        $message = "<div class='alert alert-success'>Clan ID $cid aktualisiert.</div>";
    }
    else {
        $message = "<div class='alert alert-error'>Fehler beim Speichern.</div>";
    }
}

// DELETE CLAN (Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_clan'])) {
    $cid = (int)$_POST['clan_id'];
    // Cascade delete handles members/buildings/etc if set up correctly in DB
    $stmt = $pdo->prepare("DELETE FROM clans WHERE id=?");
    if ($stmt->execute([$cid])) {
        $message = "<div class='alert alert-success'>Clan ID $cid gelöscht.</div>";
    }
    else {
        $message = "<div class='alert alert-error'>Fehler beim Löschen.</div>";
    }
}

// User Liste laden
$stmt = $pdo->query("SELECT * FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();

// Clan Liste laden
$stmt = $pdo->query("SELECT * FROM clans ORDER BY id ASC");
$clans = $stmt->fetchAll();
?>

<div class="content-box">
    <h2>👮‍♂️ Admin Konsole</h2>
    <p>Willkommen, Administrator. Hier hast du die volle Kontrolle.</p>
    
    <?php echo $message; ?>

    <!-- USERS -->
    <h3>User Verwaltung</h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.9rem;">
            <thead>
                <tr style="background: #333; text-align: left;">
                    <th style="padding: 5px;">ID/Name</th>
                    <th style="padding: 5px;">Taler</th>
                    <th style="padding: 5px;">HP/Eng/Hung</th>
                    <th style="padding: 5px;">Base Levels (W/S/V)</th>
                    <th style="padding: 5px;">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr style="border-bottom: 1px solid #444; background: #222;">
                        <form method="post">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            
                            <td style="padding: 5px;">
                                #<?php echo $u['id']; ?> <br>
                                <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                            </td>
                            
                            <td style="padding: 5px;">
                                <input type="number" name="taler" value="<?php echo $u['taler']; ?>" style="width: 60px;">
                            </td>
                            
                            <td style="padding: 5px;">
                                <input type="number" name="health" value="<?php echo $u['health']; ?>" title="Health" style="width: 45px;">
                                <input type="number" name="energy" value="<?php echo $u['energy']; ?>" title="Energy" style="width: 45px;">
                                <input type="number" name="hunger" value="<?php echo $u['hunger']; ?>" title="Hunger" style="width: 45px;">
                            </td>
                            
                            <td style="padding: 5px;">
                                <input type="number" name="wall" value="<?php echo $u['base_level_wall']; ?>" title="Wall" style="width: 40px;">
                                <input type="number" name="storage" value="<?php echo $u['base_level_storage']; ?>" title="Storage" style="width: 40px;">
                                <input type="number" name="vault" value="<?php echo $u['base_level_vault']; ?>" title="Vault" style="width: 40px;">
                            </td>
                            
                            <td style="padding: 5px;">
                                <button type="submit" name="update_user" style="background: #004400; padding: 5px;">Save</button>
                            </td>
                        </form>
                    </tr>
                <?php
endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- CLANS -->
    <h3 style="margin-top: 2rem;">Clan Verwaltung</h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.9rem;">
            <thead>
                <tr style="background: #333; text-align: left;">
                    <th style="padding: 5px;">ID/Tag</th>
                    <th style="padding: 5px;">Name</th>
                    <th style="padding: 5px;">Kasse (Taler)</th>
                    <th style="padding: 5px;">Erstellt</th>
                    <th style="padding: 5px;">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clans as $c): ?>
                    <tr style="border-bottom: 1px solid #444; background: #222;">
                        <form method="post">
                            <input type="hidden" name="clan_id" value="<?php echo $c['id']; ?>">
                            
                            <td style="padding: 5px;">
                                #<?php echo $c['id']; ?> <br>
                                [<?php echo htmlspecialchars($c['tag']); ?>]
                            </td>
                            
                            <td style="padding: 5px;">
                                <strong><?php echo htmlspecialchars($c['name']); ?></strong>
                            </td>
                            
                            <td style="padding: 5px;">
                                <input type="number" name="clan_taler" value="<?php echo $c['taler']; ?>" style="width: 80px;">
                            </td>
                            
                            <td style="padding: 5px; color: #888;">
                                <?php echo $c['created_at']; ?>
                            </td>
                            
                            <td style="padding: 5px;">
                                <button type="submit" name="update_clan" style="background: #004400; padding: 5px;">Save</button>
                                <button type="submit" name="delete_clan" style="background: darkred; padding: 5px;" onclick="return confirm('Clan WIRKLICH löschen? Alle Daten gehen verloren!');">Delete</button>
                            </td>
                        </form>
                    </tr>
                <?php
endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
