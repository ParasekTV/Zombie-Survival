<?php
// clan.php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';
$userId = $_SESSION['user_id'];

// Prüfen ob User im Clan ist
$stmt = $pdo->prepare("SELECT c.*, cm.role FROM clans c JOIN clan_members cm ON c.id = cm.clan_id WHERE cm.user_id = ?");
$stmt->execute([$userId]);
$myClan = $stmt->fetch();

// CLAN GRÜNDEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_clan'])) {
    $name = trim($_POST['clan_name']);
    $tag = trim($_POST['clan_tag']);

    if ($myClan) {
        $message = "<div class='alert alert-error'>Du bist bereits in einem Clan!</div>";
    }
    elseif (strlen($name) < 3 || strlen($tag) < 2 || strlen($tag) > 6) {
        $message = "<div class='alert alert-error'>Name (min 3) oder Tag (2-6 Zeichen) ungültig.</div>";
    }
    else {
        // Kosten Check (100 Taler)
        $stmt = $pdo->prepare("SELECT taler FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userTaler = $stmt->fetchColumn();

        if ($userTaler >= 100) {
            $pdo->beginTransaction();
            try {
                // Taler abziehen
                $pdo->prepare("UPDATE users SET taler = taler - 100 WHERE id = ?")->execute([$userId]);

                // Clan erstellen
                $stmt = $pdo->prepare("INSERT INTO clans (name, tag, owner_id) VALUES (?, ?, ?)");
                $stmt->execute([$name, $tag, $userId]);
                $clanId = $pdo->lastInsertId();

                // Member adden
                $stmt = $pdo->prepare("INSERT INTO clan_members (clan_id, user_id, role) VALUES (?, ?, 'leader')");
                $stmt->execute([$clanId, $userId]);

                $pdo->commit();
                header("Location: clan.php"); // Reload
                exit;
            }
            catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert alert-error'>Fehler beim Erstellen: " . $e->getMessage() . "</div>";
            }
        }
        else {
            $message = "<div class='alert alert-error'>Nicht genug Taler (Kosten: 100).</div>";
        }
    }
}

// BEWERBEN (User Seite)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_clan'])) {
    $clanId = (int)$_POST['clan_id'];
    $msg = trim($_POST['message']); // Optional

    // Check if already applied
    $stmt = $pdo->prepare("SELECT clan_id FROM clan_applications WHERE user_id = ? AND clan_id = ?");
    $stmt->execute([$userId, $clanId]);
    if ($stmt->fetch()) {
        $message = "<div class='alert alert-error'>Du hast dich dort bereits beworben.</div>";
    }
    else {
        $stmt = $pdo->prepare("INSERT INTO clan_applications (clan_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$clanId, $userId, $msg]);
        $message = "<div class='alert alert-success'>Bewerbung abgeschickt!</div>";
    }
}

// BEWERBUNG VERWALTEN (Leader/Officer Seite)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['handle_app'])) {
    if ($myClan && ($myClan['role'] == 'leader' || $myClan['role'] == 'officer')) {
        $appUserId = (int)$_POST['app_user_id'];
        $action = $_POST['action']; // 'accept' or 'reject'

        if ($action === 'reject') {
            $pdo->prepare("DELETE FROM clan_applications WHERE clan_id = ? AND user_id = ?")->execute([$myClan['id'], $appUserId]);
            $message = "<div class='alert alert-info'>Bewerbung abgelehnt.</div>";
        }
        elseif ($action === 'accept') {
            // CHECK MEMBER LIMIT
            // 1. Get current count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM clan_members WHERE clan_id = ?");
            $stmt->execute([$myClan['id']]);
            $memberCount = $stmt->fetchColumn();

            // 2. Get HQ Level
            $stmt = $pdo->prepare("SELECT level FROM clan_buildings WHERE clan_id = ? AND type = 'hauptquartier'");
            $stmt->execute([$myClan['id']]);
            $hqLvl = $stmt->fetchColumn() ?: 0;
            $limit = 5 + ($hqLvl * 2);

            if ($memberCount >= $limit) {
                $message = "<div class='alert alert-error'>Clan ist voll! (Limit: $limit). Bau das Hauptquartier aus.</div>";
            }
            else {
                // Accept
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("INSERT INTO clan_members (clan_id, user_id, role) VALUES (?, ?, 'member')")->execute([$myClan['id'], $appUserId]);
                    $pdo->prepare("DELETE FROM clan_applications WHERE user_id = ?")->execute([$appUserId]); // Delete all apps of this user
                    $pdo->commit();
                    $message = "<div class='alert alert-success'>Neues Mitglied aufgenommen!</div>";
                }
                catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "<div class='alert alert-error'>Fehler: " . $e->getMessage() . "</div>";
                }
            }
        }
    }
}

