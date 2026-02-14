<?php
// dashboard.php
require_once 'config.php';
include 'includes/header.php';

// Access Control
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';

// AKTIONEN (Equip / Unequip / Sleep / Loot)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -> SCHLAFEN & LOOTEN
    if ($action === 'sleep') {
        $pdo->prepare("UPDATE users SET energy = 100, health = LEAST(100, health + 10) WHERE id = ?")->execute([$_SESSION['user_id']]);
        $message = "<div class='alert alert-success'>Du hast geschlafen. Energie voll! (+10 HP)</div>";
    }
    elseif ($action === 'loot') {
        header("Location: looten.php");
        exit;
    }

    // -> AUSRÜSTUNG
    elseif ($action === 'equip' || $action === 'unequip') {
        $pdo->beginTransaction();
        try {
            if ($action === 'equip') {
                $invId = $_POST['inv_id'];
                $slot = $_POST['slot']; // 'weapon' oder 'armor'

                // Item prüfen
                $stmt = $pdo->prepare("SELECT i.id, i.name, i.type, inv.amount FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.id = ? AND inv.user_id = ?");
                $stmt->execute([$invId, $_SESSION['user_id']]);
                $item = $stmt->fetch();

                if (!$item)
                    throw new Exception("Item nicht gefunden.");
                if ($slot === 'weapon' && $item['type'] !== 'weapon')
                    throw new Exception("Das ist keine Waffe.");
                if ($slot === 'armor' && $item['type'] !== 'armor')
                    throw new Exception("Das ist keine Rüstung.");

                // Aktuelles Equipment prüfen (falls vorhanden -> zurück ins Inv)
                $col = ($slot === 'weapon') ? 'eq_weapon_id' : 'eq_armor_id';
                $stmt = $pdo->prepare("SELECT $col FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $currentEqId = $stmt->fetchColumn();

                if ($currentEqId) {
                    // Zurück ins Inv
                    $chk = $pdo->prepare("SELECT id FROM inventory WHERE user_id = ? AND item_id = ?");
                    $chk->execute([$_SESSION['user_id'], $currentEqId]);
                    if ($chk->fetch()) {
                        $pdo->prepare("UPDATE inventory SET amount = amount + 1 WHERE user_id = ? AND item_id = ?")->execute([$_SESSION['user_id'], $currentEqId]);
                    }
                    else {
                        $pdo->prepare("INSERT INTO inventory (user_id, item_id, amount) VALUES (?, ?, 1)")->execute([$_SESSION['user_id'], $currentEqId]);
                    }
                }

                // Neues Item anlegen (aus Inv entfernen)
                if ($item['amount'] > 1) {
                    $pdo->prepare("UPDATE inventory SET amount = amount - 1 WHERE id = ?")->execute([$invId]);
                }
                else {
                    $pdo->prepare("DELETE FROM inventory WHERE id = ?")->execute([$invId]);
                }

                // Slot updaten
                $pdo->prepare("UPDATE users SET $col = ? WHERE id = ?")->execute([$item['id'], $_SESSION['user_id']]);

                $message = "<div class='alert alert-success'>{$item['name']} ausgerüstet!</div>";
            }
            elseif ($action === 'unequip') {
                $slot = $_POST['slot'];
                $col = ($slot === 'weapon') ? 'eq_weapon_id' : 'eq_armor_id';

                // Aktuelles Item holen
                $stmt = $pdo->prepare("SELECT $col FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $itemId = $stmt->fetchColumn();

                if (!$itemId)
                    throw new Exception("Nichts ausgerüstet.");

                // Ins Inv
                $chk = $pdo->prepare("SELECT id FROM inventory WHERE user_id = ? AND item_id = ?");
                $chk->execute([$_SESSION['user_id'], $itemId]);
                if ($chk->fetch()) {
                    $pdo->prepare("UPDATE inventory SET amount = amount + 1 WHERE user_id = ? AND item_id = ?")->execute([$_SESSION['user_id'], $itemId]);
                }
                else {
                    $pdo->prepare("INSERT INTO inventory (user_id, item_id, amount) VALUES (?, ?, 1)")->execute([$_SESSION['user_id'], $itemId]);
                }

                // Slot leeren
                $pdo->prepare("UPDATE users SET $col = NULL WHERE id = ?")->execute([$_SESSION['user_id']]);
                $message = "<div class='alert alert-success'>Ausgezogen!</div>";
            }
            $pdo->commit();
        }
        catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-error'>" . $e->getMessage() . "</div>";
        }
    }
}

