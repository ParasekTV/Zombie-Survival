<?php
// base.php
require_once 'config.php';
include 'includes/header.php';

// Access Control
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';

// Hilfsfunktion: Inventar prüfen
function hasItem($pdo, $userId, $itemName, $amountNeeded)
{
    $sql = "SELECT amount FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.user_id = ? AND i.name = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $itemName]);
    $result = $stmt->fetch();
    return ($result && $result['amount'] >= $amountNeeded);
}

// Hilfsfunktion: Items entfernen
function removeItems($pdo, $userId, $itemName, $amount)
{
    // ID holen
    $stmt = $pdo->prepare("SELECT id FROM items WHERE name = ?");
    $stmt->execute([$itemName]);
    $itemId = $stmt->fetchColumn();

    $upd = $pdo->prepare("UPDATE inventory SET amount = amount - ? WHERE user_id = ? AND item_id = ?");
    $upd->execute([$amount, $userId, $itemId]);
}

// FINANZEN & LAGER (Tresor & Vorratslager)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Stats neu laden
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Default values if column missing (safety)
    $user['base_level_vault'] = $user['base_level_vault'] ?? 0;
    $user['base_level_storage'] = $user['base_level_storage'] ?? 0;
    $user['vault_taler'] = $user['vault_taler'] ?? 0;

    $maxGold = $user['base_level_vault'] * 500;
    $maxItems = $user['base_level_storage'] * 100;

    $pdo->beginTransaction();
    try {
        // TRESOR
        if ($action === 'deposit_taler' || $action === 'withdraw_taler') {
            $amount = (int)$_POST['amount'];
            if ($amount <= 0)
                throw new Exception("Ungültiger Betrag.");

            if ($action === 'deposit_taler') {
                if ($user['base_level_vault'] < 1)
                    throw new Exception("Kein Tresor vorhanden.");
                if ($user['taler'] < $amount)
                    throw new Exception("Zu wenig Taler.");
                if (($user['vault_taler'] + $amount) > $maxGold)
                    throw new Exception("Tresor voll!");

                $pdo->prepare("UPDATE users SET taler = taler - ?, vault_taler = vault_taler + ? WHERE id = ?")->execute([$amount, $amount, $_SESSION['user_id']]);
                $message = "<div class='alert alert-success'>$amount Taler eingelagert.</div>";
            }
            elseif ($action === 'withdraw_taler') {
                if ($user['vault_taler'] < $amount)
                    throw new Exception("Zu wenig Taler im Tresor.");
                $pdo->prepare("UPDATE users SET taler = taler + ?, vault_taler = vault_taler - ? WHERE id = ?")->execute([$amount, $amount, $_SESSION['user_id']]);
                $message = "<div class='alert alert-success'>$amount Taler entnommen.</div>";
            }
        }

        // VORRATSLAGER (Items)
        elseif ($action === 'deposit_item' || $action === 'withdraw_item') {
            $itemName = $_POST['item_name'];
            $amount = (int)$_POST['amount'];
            if ($amount <= 0)
                throw new Exception("Ungültige Menge.");
            if ($user['base_level_storage'] < 1)
                throw new Exception("Kein Lager vorhanden.");

            // Item ID holen
            $stmt = $pdo->prepare("SELECT id FROM items WHERE name = ?");
            $stmt->execute([$itemName]);
            $itemId = $stmt->fetchColumn();
            if (!$itemId)
                throw new Exception("Item nicht gefunden.");

            // Aktuelle Lagermenge prüfen (Total slots used)
            $stmt = $pdo->prepare("SELECT SUM(amount) FROM base_inventory WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $currentStorageLoad = (int)$stmt->fetchColumn();

            if ($action === 'deposit_item') {
                // Check Inventory
                if (!hasItem($pdo, $_SESSION['user_id'], $itemName, $amount))
                    throw new Exception("Nicht genug Items im Inventar.");
                // Check Storage Space
                if (($currentStorageLoad + $amount) > $maxItems)
                    throw new Exception("Lager voll! (Max: $maxItems)");

                // Remove from Inv
                removeItems($pdo, $_SESSION['user_id'], $itemName, $amount);

                // Add to Base
                $check = $pdo->prepare("SELECT id FROM base_inventory WHERE user_id = ? AND item_id = ?");
                $check->execute([$_SESSION['user_id'], $itemId]);
                if ($check->fetch()) {
                    $pdo->prepare("UPDATE base_inventory SET amount = amount + ? WHERE user_id = ? AND item_id = ?")->execute([$amount, $_SESSION['user_id'], $itemId]);
                }
                else {
                    $pdo->prepare("INSERT INTO base_inventory (user_id, item_id, amount) VALUES (?, ?, ?)")->execute([$_SESSION['user_id'], $itemId, $amount]);
                }
                $message = "<div class='alert alert-success'>$amount x $itemName eingelagert.</div>";
            }
            elseif ($action === 'withdraw_item') {
                // Check Base Inv
                $stmt = $pdo->prepare("SELECT amount FROM base_inventory WHERE user_id = ? AND item_id = ?");
                $stmt->execute([$_SESSION['user_id'], $itemId]);
                $inBase = $stmt->fetchColumn();

                if (!$inBase || $inBase < $amount)
                    throw new Exception("Nicht genug Items im Lager.");

                // Remove from Base
                $pdo->prepare("UPDATE base_inventory SET amount = amount - ? WHERE user_id = ? AND item_id = ?")->execute([$amount, $_SESSION['user_id'], $itemId]);
                // Cleanup 0
                $pdo->prepare("DELETE FROM base_inventory WHERE amount <= 0")->execute();

                // Add to Inv
                $check = $pdo->prepare("SELECT id FROM inventory WHERE user_id = ? AND item_id = ?");
                $check->execute([$_SESSION['user_id'], $itemId]);
                if ($check->fetch()) {
                    $pdo->prepare("UPDATE inventory SET amount = amount + ? WHERE user_id = ? AND item_id = ?")->execute([$amount, $_SESSION['user_id'], $itemId]);
                }
                else {
                    $pdo->prepare("INSERT INTO inventory (user_id, item_id, amount) VALUES (?, ?, ?)")->execute([$_SESSION['user_id'], $itemId, $amount]);
                }
                $message = "<div class='alert alert-success'>$amount x $itemName entnommen.</div>";
            }
        }

        $pdo->commit();
    }
    catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-error'>" . $e->getMessage() . "</div>";
    }
}

