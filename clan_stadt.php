<?php
// clan_stadt.php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';

// Check Clan Membership
$stmt = $pdo->prepare("SELECT c.*, cm.role FROM clans c JOIN clan_members cm ON c.id = cm.clan_id WHERE cm.user_id = ?");
$stmt->execute([$userId]);
$myClan = $stmt->fetch();

if (!$myClan) {
    echo "<div class='alert alert-error'>Du bist in keinem Clan! <a href='clan.php'>Hier beitreten</a></div>";
    include 'includes/footer.php';
    exit;
}

// Helper für Level display
function getLvl($b, $type)
{
    return $b[$type] ?? 0;
}

// Buildings laden (Moved up for Logic checks)
$buildings = [];
$stmt = $pdo->prepare("SELECT type, level FROM clan_buildings WHERE clan_id = ?");
$stmt->execute([$myClan['id']]);
while ($row = $stmt->fetch()) {
    $buildings[$row['type']] = $row['level'];
}

// LOGIK: SPENDEN (Items)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donate_item'])) {
    $item = $_POST['item_name'];
    $amount = (int)$_POST['amount'];

    // Storage Limit Check
    $storageLvl = getLvl($buildings, 'lager');
    $maxSlots = 20 + ($storageLvl * 10);

    // Check current slots
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clan_inventory WHERE clan_id = ?");
    $stmt->execute([$myClan['id']]);
    $usedSlots = $stmt->fetchColumn();

    // Check if item exists in clan inv
    $stmt = $pdo->prepare("SELECT id FROM items WHERE name = ?");
    $stmt->execute([$item]);
    $itemId = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id FROM clan_inventory WHERE clan_id = ? AND item_id = ?");
    $stmt->execute([$myClan['id'], $itemId]);
    $existsInClan = $stmt->fetchColumn();

    if (!$existsInClan && $usedSlots >= $maxSlots) {
        $message = "<div class='alert alert-error'>Clan-Lager ist voll! (Max $maxSlots Slots).</div>";
    }
    else {
        // Proceed with donation
        $stmt = $pdo->prepare("SELECT amount, id FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE user_id = ? AND i.name = ?");
        $stmt->execute([$userId, $item]);
        $userInv = $stmt->fetch();

        if ($userInv && $userInv['amount'] >= $amount && $amount > 0) {
            $pdo->beginTransaction();
            try {
                // Remove from User
                $pdo->prepare("UPDATE inventory SET amount = amount - ? WHERE id = ?")->execute([$amount, $userInv['id']]);

                // Add to Clan
                if ($existsInClan) {
                    $pdo->prepare("UPDATE clan_inventory SET amount = amount + ? WHERE clan_id = ? AND item_id = ?")->execute([$amount, $myClan['id'], $itemId]);
                }
                else {
                    $pdo->prepare("INSERT INTO clan_inventory (clan_id, item_id, amount) VALUES (?, ?, ?)")->execute([$myClan['id'], $itemId, $amount]);
                }

                $pdo->commit();
                $message = "<div class='alert alert-success'>$amount x $item gespendet!</div>";
            }
            catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert alert-error'>Fehler: " . $e->getMessage() . "</div>";
            }
        }
        else {
            $message = "<div class='alert alert-error'>Nicht genug Items im Inventar.</div>";
        }
    }
}

// LOGIK: SPENDEN (Taler)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donate_taler'])) {
    $amount = (int)$_POST['amount'];

    $stmt = $pdo->prepare("SELECT taler FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userTaler = $stmt->fetchColumn();

    if ($userTaler >= $amount && $amount > 0) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET taler = taler - ? WHERE id = ?")->execute([$amount, $userId]);
            $pdo->prepare("UPDATE clans SET taler = taler + ? WHERE id = ?")->execute([$amount, $myClan['id']]);
            $pdo->commit();
            $message = "<div class='alert alert-success'>$amount Taler in die Clan-Kasse eingezahlt!</div>";
            // Refresh
            $myClan['taler'] += $amount;
        }
        catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-error'>Fehler bei der Überweisung: " . $e->getMessage() . "</div>";
        }
    }
    else {
        $message = "<div class='alert alert-error'>Nicht genug Taler.</div>";
    }
}

