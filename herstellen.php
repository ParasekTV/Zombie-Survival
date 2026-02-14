<?php
// herstellen.php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';

// Hilfsfunktionen (Könnten in eine functions.php ausgelagert werden)
function checkItem($pdo, $userId, $itemName, $amount)
{
    $stmt = $pdo->prepare("SELECT amount FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.user_id = ? AND i.name = ?");
    $stmt->execute([$userId, $itemName]);
    $res = $stmt->fetch();
    return ($res && $res['amount'] >= $amount);
}

function consumeItem($pdo, $userId, $itemName, $amount)
{
    $stmt = $pdo->prepare("SELECT id FROM items WHERE name = ?");
    $stmt->execute([$itemName]);
    $itemId = $stmt->fetchColumn();
    $pdo->prepare("UPDATE inventory SET amount = amount - ? WHERE user_id = ? AND item_id = ?")->execute([$amount, $userId, $itemId]);
}

function giveItem($pdo, $userId, $itemName, $amount)
{
    $stmt = $pdo->prepare("SELECT id FROM items WHERE name = ?");
    $stmt->execute([$itemName]);
    $itemId = $stmt->fetchColumn();

    // Check exist
    $chk = $pdo->prepare("SELECT id FROM inventory WHERE user_id = ? AND item_id = ?");
    $chk->execute([$userId, $itemId]);
    if ($chk->fetch()) {
        $pdo->prepare("UPDATE inventory SET amount = amount + ? WHERE user_id = ? AND item_id = ?")->execute([$amount, $userId, $itemId]);
    }
    else {
        $pdo->prepare("INSERT INTO inventory (user_id, item_id, amount) VALUES (?, ?, ?)")->execute([$userId, $itemId, $amount]);
    }
}

function getSkill($pdo, $userId)
{
    $stmt = $pdo->prepare("SELECT survival_skill FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// REZEPTE DEFINITION
$recipes = [
    'messer' => [
        'name' => 'Messer',
        'cost' => [['Eisen', 1], ['Holz', 1]],
        'desc' => 'Kleine Waffe (Atk +5)',
        'req_skill' => 0
    ],
    'speer' => [
        'name' => 'Speer',
        'cost' => [['Holz', 3], ['Stein', 1], ['Messer', 1]],
        'desc' => 'Mittlere Reichweite (Atk +10)',
        'req_skill' => 0
    ],
    'axt' => [
        'name' => 'Axt',
        'cost' => [['Holz', 2], ['Stein', 2]],
        'desc' => 'Werkzeug. Hilft beim Bauen.',
        'req_skill' => 0
    ],
    'verband' => [
        'name' => 'Verband',
        'cost' => [['Pilze', 5]],
        'desc' => 'Heilt Wunden (+50 HP). Aus Pilzfasern.',
        'req_skill' => 0
    ],
    'machete' => [
        'name' => 'Machete',
        'cost' => [['Eisen', 3], ['Holz', 1], ['Messer', 1]],
        'desc' => 'Sehr scharfe Klinge. (Atk +15)',
        'req_skill' => 1
    ],
    'kevlar' => [
        'name' => 'Kevlar Weste',
        'cost' => [['Verband', 5], ['Eisen', 5]], // Kreatives Rezept
        'desc' => 'Bietet Schutz. (Def +10)',
        'req_skill' => 2
    ],
    'helm' => [
        'name' => 'Helm',
        'cost' => [['Eisen', 5]],
        'desc' => 'Schützt den Kopf. (Def +5)',
        'req_skill' => 1
    ],
    'stiefel' => [
        'name' => 'Stiefel',
        'cost' => [['Eisen', 2], ['Verband', 2]],
        'desc' => 'Festes Schuhwerk. (Def +3)',
        'req_skill' => 1
    ],
    'medikit' => [
        'name' => 'Medikit',
        'cost' => [['Verband', 3], ['Wasser', 2], ['Energy Drink', 1]],
        'desc' => 'Professionelle Hilfe. (Full HP)',
        'req_skill' => 3
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $craft = $_POST['item'];

    if (isset($recipes[$craft])) {
        $recipe = $recipes[$craft];
        $canCraft = true;

        // Prüfen
        $userSkill = getSkill($pdo, $_SESSION['user_id']);

        if ($userSkill < $recipe['req_skill']) {
            $canCraft = false;
            $message = "<div class='alert alert-error'>Skill zu niedrig! Benötigt: Level {$recipe['req_skill']} (Du hast: $userSkill)</div>";
        }
        else {
            foreach ($recipe['cost'] as $req) {
                if (!checkItem($pdo, $_SESSION['user_id'], $req[0], $req[1])) {
                    $canCraft = false;
                    $message = "<div class='alert alert-error'>Nicht genug Materialien!</div>"; // Überschreibt skill message, ok
                    break;
                }
            }
        }

        if ($canCraft) {
            // Abziehen
            foreach ($recipe['cost'] as $req) {
                consumeItem($pdo, $_SESSION['user_id'], $req[0], $req[1]);
            }
            // Geben
            giveItem($pdo, $_SESSION['user_id'], $recipe['name'], 1);
            $message = "<div class='alert alert-success'>{$recipe['name']} hergestellt!</div>";
        }
    }
}

?>

<div class="content-box">
    <h2>Werkbank</h2>
    <p>Stelle Waffen und Werkzeuge her, um deine Überlebenschancen zu erhöhen.</p>
    
    <?php echo $message; ?>

    <div class="crafting-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
        <?php foreach ($recipes as $key => $r): ?>
            <div class="recipe-card" style="border: 1px solid #555; padding: 1rem; background: #222;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem;">
                    <img src="<?php echo getIconPath($r['name'], 'items'); ?>" alt="" style="width: 32px; height: 32px;">
                    <h3 style="color: var(--accent-color); margin: 0;"><?php echo $r['name']; ?></h3>
                </div>
                <p style="font-size: 0.9rem; margin-bottom: 0.5rem; color: #ccc;"><?php echo $r['desc']; ?></p>
                
                <?php
    $currSkill = getSkill($pdo, $_SESSION['user_id']);
    $skillColor = ($currSkill >= $r['req_skill']) ? '#7f7' : '#f77';
?>
                <?php if ($r['req_skill'] > 0): ?>
                    <div style="font-size: 0.8rem; margin-bottom: 5px; color: <?php echo $skillColor; ?>;">
                        Benötigter Skill: <?php echo $r['req_skill']; ?> (Du hast: <?php echo $currSkill; ?>)
                    </div>
                <?php
    endif; ?>
                
                <div class="costs" style="background: #111; padding: 10px; margin-bottom: 10px; font-size: 0.85rem; border-radius: 4px;">
                    <strong style="display: block; margin-bottom: 5px;">Benötigt:</strong>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <?php foreach ($r['cost'] as $c): ?>
                            <div style="display: flex; align-items: center; gap: 5px; background: #333; padding: 2px 6px; border-radius: 4px;">
                                <img src="<?php echo getIconPath($c[0], 'items'); ?>" alt="" style="width: 16px; height: 16px;">
                                <span><?php echo $c[1] . 'x ' . $c[0]; ?></span>
                            </div>
                        <?php
    endforeach; ?>
                    </div>
                </div>
                
                <form method="post">
                    <input type="hidden" name="item" value="<?php echo $key; ?>">
                    <button type="submit" style="width: 100%;">Herstellen</button>
                </form>
            </div>
        <?php
endforeach; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