// MEMBER MANAGEMENT (Leader Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_member'])) {
    if ($myClan['role'] == 'leader') {
        $targetId = (int)$_POST['target_id'];
        $action = $_POST['action'];

        // Prevent self-action
        if ($targetId == $userId) {
            $message = "<div class='alert alert-error'>Du kannst dich nicht selbst verwalten.</div>";
        }
        else {
            if ($action == 'kick') {
                $pdo->prepare("DELETE FROM clan_members WHERE clan_id = ? AND user_id = ?")->execute([$myClan['id'], $targetId]);
                $message = "<div class='alert alert-success'>Mitglied entfernt.</div>";
            }
            elseif ($action == 'promote') {
                $pdo->prepare("UPDATE clan_members SET role = 'officer' WHERE clan_id = ? AND user_id = ?")->execute([$myClan['id'], $targetId]);
                $message = "<div class='alert alert-success'>Mitglied zum Offizier befördert.</div>";
            }
            elseif ($action == 'demote') {
                $pdo->prepare("UPDATE clan_members SET role = 'member' WHERE clan_id = ? AND user_id = ?")->execute([$myClan['id'], $targetId]);
                $message = "<div class='alert alert-success'>Offizier zum Mitglied degradiert.</div>";
            }
        }
    }
}

?>

<div class="content-box">
    <?php if ($myClan): ?>
        <!-- CLAN DASHBOARD -->
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>[<?php echo htmlspecialchars($myClan['tag']); ?>] <?php echo htmlspecialchars($myClan['name']); ?></h2>
            <span style="background: #333; padding: 5px 10px; border-radius: 4px;">Kasse: <?php echo $myClan['taler']; ?> Taler</span>
        </div>
        
        <p>Willkommen im Hauptquartier. Deine Rolle: <strong><?php echo ucfirst($myClan['role']); ?></strong></p>

        <div class="clan-nav" style="margin: 1rem 0; display: flex; gap: 10px;">
            <a href="clan_stadt.php"><button style="background: var(--primary-color);">🏰 Zur Clan-Stadt</button></a>
            <a href="clan_pinnwand.php"><button style="background: #444;">💬 Interne Pinnwand</button></a>
        </div>

        <h3>Mitglieder</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <?php
    $stmt = $pdo->prepare("SELECT u.id, u.username, cm.role, u.last_active FROM clan_members cm JOIN users u ON cm.user_id = u.id WHERE cm.clan_id = ? ORDER BY FIELD(cm.role, 'leader', 'officer', 'member')");
    $stmt->execute([$myClan['id']]);
    $members = $stmt->fetchAll();
    foreach ($members as $m):
        $online = (strtotime($m['last_active']) > time() - 300) ? '🟢' : '⚫';
?>
            <tr style="border-bottom: 1px solid #444;">
                <td style="padding: 10px;"><?php echo $online . ' ' . htmlspecialchars($m['username']); ?></td>
                <td style="padding: 10px;"><?php echo ucfirst($m['role']); ?></td>
                
                <!-- ACTIONS (Leader Only) -->
                <?php if ($myClan['role'] == 'leader' && $m['id'] != $userId): ?>
                <td style="padding: 10px; text-align: right;">
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="target_id" value="<?php echo $m['id']; ?>">
                        <input type="hidden" name="manage_member" value="1">
                        
                        <?php if ($m['role'] == 'member'): ?>
                            <button type="submit" name="action" value="promote" style="font-size:0.7rem; padding: 2px 5px; background: #666;" title="Befördern">⬆️</button>
                        <?php
            elseif ($m['role'] == 'officer'): ?>
                             <button type="submit" name="action" value="demote" style="font-size:0.7rem; padding: 2px 5px; background: #666;" title="Degradieren">⬇️</button>
                        <?php
            endif; ?>

                        <button type="submit" name="action" value="kick" style="font-size:0.7rem; padding: 2px 5px; background: darkred;" title="Rauswerfen" onclick="return confirm('Wirklich kicken?');">✖</button>
                    </form>
                </td>
                <?php
        else: ?>
                <td></td>
                <?php
        endif; ?>
            </tr>
            <?php
    endforeach; ?>
        </table>

        <!-- APPLICATIONS (Leader only) -->
        <?php if ($myClan['role'] == 'leader' || $myClan['role'] == 'officer'):
        $stmt = $pdo->prepare("SELECT u.id, u.username, ca.message FROM clan_applications ca JOIN users u ON ca.user_id = u.id WHERE ca.clan_id = ?");
        $stmt->execute([$myClan['id']]);
        $apps = $stmt->fetchAll();
        if (!empty($apps)):