// LOGIK: AUSZAHLEN (Taler)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_taler'])) {
    if ($myClan['role'] == 'leader' || $myClan['role'] == 'officer') {
        $amount = (int)$_POST['amount'];
        if ($myClan['taler'] >= $amount && $amount > 0) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE clans SET taler = taler - ? WHERE id = ?")->execute([$amount, $myClan['id']]);
                $pdo->prepare("UPDATE users SET taler = taler + ? WHERE id = ?")->execute([$amount, $userId]);
                $pdo->commit();
                $message = "<div class='alert alert-success'>$amount Taler ausgezahlt!</div>";
                $myClan['taler'] -= $amount;
            }
            catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert alert-error'>Fehler: " . $e->getMessage() . "</div>";
            }
        }
        else {
            $message = "<div class='alert alert-error'>Nicht genug Taler in der Kasse.</div>";
        }
    }
    else {
        $message = "<div class='alert alert-error'>Keine Berechtigung.</div>";
    }
}

// LOGIK: AUSZAHLEN (Items)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_item'])) {
    if ($myClan['role'] == 'leader' || $myClan['role'] == 'officer') {
        $itemName = $_POST['item_name'];
        $amount = (int)$_POST['amount'];

        // Get Item ID and check Clan Logic
        $stmt = $pdo->prepare("SELECT i.id, ci.amount, ci.id as row_id FROM clan_inventory ci JOIN items i ON ci.item_id = i.id WHERE ci.clan_id = ? AND i.name = ?");
        $stmt->execute([$myClan['id'], $itemName]);
        $clanItem = $stmt->fetch();

        if ($clanItem && $clanItem['amount'] >= $amount && $amount > 0) {
            $pdo->beginTransaction();
            try {
                // Remove from Clan
                if ($clanItem['amount'] == $amount) {
                    $pdo->prepare("DELETE FROM clan_inventory WHERE id = ?")->execute([$clanItem['row_id']]);
                }
                else {
                    $pdo->prepare("UPDATE clan_inventory SET amount = amount - ? WHERE id = ?")->execute([$amount, $clanItem['row_id']]);
                }

                // Add to User
                $stmt = $pdo->prepare("SELECT id FROM inventory WHERE user_id = ? AND item_id = ?");
                $stmt->execute([$userId, $clanItem['id']]);
                if ($stmt->fetch()) {
                    $pdo->prepare("UPDATE inventory SET amount = amount + ? WHERE user_id = ? AND item_id = ?")->execute([$amount, $userId, $clanItem['id']]);
                }
                else {
                    $pdo->prepare("INSERT INTO inventory (user_id, item_id, amount) VALUES (?, ?, ?)")->execute([$userId, $clanItem['id'], $amount]);
                }

                $pdo->commit();
                $message = "<div class='alert alert-success'>$amount x $itemName entnommen!</div>";
            }
            catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert alert-error'>Fehler: " . $e->getMessage() . "</div>";
            }
        }
        else {
            $message = "<div class='alert alert-error'>Item nicht (genug) vorhanden.</div>";
        }
    }
    else {
        $message = "<div class='alert alert-error'>Keine Berechtigung.</div>";
    }
}

