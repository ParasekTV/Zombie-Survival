<?php
// arena.php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';
$cooldownSec = 300; // 5 Minuten Cooldown

// Hilfsfunktion: Stärkste Waffe holen
function getWeaponStrength($pdo, $userId)
{
    $sql = "SELECT MAX(i.value) as str 
            FROM inventory inv 
            JOIN items i ON inv.item_id = i.id 
            WHERE inv.user_id = ? AND i.type = 'weapon'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $res = $stmt->fetch();
    return $res['str'] ?? 0; // 0 wenn keine Waffe
}

// Hilfsfunktion: Clan Mauer Bonus holen
function getClanWallBonus($pdo, $userId)
{
    $sql = "SELECT cb.level 
            FROM clan_buildings cb 
            JOIN clan_members cm ON cb.clan_id = cm.clan_id 
            WHERE cm.user_id = ? AND cb.type = 'mauer'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $lvl = $stmt->fetchColumn();
    return ($lvl) ? $lvl * 5 : 0; // 5 Defense pro Level
}

// KAMPF LOGIK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_id'])) {
    $targetId = (int)$_POST['target_id'];

    // Eigene Daten laden
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $attacker = $stmt->fetch();

    $lastAttack = strtotime($attacker['last_attack'] ?? '2000-01-01');

    if (time() - $lastAttack < $cooldownSec) {
        $wait = ceil(($cooldownSec - (time() - $lastAttack)) / 60);
        $message = "<div class='alert alert-error'>Du ruhst dich noch vom letzten Kampf aus. Warte $wait Minuten.</div>";
    }
    elseif ($attacker['energy'] < 10) {
        $message = "<div class='alert alert-error'>Du bist zu erschöpft zum Kämpfen! (Benötigt 10 Energie)</div>";
    }
    else {
        // Gegner laden
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $defender = $stmt->fetch();

        if ($defender) {
            // Stats berechnen
            $atkStr = getWeaponStrength($pdo, $attacker['id']);
            $defStr = getWeaponStrength($pdo, $defender['id']);

            // CLAN BONUS
            $defBonus = getClanWallBonus($pdo, $defender['id']);

            // Random Factor
            $atkRand = rand(0, 50);
            $defRand = rand(0, 50);

            // Total Scores
            $atkScore = $atkRand + $atkStr;
            $defScore = $defRand + $defStr + $defBonus;

            // Energie & Cooldown Update
            $pdo->prepare("UPDATE users SET energy = energy - 10, last_attack = NOW() WHERE id = ?")->execute([$attacker['id']]);

            // Result
            if ($atkScore > $defScore) {
                // GEWONNEN
                $loot = floor($defender['taler'] * 0.10); // 10% Taler klauen

                // Transaktion
                $pdo->prepare("UPDATE users SET taler = taler - ?, health = GREATEST(0, health - 10) WHERE id = ?")->execute([$loot, $defender['id']]);
                $pdo->prepare("UPDATE users SET taler = taler + ? WHERE id = ?")->execute([$loot, $attacker['id']]);

                $log = "<strong>Sieg!</strong> Du hast {$defender['username']} besiegt.<br>";
                $log .= "Dein Score: <strong>$atkScore</strong> (Waffe: $atkStr + Glück: $atkRand)<br>";
                $log .= "Gegner Score: $defScore (Waffe: $defStr + Mauer: $defBonus + Glück: $defRand)<br>";
                $log .= "Beute: <strong>$loot Taler</strong>!";

                $message = "<div class='alert alert-success'>$log</div>";
            }
            else {
                // VERLOREN
                $pdo->prepare("UPDATE users SET health = GREATEST(0, health - 5) WHERE id = ?")->execute([$attacker['id']]);

                $log = "<strong>Niederlage!</strong> {$defender['username']} hat sich verteidigt.<br>";
                $log .= "Dein Score: $atkScore (Waffe: $atkStr + Glück: $atkRand)<br>";
                $log .= "Gegner Score: <strong>$defScore</strong> (Waffe: $defStr + Mauer: +$defBonus + Glück: $defRand)<br>";
                $log .= "Du verlierst 5 HP.";

                $message = "<div class='alert alert-error'>$log</div>";
            }

        }
        else {
            $message = "<div class='alert alert-error'>Gegner nicht gefunden.</div>";
        }
    }
}

// Gegnerliste laden (Nur Online-Spieler: Letzte 5 Minuten aktiv, außer mir)
$enemies = [];
try {
    $stmt = $pdo->prepare("SELECT id, username, health, level FROM users WHERE id != ? AND last_active > NOW() - INTERVAL 5 MINUTE ORDER BY taler DESC LIMIT 20");
    $stmt->execute([$_SESSION['user_id']]);
    $enemies = $stmt->fetchAll();
}
catch (PDOException $e) {
// Fallback falls 'last_active' Spalte noch nicht existiert
// Zeige leere Liste oder alle Spieler (hier: leere Liste, damit User merkt, dass was fehlt?)
// Besser: Zeige Warnung. Aber da wir "No players" Message haben, reicht leeres Array.
}
?>

<div class="content-box">
    <h2>PVP Arena</h2>
    <p>Hier gelten keine Gesetze. Kämpfe gegen andere Überlebende um Ruhm und Taler.</p>
    <p><em>(Angriffe kosten 10 Energie. Cooldown: 5 Minuten)</em></p>

    <?php echo $message; ?>

    <?php if (empty($enemies)): ?>
        <div class="alert alert-info">Gerade befindet sich kein anderer Spieler in deiner Nähe.</div>
    <?php
else: ?>
        <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
            <thead>
                <tr style="background: #333; text-align: left;">
                    <th style="padding: 10px;">Spieler</th>
                    <th style="padding: 10px;">Zustand</th>
                    <th style="padding: 10px;">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enemies as $e): ?>
                    <tr style="border-bottom: 1px solid #444;">
                        <td style="padding: 10px;">
                            <strong><?php echo htmlspecialchars($e['username']); ?></strong>
                        </td>
                        <td style="padding: 10px;">
                            HP: <?php echo $e['health']; ?>%
                        </td>
                        <td style="padding: 10px;">
                            <form method="post">
                                <input type="hidden" name="target_id" value="<?php echo $e['id']; ?>">
                                <button type="submit" style="background: darkred; font-size: 0.8rem; padding: 5px 10px;">Angreifen ⚔️</button>
                            </form>
                        </td>
                    </tr>
                <?php
    endforeach; ?>
            </tbody>
        </table>
    <?php
endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