// User-Daten laden (inkl. Equipment)
$stmt = $pdo->prepare("
    SELECT u.*, 
           w.name as weapon_name, w.effect_value as weapon_atk,
           a.name as armor_name, a.effect_value as armor_def
    FROM users u
    LEFT JOIN items w ON u.eq_weapon_id = w.id
    LEFT JOIN items a ON u.eq_armor_id = a.id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Kampfwerte berechnen
$totalAtk = ($user['attack'] ?? 10) + ($user['weapon_atk'] ?? 0);
$totalDef = ($user['defense'] ?? 0) + ($user['armor_def'] ?? 0);

// Sättigung
$satiety = 100 - $user['hunger'];
$hungerColor = ($satiety < 20) ? '#d9534f' : (($satiety < 50) ? '#f0ad4e' : '#5cb85c');

// Inventar für Dropdowns laden (Nur Waffen/Rüstung)
$stmt = $pdo->prepare("SELECT inv.id, i.name, i.type, i.effect_value 
                       FROM inventory inv 
                       JOIN items i ON inv.item_id = i.id 
                       WHERE inv.user_id = ? AND (i.type = 'weapon' OR i.type = 'armor') 
                       ORDER BY i.type, i.name");
$stmt->execute([$_SESSION['user_id']]);
$equipableItems = $stmt->fetchAll();

$weapons = array_filter($equipableItems, fn($i) => $i['type'] === 'weapon');
$armors = array_filter($equipableItems, fn($i) => $i['type'] === 'armor');

?>

<div class="content-box">
    <h2>Übersicht</h2>
    <p>Willkommen, <strong><?php echo htmlspecialchars($user['username']); ?></strong>!</p>
    
    <?php echo $message; ?>

    <!-- 1. RESOURCE STATS (TOP RANK) -->
    <div class="stats-container" style="display: flex; justify-content: space-around; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;">
        <!-- Health -->
        <div class="stat-box" style="border-color: #d9534f; flex: 1; min-width: 120px;">
            <img src="<?php echo getIconPath('health', 'stats'); ?>" alt="Health" style="width: 32px; height: 32px;">
            <div class="stat-value"><?php echo $user['health']; ?>%</div>
            <div class="stat-label">Gesundheit</div>
        </div>
        <!-- Satiety -->
        <div class="stat-box" style="border-color: <?php echo $hungerColor; ?>; flex: 1; min-width: 120px;">
            <img src="<?php echo getIconPath('satiety', 'stats'); ?>" alt="Satiety" style="width: 32px; height: 32px;">
            <div class="stat-value"><?php echo $satiety; ?>%</div>
            <div class="stat-label">Sättigung</div>
        </div>
        <!-- Energy -->
        <div class="stat-box" style="border-color: #f0ad4e; flex: 1; min-width: 120px;">
            <img src="<?php echo getIconPath('energy', 'stats'); ?>" alt="Energy" style="width: 32px; height: 32px;">
            <div class="stat-value"><?php echo $user['energy']; ?>%</div>
            <div class="stat-label">Energie</div>
        </div>
        <!-- Taler -->
        <div class="stat-box" style="border-color: gold; flex: 1; min-width: 120px;">
            <img src="<?php echo getIconPath('taler', 'stats'); ?>" alt="Taler" style="width: 32px; height: 32px;">
            <div class="stat-value"><?php echo $user['taler']; ?></div>
            <div class="stat-label">Taler</div>
        </div>
    </div>

    <!-- 2. CHARACTER DISPLAY (FULL WIDTH MIDDLE) -->
    <div style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 1px solid #444; margin-bottom: 20px; overflow: hidden;">
        <h3 style="text-align: center; margin-top: 0; color: #fff;">Dein Charakter</h3>
        
        <!-- LARGE STICK FIGURE CONTAINER -->
        <!-- Utilizing full width. Base width 500px, scaled up on larger screens if needed, but flex centering is better. -->
        <div style="position: relative; width: 100%; max-width: 600px; height: 400px; margin: 0 auto;">
            
            <!-- STICK FIGURE GROUP (Centered in the 600px container) -->
            <!-- We treat 300px as center x -->
            
            <!-- SCALE WRAPPER to adjust size easily -->
            <div style="transform: scale(1.3); transform-origin: center center; width: 100%; height: 100%; position: relative;">
                
                <!-- KOPF (Center ~ 300) -->
                <div style="position: absolute; top: 30px; left: 275px; width: 50px; height: 50px; border: 4px solid #ccc; border-radius: 50%; box-shadow: 0 0 15px rgba(255,255,255,0.2); background: #222;"></div>
                
                <!-- KÖRPER -->
                <div style="position: absolute; top: 80px; left: 300px; height: 120px; border-left: 4px solid #ccc;"></div>
                
                <!-- ARME -->
                <div style="position: absolute; top: 110px; left: 240px; width: 120px; border-top: 4px solid #ccc;"></div>
                
                <!-- BEINE -->
                <div style="position: absolute; top: 200px; left: 300px; width: 0; height: 100px; transform: rotate(20deg); border-left: 4px solid #ccc; transform-origin: top;"></div>
                <div style="position: absolute; top: 200px; left: 300px; width: 0; height: 100px; transform: rotate(-20deg); border-left: 4px solid #ccc; transform-origin: top;"></div>

                <!-- WAFFEN SLOT (Rechts außen) -->
                <div style="position: absolute; top: 70px; left: 380px; width: 100px; height: 100px; border: 2px dashed #666; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; text-align: center; font-size: 0.7rem; border-radius: 8px; z-index: 2;">
                    <?php if ($user['weapon_name']): ?>
                        <div style="width: 100%;">
                            <img src="<?php echo getIconPath($user['weapon_name'], 'items'); ?>" width="48" style="display:block; margin:0 auto;"><br>
                            <strong><?php echo $user['weapon_name']; ?></strong><br>
                            <span style="color:#f77;">+<?php echo $user['weapon_atk']; ?> Atk</span>
                            <form method="post" style="position: absolute; top: -8px; right: -8px;">
                                <input type="hidden" name="action" value="unequip">
                                <input type="hidden" name="slot" value="weapon">
                                <button style="padding:0px 8px; font-size:0.8rem; border-radius: 50%; background: #d9534f; border: none; color: white; cursor: pointer; font-weight: bold;">X</button>
                            </form>
                        </div>
                    <?php
else: ?>
                        <span style="color:#555; font-size: 0.9rem;">Waffe</span>
                    <?php
endif; ?>
                </div>

                <!-- RÜSTUNG SLOT (Links außen) -->
                <div style="position: absolute; top: 100px; left: 120px; width: 100px; height: 100px; border: 2px dashed #666; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; text-align: center; font-size: 0.7rem; border-radius: 8px; z-index: 2;">
                    <?php if ($user['armor_name']): ?>
                         <div style="width: 100%;">
                            <img src="<?php echo getIconPath($user['armor_name'], 'items'); ?>" width="48" style="display:block; margin:0 auto;"><br>
                            <strong><?php echo $user['armor_name']; ?></strong><br>
                            <span style="color:#77f;">+<?php echo $user['armor_def']; ?> Def</span>
                             <form method="post" style="position: absolute; top: -8px; right: -8px;">
                                <input type="hidden" name="action" value="unequip">
                                <input type="hidden" name="slot" value="armor">
                                <button style="padding:0px 8px; font-size:0.8rem; border-radius: 50%; background: #d9534f; border: none; color: white; cursor: pointer; font-weight: bold;">X</button>
                            </form>
                        </div>
                    <?php
else: ?>
                        <span style="color:#555; font-size: 0.9rem;">Rüstung</span>
                    <?php
endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. BOTTOM ROW: COMBAT STATS & ACTIONS -->
    <div style="display: flex; flex-wrap: wrap; gap: 20px;">
        
        <!-- COMBAT STATS -->
        <div class="content-box" style="flex: 1; min-width: 200px; background: #222; margin: 0;">
            <h3 style="margin-top: 0;">Kampfwerte</h3>
            <div style="display: flex; justify-content: space-around; text-align: center;">
                <div>
                    <div style="font-size: 2rem;">⚔️ <?php echo $totalAtk; ?></div>
                    <div style="color: #aaa;">Angriff</div>
                </div>
                <div>
                    <div style="font-size: 2rem;">🛡️ <?php echo $totalDef; ?></div>
                    <div style="color: #aaa;">Abwehr</div>
                </div>
                <div>
                    <div style="font-size: 2rem;">🧠 <?php echo $user['survival_skill'] ?? 0; ?></div>
                    <div style="color: #aaa;">Skill</div>
                </div>
            </div>
        </div>

        <!-- ACTIONS -->
        <div class="content-box" style="flex: 1; min-width: 200px; background: #222; margin: 0;">
            <h3 style="margin-top: 0;">Aktionen</h3>
            <div style="display: flex; gap: 10px; flex-direction: column;">
                <form method="post">
                    <input type="hidden" name="action" value="loot">
                    <button type="submit" class="btn-primary" style="width: 100%; padding: 10px;">🔍 Looten (-10 Hunger)</button>
                </form>
                <form method="post">
                    <input type="hidden" name="action" value="sleep">
                    <button type="submit" style="width: 100%; padding: 10px;">💤 Schlafen (HP/Energie)</button>
                </form>
            </div>
        </div>

        <!-- EQUIP MANAGEMENT -->
        <div class="content-box" style="flex: 1; min-width: 250px; background: #222; margin: 0;">
            <h3 style="margin-top: 0;">Ausrüstung ändern</h3>
            <!-- WAFFEN -->
            <form method="post" style="display:flex; margin-bottom:10px;">
                <input type="hidden" name="action" value="equip">
                <input type="hidden" name="slot" value="weapon">
                <select name="inv_id" style="flex:1; margin-right:5px; padding:8px; color: black;">
                    <option value="">-- Waffe wählen --</option>
                    <?php foreach ($weapons as $w): ?>
                        <option value="<?php echo $w['id']; ?>"><?php echo $w['name']; ?> (+<?php echo $w['effect_value']; ?> Atk)</option>
                    <?php
endforeach; ?>
                </select>
                <button style="padding: 8px;">OK</button>
            </form>

            <!-- RÜSTUNG -->
             <form method="post" style="display:flex;">
                <input type="hidden" name="action" value="equip">
                <input type="hidden" name="slot" value="armor">
                <select name="inv_id" style="flex:1; margin-right:5px; padding:8px; color: black;">
                    <option value="">-- Rüstung wählen --</option>
                    <?php foreach ($armors as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo $a['name']; ?> (+<?php echo $a['effect_value']; ?> Def)</option>
                    <?php
endforeach; ?>
                </select>
                <button style="padding: 8px;">OK</button>
            </form>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
