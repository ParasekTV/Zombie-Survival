<?php
// inventar.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
include 'includes/header.php';

// Access Control
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ITEM BENUTZEN
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['use_item'])) {
    $itemName = $_POST['item_name'];

    // Check ownership
    $stmt = $pdo->prepare("SELECT inv.amount, inv.id FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE user_id = ? AND i.name = ?");
    $stmt->execute([$_SESSION['user_id'], $itemName]);
    $item = $stmt->fetch();

    if ($item && $item['amount'] > 0) {
        $used = false;

        $pdo->beginTransaction();
        try {
            // LOGIK: HEILUNG
            // LOGIK: HEILUNG
            if ($itemName === 'Verband') {
                echo "<!-- DEBUG: Using Verband -->";
                // Simple update first to test
                $pdo->prepare("UPDATE users SET health = health + 10 WHERE id = ?")->execute([$_SESSION['user_id']]);
                // Cap at 100 separately if needed, or trust LEAST later
                $pdo->prepare("UPDATE users SET health = 100 WHERE health > 100 AND id = ?")->execute([$_SESSION['user_id']]);

                $used = true;
                $message = "<div class='alert alert-success'>Du hast einen Verband benutzt. +10 HP!</div>";
                echo "<!-- DEBUG: Verband Logic Done -->";
            }

            // LOGIK: NAHRUNG (Hunger senken)
            elseif (in_array($itemName, ['Konserve', 'Wasser', 'Pilze', 'Fleisch'])) {
                $pdo->prepare("UPDATE users SET hunger = GREATEST(0, hunger - 10) WHERE id = ?")->execute([$_SESSION['user_id']]);
                $used = true;
                $message = "<div class='alert alert-success'>Lecker! Du hast $itemName gegessen. -10 Hunger.</div>";
            }

            // LOGIK: ENERGIE
            elseif (in_array($itemName, ['Kaffee', 'Energy Drink'])) {
                $pdo->prepare("UPDATE users SET energy = LEAST(100, energy + 10) WHERE id = ?")->execute([$_SESSION['user_id']]);
                $used = true;
                $message = "<div class='alert alert-success'>Wachmacher! Du hast $itemName getrunken. +10 Energie.</div>";
            }

            // LOGIK: BUCH (Skill)
            elseif ($itemName === 'Buch: Überleben') {
                $pdo->prepare("UPDATE users SET survival_skill = survival_skill + 1 WHERE id = ?")->execute([$_SESSION['user_id']]);
                $used = true;

                // Neuen Skill-Level abrufen
                $stmt = $pdo->prepare("SELECT survival_skill FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $newLevel = $stmt->fetchColumn();

                $message = "<div class='alert alert-success' style='border-color: gold; color: gold;'>📖 Du hast das Buch gelesen. Dein Überlebens-Skill ist jetzt Level $newLevel!</div>";
            }

            else {
                $message = "<div class='alert alert-info'>Diesen Gegenstand kannst du nicht direkt benutzen.</div>";
            }

            // ITEM ENTFERNEN (wenn benutzt)
            if ($used) {
                if ($item['amount'] > 1) {
                    $pdo->prepare("UPDATE inventory SET amount = amount - 1 WHERE id = ?")->execute([$item['id']]);
                }
                else {
                    $pdo->prepare("DELETE FROM inventory WHERE id = ?")->execute([$item['id']]);
                }
                $pdo->commit();
            }
            else {
                $pdo->rollBack(); // Keine Aktion ausgeführt
            }
        }
        catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-error'>Fehler: " . $e->getMessage() . "</div>";
        }

    }
    else {
        $message = "<div class='alert alert-error'>Gegenstand nicht vorhanden.</div>";
    }
}

// Inventar laden
$stmt = $pdo->prepare("
    SELECT i.name, i.type, i.description, inv.amount 
    FROM inventory inv
    JOIN items i ON inv.item_id = i.id
    WHERE inv.user_id = ? AND inv.amount > 0
    ORDER BY i.type, i.name
");
$stmt->execute([$_SESSION['user_id']]);
$items = $stmt->fetchAll();

?>

<div class="content-box">
    <h2>Dein Rucksack</h2>
    <?php echo $message; ?>
    
    <?php if (empty($items)): ?>
        <p>Dein Rucksack ist leer. Geh looten!</p>
        <a href="looten.php"><button style="width: auto;">Jetzt plündern</button></a>
    <?php
else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #333; text-align: left;">
                    <th style="padding: 10px;">Gegenstand</th>
                    <th style="padding: 10px;">Typ</th>
                    <th style="padding: 10px;">Beschreibung</th>
                    <th style="padding: 10px;">Anzahl</th>
                    <th style="padding: 10px;">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr style="border-bottom: 1px solid #444;">
                        <td style="padding: 10px; font-weight: bold; color: var(--accent-color); display: flex; align-items: center; gap: 10px;">
                            <img src="<?php echo getIconPath($item['name'], 'items'); ?>" alt="" style="width: 32px; height: 32px; object-fit: contain;">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </td>
                        <td style="padding: 10px; font-size: 0.8rem; text-transform: uppercase;"><?php echo htmlspecialchars($item['type']); ?></td>
                        <td style="padding: 10px; color: #aaa;"><?php echo htmlspecialchars($item['description']); ?></td>
                        <td style="padding: 10px; font-size: 1.2rem;"><?php echo $item['amount']; ?>x</td>
                        <td style="padding: 10px;">
                            <?php
        $consumables = ['Verband', 'Konserve', 'Wasser', 'Pilze', 'Fleisch', 'Kaffee', 'Energy Drink', 'Buch: Überleben'];
        if (in_array($item['name'], $consumables)):
?>
                                <form method="post">
                                    <input type="hidden" name="item_name" value="<?php echo htmlspecialchars($item['name']); ?>">
                                    <button type="submit" name="use_item" style="background: #28a745; font-size: 0.8rem; padding: 5px 10px;">Benutzen</button>
                                </form>
                            <?php
        endif; ?>
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