// UPGRADE BUILDINGS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade'])) {
    $upgrade = $_POST['upgrade'];

    try {
        $pdo->beginTransaction();

        if ($upgrade === 'wall') {
            if (hasItem($pdo, $_SESSION['user_id'], 'Holz', 10) && hasItem($pdo, $_SESSION['user_id'], 'Stein', 5)) {
                removeItems($pdo, $_SESSION['user_id'], 'Holz', 10);
                removeItems($pdo, $_SESSION['user_id'], 'Stein', 5);
                $pdo->prepare("UPDATE users SET base_level_wall = base_level_wall + 1 WHERE id = ?")->execute([$_SESSION['user_id']]);
                $message = "<div class='alert alert-success'>Mauer verstärkt!</div>";
            }
            else
                $message = "<div class='alert alert-error'>Fehlende Ressourcen.</div>";
        }
        elseif ($upgrade === 'storage') {
            if (hasItem($pdo, $_SESSION['user_id'], 'Holz', 20) && hasItem($pdo, $_SESSION['user_id'], 'Eisen', 2)) {
                removeItems($pdo, $_SESSION['user_id'], 'Holz', 20);
                removeItems($pdo, $_SESSION['user_id'], 'Eisen', 2);
                $pdo->prepare("UPDATE users SET base_level_storage = base_level_storage + 1 WHERE id = ?")->execute([$_SESSION['user_id']]);
                $message = "<div class='alert alert-success'>Lager erweitert!</div>";
            }
            else
                $message = "<div class='alert alert-error'>Fehlende Ressourcen.</div>";
        }
        elseif ($upgrade === 'vault') {
            if (hasItem($pdo, $_SESSION['user_id'], 'Stein', 50) && hasItem($pdo, $_SESSION['user_id'], 'Eisen', 10) && hasItem($pdo, $_SESSION['user_id'], 'Beton', 2)) {
                removeItems($pdo, $_SESSION['user_id'], 'Stein', 50);
                removeItems($pdo, $_SESSION['user_id'], 'Eisen', 10);
                removeItems($pdo, $_SESSION['user_id'], 'Beton', 2);
                $pdo->prepare("UPDATE users SET base_level_vault = base_level_vault + 1 WHERE id = ?")->execute([$_SESSION['user_id']]);
                $message = "<div class='alert alert-success'>Tresor ausgebaut!</div>";
            }
            else
                $message = "<div class='alert alert-error'>Fehlende Ressourcen.</div>";
        }
        elseif ($upgrade === 'traps') {
            // Kosten: 5 Holz, 5 Eisen
            if (hasItem($pdo, $_SESSION['user_id'], 'Holz', 5) && hasItem($pdo, $_SESSION['user_id'], 'Eisen', 5)) {
                removeItems($pdo, $_SESSION['user_id'], 'Holz', 5);
                removeItems($pdo, $_SESSION['user_id'], 'Eisen', 5);
                $pdo->prepare("UPDATE users SET base_level_traps = base_level_traps + 1 WHERE id = ?")->execute([$_SESSION['user_id']]);
                $message = "<div class='alert alert-success'>Fallen installiert!</div>";
            }
            else
                $message = "<div class='alert alert-error'>Fehlende Ressourcen (5 Holz, 5 Eisen).</div>";
        }
        elseif ($upgrade === 'turrets') {
            // Kosten: 10 Eisen, 5 Beton
            if (hasItem($pdo, $_SESSION['user_id'], 'Eisen', 10) && hasItem($pdo, $_SESSION['user_id'], 'Beton', 5)) {
                removeItems($pdo, $_SESSION['user_id'], 'Eisen', 10);
                removeItems($pdo, $_SESSION['user_id'], 'Beton', 5);
                $pdo->prepare("UPDATE users SET base_level_turrets = base_level_turrets + 1 WHERE id = ?")->execute([$_SESSION['user_id']]);
                $message = "<div class='alert alert-success'>Geschütz errichtet!</div>";
            }
            else
                $message = "<div class='alert alert-error'>Fehlende Ressourcen (10 Eisen, 5 Beton).</div>";
        }

        $pdo->commit();
    }
    catch (Exception $e) {
        $pdo->rollBack();
        $message = "<div class='alert alert-error'>Fehler: " . $e->getMessage() . "</div>";
    }
}