?>
            <h3 style="color: yellow; margin-top: 2rem;">Offene Bewerbungen</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <?php foreach ($apps as $app): ?>
                <tr style="border-bottom: 1px solid #444;">
                    <td style="padding: 10px;">
                        <strong><?php echo htmlspecialchars($app['username']); ?></strong><br>
                        <em style="font-size: 0.8rem;"><?php echo htmlspecialchars($app['message']); ?></em>
                    </td>
                    <td style="padding: 10px; text-align: right;">
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="app_user_id" value="<?php echo $app['id']; ?>">
                            <button type="submit" name="handle_app" value="accept" style="background: green; padding: 5px;">✔</button>
                            <input type="hidden" name="action" value="accept">
                        </form>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="app_user_id" value="<?php echo $app['id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" name="handle_app" value="reject" style="background: darkred; padding: 5px;">✖</button>
                        </form>
                    </td>
                </tr>
                <?php
            endforeach; ?>
            </table>
        <?php
        endif;
    endif; ?>

    <?php
else: ?>
        <!-- NO CLAN -->
        <?php echo $message; ?>
        
        <h2>Clan System</h2>
        <div class="row" style="display: flex; gap: 2rem; flex-wrap: wrap;">
            
            <!-- GRÜNDEN -->
            <div style="flex: 1; min-width: 300px; border: 1px solid #555; padding: 1rem;">
                <h3>Clan gründen</h3>
                <p>Kosten: 100 Taler</p>
                <form method="post">
                    <label>Clan Name:</label><br>
                    <input type="text" name="clan_name" required placeholder="Die Überlebenden"><br><br>
                    <label>Clan Tag (2-6 Zeichen):</label><br>
                    <input type="text" name="clan_tag" required placeholder="SURV" maxlength="6"><br><br>
                    <button type="submit" name="create_clan">Gründen (100💰)</button>
                </form>
            </div>

            <!-- FINDEN -->
            <div style="flex: 1; min-width: 300px; border: 1px solid #555; padding: 1rem;">
                <h3>Clan beitreten</h3>
                <p>Liste der existierenden Clans:</p>
                <ul>
                    <?php
    $stmt = $pdo->query("SELECT c.id, c.name, c.tag, COUNT(cm.id) as members FROM clans c LEFT JOIN clan_members cm ON c.id = cm.clan_id GROUP BY c.id ORDER BY members DESC LIMIT 10");
    $clans = $stmt->fetchAll();
    if (empty($clans))
        echo "<li>Noch keine Clans gegründet.</li>";
    foreach ($clans as $c) {
        echo "<li style='margin-bottom: 10px; border-bottom: 1px solid #333; padding-bottom: 5px;'>
                                [" . htmlspecialchars($c['tag']) . "] " . htmlspecialchars($c['name']) . " (" . $c['members'] . " Member)
                                <form method='post' style='margin-top: 5px; display: flex; gap: 5px;'>
                                    <input type='hidden' name='clan_id' value='{$c['id']}'>
                                    <input type='text' name='message' placeholder='Deine Nachricht...' style='font-size: 0.8rem; padding: 2px; flex: 1;'>
                                    <button type='submit' name='apply_clan' style='font-size: 0.7rem; padding: 2px 5px;'>Bewerben</button>
                                </form>
                              </li>";
    }
?>
                </ul>
            </div>
            
        </div>
    <?php
endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
