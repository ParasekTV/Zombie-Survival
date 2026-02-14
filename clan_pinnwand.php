<?php
// clan_pinnwand.php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';

// Check Clan Membership
$stmt = $pdo->prepare("SELECT c.id, c.name FROM clans c JOIN clan_members cm ON c.id = cm.clan_id WHERE cm.user_id = ?");
$stmt->execute([$userId]);
$myClan = $stmt->fetch();

if (!$myClan) {
    header("Location: clan.php");
    exit;
}

// NEUE NACHRICHT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_msg'])) {
    $msg = trim($_POST['message']);
    if (!empty($msg)) {
        $stmt = $pdo->prepare("INSERT INTO clan_messages (clan_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$myClan['id'], $userId, $msg]);
    }
}

// NACHRICHTEN LADEN
$stmt = $pdo->prepare("
    SELECT cm.message, cm.created_at, u.username 
    FROM clan_messages cm 
    JOIN users u ON cm.user_id = u.id 
    WHERE cm.clan_id = ? 
    ORDER BY cm.created_at DESC 
    LIMIT 50");
$stmt->execute([$myClan['id']]);
$posts = $stmt->fetchAll();

?>

<div class="content-box">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>💬 Geheimkanal: <?php echo htmlspecialchars($myClan['name']); ?></h2>
        <a href="clan.php"><button>Zurück zum HQ</button></a>
    </div>

    <!-- POST FORM -->
    <div style="margin-bottom: 2rem; background: #222; padding: 1rem; border-radius: 4px;">
        <form method="post">
            <textarea name="message" placeholder="Nachricht an den Clan..." style="width: 100%; height: 60px; background: #111; color: white; border: 1px solid #444; padding: 5px;"></textarea>
            <button type="submit" name="new_msg" style="margin-top: 5px;">Senden 📡</button>
        </form>
    </div>

    <!-- MESSAGES -->
    <div class="clan-chat">
        <?php foreach ($posts as $p): ?>
            <div class="chat-msg" style="border-bottom: 1px solid #333; padding: 10px 0;">
                <div style="font-size: 0.8rem; color: #888; margin-bottom: 2px;">
                    <strong style="color: var(--primary-color);"><?php echo htmlspecialchars($p['username']); ?></strong> 
                    am <?php echo date('d.m. H:i', strtotime($p['created_at'])); ?>:
                </div>
                <div style="font-size: 1rem;">
                    <?php echo nl2br(htmlspecialchars($p['message'])); ?>
                </div>
            </div>
        <?php
endforeach; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