// DATEN LADEN
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$user['vault_taler'] = $user['vault_taler'] ?? 0;
$user['base_level_traps'] = $user['base_level_traps'] ?? 0;
$user['base_level_turrets'] = $user['base_level_turrets'] ?? 0;

// Lager Inhalt laden
$storageItems = [];
if ($user['base_level_storage'] > 0) {
    try {
        $stmt = $pdo->prepare("SELECT i.name, b.amount, i.type FROM base_inventory b JOIN items i ON b.item_id = i.id WHERE b.user_id = ? AND b.amount > 0");
        $stmt->execute([$_SESSION['user_id']]);
        $storageItems = $stmt->fetchAll();
    }
    catch (PDOException $e) {
        $storageItems = []; // Tabelle fehlt noch?
    }
}

// User Inventar laden (für Dropdown)
$invItems = [];
$stmt = $pdo->prepare("SELECT i.name, inv.amount FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.user_id = ? AND inv.amount > 0");
$stmt->execute([$_SESSION['user_id']]);
$invItems = $stmt->fetchAll();

?>

<div class="content-box">
    <h2>Deine Basis</h2>
    <p>Baue deine Zuflucht aus, um dich vor Angriffen zu schützen und mehr Vorräte zu lagern.</p>
    <?php echo $message; ?>

    <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start;">
        
        <!-- GEBÄUDE -->
        <div class="base-upgrades" style="flex: 2; display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem;">
            
            <!-- MAUER -->
            <div class="upgrade-card" style="border: 1px solid #777; padding: 1rem; background: #222;">
                <img src="<?php echo getIconPath('wall', 'buildings'); ?>" style="float:right; width:48px;">
                <h3>Mauer (Lvl <?php echo $user['base_level_wall']; ?>)</h3>
                <p>Schutz vor Zombies.</p>
                <small>Kosten: 10 Holz, 5 Stein</small>
                <form method="post" style="margin-top:10px;"><input type="hidden" name="upgrade" value="wall"><button>Ausbauen</button></form>
            </div>

            <!-- LAGER -->
            <div class="upgrade-card" style="border: 1px solid #777; padding: 1rem; background: #222;">
                <img src="<?php echo getIconPath('storage', 'buildings'); ?>" style="float:right; width:48px;">
                <h3>Lager (Lvl <?php echo $user['base_level_storage']; ?>)</h3>
                <p>Platz: <?php echo $user['base_level_storage'] * 100; ?> Items</p>
                <small>Kosten: 20 Holz, 2 Eisen</small>
                <form method="post" style="margin-top:10px;"><input type="hidden" name="upgrade" value="storage"><button>Ausbauen</button></form>
            </div>

            <!-- TRESOR -->
            <div class="upgrade-card" style="border: 1px solid gold; padding: 1rem; background: #222;">
                <img src="<?php echo getIconPath('vault', 'buildings'); ?>" style="float:right; width:48px;">
                <h3>Tresor (Lvl <?php echo $user['base_level_vault']; ?>)</h3>
                <p>Sichert Taler.</p>
                <small>Kosten: 50 Stein, 10 Eisen</small>
                <form method="post" style="margin-top:10px;"><input type="hidden" name="upgrade" value="vault"><button>Ausbauen</button></form>
            </div>
            
            <!-- FALLEN -->
            <div class="upgrade-card" style="border: 1px solid #933; padding: 1rem; background: #291111;">
                <img src="<?php echo getIconPath('falle', 'buildings'); ?>" style="float:right; width:48px;">
                <h3>Fallen (Lvl <?php echo $user['base_level_traps']; ?>)</h3>
                <p>Verursachen Schaden bei Angriffen.</p>
                <small>Kosten: 5 Holz, 5 Eisen</small>
                <form method="post" style="margin-top:10px;"><input type="hidden" name="upgrade" value="traps"><button>Bauen</button></form>
            </div>

            <!-- GESCHÜTZE -->
            <div class="upgrade-card" style="border: 1px solid #933; padding: 1rem; background: #291111;">
                <img src="<?php echo getIconPath('geschuetz', 'buildings'); ?>" style="float:right; width:48px;">
                <h3>Geschütz (Lvl <?php echo $user['base_level_turrets']; ?>)</h3>
                <p>Aktive Verteidigung.</p>
                <small>Kosten: 10 Eisen, 5 Beton</small>
                <form method="post" style="margin-top:10px;"><input type="hidden" name="upgrade" value="turrets"><button>Bauen</button></form>
            </div>
        </div>

        <!-- MANAGEMENT (Lager & Tresor Inhalt) -->
        <div style="flex: 1; min-width: 300px; display: flex; flex-direction: column; gap: 20px;">
            
            <!-- TRESOR UI -->
            <?php if ($user['base_level_vault'] > 0): ?>
            <div class="content-box" style="margin:0; border:1px solid gold;">
                <h3>Tresor Inhalt</h3>
                <p><?php echo $user['vault_taler']; ?> / <?php echo $user['base_level_vault'] * 500; ?> 💰</p>
                <form method="post" style="display:flex; gap:5px; margin-bottom:5px;">
                    <input type="hidden" name="action" value="deposit_taler">
                    <input type="number" name="amount" placeholder="Menge" style="width:70px" min="1">
                    <button>Einlagern</button>
                </form>
                <form method="post" style="display:flex; gap:5px;">
                    <input type="hidden" name="action" value="withdraw_taler">
                    <input type="number" name="amount" style="width:70px" min="1" placeholder="Menge">
                    <button style="background:#a33;">Abheben</button>
                </form>
            </div>
            <?php