// LOGIK: GEBÄUDE AUSBAU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upgrade_building'])) {
    if ($myClan['role'] !== 'leader' && $myClan['role'] !== 'officer') {
        $message = "<div class='alert alert-error'>Nur Leader & Offiziere können bauen.</div>";
    }
    else {
        $type = $_POST['building_type'];
        $baseCost = 500; // Basis Kosten

        // Aktuelles Level holen
        $currentLevel = getLvl($buildings, $type); // Use helper

        $cost = $baseCost * ($currentLevel + 1);

        if ($myClan['taler'] >= $cost) {
            $pdo->beginTransaction();
            try {
                // Geld abziehen
                $pdo->prepare("UPDATE clans SET taler = taler - ? WHERE id = ?")->execute([$cost, $myClan['id']]);

                // Gebäude updaten/inserten
                if ($currentLevel == 0) {
                    $pdo->prepare("INSERT INTO clan_buildings (clan_id, type, level) VALUES (?, ?, 1)")->execute([$myClan['id'], $type]);
                }
                else {
                    $pdo->prepare("UPDATE clan_buildings SET level = level + 1 WHERE clan_id = ? AND type = ?")->execute([$myClan['id'], $type]);
                }

                $pdo->commit();
                $message = "<div class='alert alert-success'>$type erfolgreich auf Level " . ($currentLevel + 1) . " ausgebaut!</div>";
                $myClan['taler'] -= $cost; // Refresh Display
                if (isset($buildings[$type]))
                    $buildings[$type]++;
                else
                    $buildings[$type] = 1; // Update array for display
            }
            catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert alert-error'>Fehler: " . $e->getMessage() . "</div>";
            }
        }
        else {
            $message = "<div class='alert alert-error'>Nicht genug Taler in der Clankasse (Benötigt: $cost).</div>";
        }
    }
}

// Clan Inv laden
$stmt = $pdo->prepare("SELECT i.name, ci.amount FROM clan_inventory ci JOIN items i ON ci.item_id = i.id WHERE ci.clan_id = ? AND ci.amount > 0");
$stmt->execute([$myClan['id']]);
$clanInv = $stmt->fetchAll();

// User Inv Items laden (für Dropdown)
$stmt = $pdo->prepare("SELECT i.name, inv.amount FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.user_id = ? AND inv.amount > 0 ORDER BY i.name");
$stmt->execute([$userId]);
$myItems = $stmt->fetchAll();

?>

<div class="content-box">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>🏙️ Clan Stadt: <?php echo htmlspecialchars($myClan['name']); ?></h2>
        <a href="clan.php"><button>Zurück zum HQ</button></a>
    </div>

    <?php echo $message; ?>

    <div class="row" style="display: flex; gap: 2rem; flex-wrap: wrap;">
        
        <!-- CLAN KASSE & LAGER -->
        <div style="flex: 1; min-width: 300px; border: 1px solid #777; padding: 1rem; background: #222;">
            <h3 style="color: gold;">💰 Clan Kasse: <?php echo $myClan['taler']; ?> Taler</h3>
            
            <!-- EINZAHLEN -->
            <form method="post" style="margin-bottom: 0.5rem; display:flex; gap:5px;">
                <input type="number" name="amount" placeholder="Menge" style="width: 100px;">
                <button type="submit" name="donate_taler">Einzahlen</button>
            </form>

            <!-- AUSZAHLEN (Leader/Officer) -->
            <?php if ($myClan['role'] == 'leader' || $myClan['role'] == 'officer'): ?>
                <form method="post" style="margin-bottom: 1rem; display:flex; gap:5px;">
                    <input type="number" name="amount" placeholder="Menge" style="width: 100px;">
                    <button type="submit" name="withdraw_taler" style="background: #555;">Auszahlen</button>
                </form>
            <?php
endif; ?>

            <hr style="border-color: #444;">

            <h3>📦 Clan Lager</h3>
            
            <!-- STORAGE INFO -->
            <?php
