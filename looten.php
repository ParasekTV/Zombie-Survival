<?php
// looten.php
require_once 'config.php';
include 'includes/header.php';

// Access Control
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';
$lootResult = [];

// Hilfsfunktion: Item ID holen
function getItemId($pdo, $name)
{
    $stmt = $pdo->prepare("SELECT id FROM items WHERE name = ?");
    $stmt->execute([$name]);
    return $stmt->fetchColumn();
}

// Hilfsfunktion: Item adden
function addItem($pdo, $userId, $itemName, $amount)
{
    $itemId = getItemId($pdo, $itemName);
    if (!$itemId)
        return; // Item existiert nicht

    // Check if exists
    $stmt = $pdo->prepare("SELECT id, amount FROM inventory WHERE user_id = ? AND item_id = ?");
    $stmt->execute([$userId, $itemId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $update = $pdo->prepare("UPDATE inventory SET amount = amount + ? WHERE id = ?");
        $update->execute([$amount, $existing['id']]);
    }
    else {
        $insert = $pdo->prepare("INSERT INTO inventory (user_id, item_id, amount) VALUES (?, ?, ?)");
        $insert->execute([$userId, $itemId, $amount]);
    }
}

// LOOT LOGIK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['location'])) {
    $location = $_POST['location'];
    $energyCost = 10;

    // User Stats laden
    $stmt = $pdo->prepare("SELECT energy, health, hunger FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userStats = $stmt->fetch();

    if ($userStats['energy'] < $energyCost) {
        $message = "<div class='alert alert-error'>Zu wenig Energie! Du musst dich ausruhen.</div>";
    }
    else {
        // Energie abziehen, Hunger erhöhen
        $newEnergy = $userStats['energy'] - $energyCost;
        $newHunger = min(100, $userStats['hunger'] + 10);
        $damage = 0;
        $lootText = "";

        // RNG & Drop Tables
        $chanceZombie = 0; // Wahrscheinlichkeit für Angriff
        $possibleLoot = []; // [Name, MinAmount, MaxAmount, Probability]

        switch ($location) {
            case 'wald':
                $chanceZombie = 20;
                $possibleLoot = [
                    ['Holz', 2, 5, 80],
                    ['Stein', 1, 3, 50],
                    ['Pilze', 1, 4, 40],
                    ['Wasser', 0, 1, 20]
                ];
                break;
            case 'stadt':
                $chanceZombie = 50; // Gefährlich!
                $possibleLoot = [
                    ['Konserve', 1, 2, 60],
                    ['Wasser', 1, 2, 60],
                    ['Beton', 1, 3, 30],
                    ['Eisen', 1, 2, 40],
                    ['Verband', 1, 2, 40], // Heilung
                    ['Kaffee', 1, 3, 40], // Energie
                    ['Energy Drink', 1, 2, 30], // Energie
                    ['Buch: Überleben', 0, 1, 5] // Selten
                ];
                break;
            case 'mine':
                $chanceZombie = 30;
                $possibleLoot = [
                    ['Stein', 3, 8, 90],
                    ['Kohle', 2, 5, 70],
                    ['Eisen', 1, 4, 50]
                ];
                break;
        }

        // 1. Zombie Event?
        if (rand(1, 100) <= $chanceZombie) {
            $dmgInfo = rand(5, 20);
            $damage += $dmgInfo;
            $lootText .= "<p style='color: red;'>⚠️ Ein Zombie hat dich überrascht! Du verlierst $dmgInfo HP.</p>";
        }

        // 2. Loot generieren
        $foundSomething = false;
        foreach ($possibleLoot as $lootItem) {
            $name = $lootItem[0];
            $min = $lootItem[1];
            $max = $lootItem[2];
            $prob = $lootItem[3];

            if (rand(1, 100) <= $prob) {
                $amount = rand($min, $max);
                if ($amount > 0) {
                    addItem($pdo, $_SESSION['user_id'], $name, $amount);
                    $icon = getIconPath($name, 'items');
                    $lootText .= "<div style='color: lightgreen; display: flex; align-items: center; gap: 5px; margin-bottom: 5px;'>
                                    <img src='$icon' style='width: 20px; height: 20px;'>
                                    <span>+ $amount x $name gefunden!</span>
                                  </div>";
                    $foundSomething = true;
                }
            }
        }

        // 3. Taler finden? (Nur Stadt & Mine)
        $talerChance = 0;
        $talerMin = 0;
        $talerMax = 0;

        if ($location === 'stadt') {
            $talerChance = 40;
            $talerMin = 5;
            $talerMax = 20;
        }
        elseif ($location === 'mine') {
            $talerChance = 20;
            $talerMin = 2;
            $talerMax = 10;
        }

        if ($talerChance > 0 && rand(1, 100) <= $talerChance) {
            $foundTaler = rand($talerMin, $talerMax);
            // Direkt DB Update für Taler
            $pdo->prepare("UPDATE users SET taler = taler + ? WHERE id = ?")->execute([$foundTaler, $_SESSION['user_id']]);
            $lootText .= "<p style='color: gold;'>+ $foundTaler Taler in einer alten Kasse gefunden!</p>";
            $foundSomething = true;
        }

        if (!$foundSomething) {
            $lootText .= "<p>Du hast leider nichts Nützliches gefunden.</p>";
        }

        // Stats update DB
        $newHealth = max(0, $userStats['health'] - $damage);
        $pdo->prepare("UPDATE users SET energy = ?, hunger = ?, health = ? WHERE id = ?")
            ->execute([$newEnergy, $newHunger, $newHealth, $_SESSION['user_id']]);

        $message = "<div class='content-box'><h3>Loot-Bericht ($location)</h3>$lootText</div>";
    }
}

?>

<div class="content-box">
    <h2>Umgebung Plündern</h2>
    <p>Wähle deinen Zielort. <br><strong>Warnung:</strong> Jeder Ausflug kostet Energie und birgt Gefahren!</p>
    
    <?php echo $message; ?>

    <div class="loot-locations" style="display: flex; gap: 1rem; flex-wrap: wrap;">
        
        <!-- WALD -->
        <div class="location-card" style="border: 1px solid lime; padding: 1rem; flex: 1; min-width: 200px;">
            <h3 style="color: lime;">🌲 Der Wald</h3>
            <p>Häufig: Holz, Stein, Nahrung (Pilze).</p>
            <p>Gefahr: <strong>Niedrig (20%)</strong></p>
            <form method="post">
                <input type="hidden" name="location" value="wald">
                <button type="submit">In den Wald gehen (-10 Energie)</button>
            </form>
        </div>

        <!-- STADT -->
        <div class="location-card" style="border: 1px solid orange; padding: 1rem; flex: 1; min-width: 200px;">
            <h3 style="color: orange;">🏙️ Verlassene Stadt</h3>
            <p>Häufig: Essen, Werkstoffe, Bücher.</p>
            <p>Gefahr: <strong>Hoch (50%)</strong></p>
            <form method="post">
                <input type="hidden" name="location" value="stadt">
                <button type="submit" style="background-color: #aa5500;">Stadt betreten (-10 Energie)</button>
            </form>
        </div>

        <!-- MINE -->
        <div class="location-card" style="border: 1px solid gray; padding: 1rem; flex: 1; min-width: 200px;">
            <h3 style="color: silver;">⛏️ Alte Mine</h3>
            <p>Häufig: Kohle, Eisen, Stein.</p>
            <p>Gefahr: <strong>Mittel (30%)</strong></p>
            <form method="post">
                <input type="hidden" name="location" value="mine">
                <button type="submit" style="background-color: #555;">Mine erkunden (-10 Energie)</button>
            </form>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