endif; ?>

            <!-- LAGER UI -->
            <?php if ($user['base_level_storage'] > 0): ?>
            <div class="content-box" style="margin:0;">
                <h3>Vorratslager</h3>
                
                <!-- Einlagern -->
                <form method="post" style="margin-bottom:15px; padding-bottom:10px; border-bottom:1px solid #444;">
                    <input type="hidden" name="action" value="deposit_item">
                    <select name="item_name" style="width:100%; margin-bottom:5px; padding:5px; color:black;">
                        <?php foreach ($invItems as $itm): ?>
                            <option value="<?php echo $itm['name']; ?>"><?php echo $itm['name']; ?> (<?php echo $itm['amount']; ?>)</option>
                        <?php
    endforeach; ?>
                    </select>
                    <div style="display:flex; gap:5px;">
                        <input type="number" name="amount" value="1" min="1" style="width:60px">
                        <button style="flex:1;">Einlagern</button>
                    </div>
                </form>

                <!-- Auslagern Liste -->
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php if (empty($storageItems)): ?>
                        <p style="color:#777;">Lager ist leer.</p>
                    <?php
    else: ?>
                        <?php foreach ($storageItems as $sItem): ?>
                            <div style="background:#222; margin-bottom:5px; padding:5px; display:flex; justify-content:space-between; align-items:center;">
                                <div style="display:flex; align-items:center; gap:5px;">
                                    <img src="<?php echo getIconPath($sItem['name'], 'items'); ?>" width="24">
                                    <span><?php echo $sItem['amount']; ?>x <?php echo $sItem['name']; ?></span>
                                </div>
                                <form method="post" style="display:flex;">
                                    <input type="hidden" name="action" value="withdraw_item">
                                    <input type="hidden" name="item_name" value="<?php echo $sItem['name']; ?>">
                                    <input type="number" name="amount" value="1" min="1" max="<?php echo $sItem['amount']; ?>" style="width:40px; padding:2px;">
                                    <button style="padding:2px 5px;">&larr;</button>
                                </form>
                            </div>
                        <?php
        endforeach; ?>
                    <?php
    endif; ?>
                </div>
            </div>
            <?php
endif; ?>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