$storageLvl = getLvl($buildings, 'lager');
$maxSlots = 20 + ($storageLvl * 10);
$usedSlots = count($clanInv); // Count unique items (stacks)
$storageColor = ($usedSlots >= $maxSlots) ? 'red' : '#aaa';
?>
            <p style="font-size: 0.8rem; color: <?php echo $storageColor; ?>;">
                Belegte Slots: <?php echo $usedSlots; ?> / <?php echo $maxSlots; ?> (Lager Lvl <?php echo $storageLvl; ?>)
            </p>


            <?php if (empty($clanInv)): ?>
                <p style="color: #777;">Lager ist leer.</p>
            <?php
else: ?>
                <ul style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($clanInv as $it): ?>
                        <li style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                            <span><?php echo $it['amount'] . "x " . htmlspecialchars($it['name']); ?></span>
                            
                            <!-- WITHDRAW ITEM (Leader/Officer) -->
                            <?php if ($myClan['role'] == 'leader' || $myClan['role'] == 'officer'): ?>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($it['name']); ?>">
                                <input type="number" name="amount" min="1" max="<?php echo $it['amount']; ?>" value="1" style="width: 50px; font-size:0.7rem;">
                                <button type="submit" name="withdraw_item" style="font-size:0.7rem; padding: 2px 5px; background: #555;">Hol</button>
                            </form>
                            <?php
        endif; ?>
                        </li>
                    <?php
    endforeach; ?>
                </ul>
            <?php
endif; ?>

            <hr style="border-color: #444;">
            <h4>Item spenden</h4>
            <?php if ($usedSlots < $maxSlots): ?>
            <form method="post">
                <select name="item_name">
                    <?php foreach ($myItems as $mi): ?>
                        <option value="<?php echo htmlspecialchars($mi['name']); ?>">
                            <?php echo htmlspecialchars($mi['name']) . " (" . $mi['amount'] . ")"; ?>
                        </option>
                    <?php
    endforeach; ?>
                </select>
                <input type="number" name="amount" value="1" min="1" style="width: 60px;">
                <button type="submit" name="donate_item">Einlagern</button>
            </form>
            <?php
else: ?>
                <p style="color:red; font-size:0.9rem;">Lager voll! Baut das Lager aus.</p>
            <?php
endif; ?>
        </div>

        <!-- GEBÄUDE -->
        <div style="flex: 1; min-width: 300px; border: 1px solid #555; padding: 1rem;">
            <h3>🏗️ Gebäude Übersicht</h3>
            <p><em>Hier entsteht eure mächtige Festung.</em></p>
            
            <!-- HQ -->
            <?php
$hqLvl = getLvl($buildings, 'hauptquartier');
$hqCost = 500 * ($hqLvl + 1);
?>
            <div class="building-card" style="border: 1px dashed #444; padding: 10px; margin-bottom: 10px;">
                <strong>Hauptquartier</strong> (Level <?php echo $hqLvl; ?>)<br>
                <span style="font-size: 0.8rem; color: #aaa;">Ermöglicht <?php echo 5 + ($hqLvl * 2); ?> Mitglieder.</span>
                <form method="post" style="margin-top: 5px;">
                    <input type="hidden" name="building_type" value="hauptquartier">
                    <button type="submit" name="upgrade_building" style="font-size: 0.7rem; background: #333;">Ausbauen (<?php echo $hqCost; ?> Taler)</button>
                </form>
            </div>
            
            <!-- MAUER -->
            <?php
$wallLvl = getLvl($buildings, 'mauer');
$wallCost = 500 * ($wallLvl + 1);
?>
            <div class="building-card" style="border: 1px dashed #444; padding: 10px; margin-bottom: 10px;">
                <strong>Clan-Mauer</strong> (Level <?php echo $wallLvl; ?>)<br>
                <span style="font-size: 0.8rem; color: #aaa;">Erhöht Verteidigung bei Angriffen. (Coming Soon)</span>
                <form method="post" style="margin-top: 5px;">
                    <input type="hidden" name="building_type" value="mauer">
                    <button type="submit" name="upgrade_building" style="font-size: 0.7rem; background: #333;">Ausbauen (<?php echo $wallCost; ?> Taler)</button>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
