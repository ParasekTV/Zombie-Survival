<?php
// rangliste.php
require_once 'config.php';
include 'includes/header.php';

// Top Spieler (Sortiert nach Taler)
$stmt = $pdo->query("SELECT username, taler, level, health, location FROM users ORDER BY taler DESC, level DESC LIMIT 50");
$topPlayers = $stmt->fetchAll();

// Top Clans (Sortiert nach Taler in der Kasse)
$stmt = $pdo->query("SELECT name, tag, taler, (SELECT COUNT(*) FROM clan_members WHERE clan_id = clans.id) as members FROM clans ORDER BY taler DESC LIMIT 20");
$topClans = $stmt->fetchAll();
?>

<div class="content-box">
    <h2>🏆 Hall of Fame</h2>
    <p>Die mächtigsten Überlebenden und Clans der neuen Welt.</p>

    <div class="row" style="display: flex; gap: 2rem; flex-wrap: wrap;">
        
        <!-- TOP SPIELER -->
        <div style="flex: 1; min-width: 300px;">
            <h3>Reichste Überlebende</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <tr style="background: #333; text-align: left;">
                    <th style="padding: 5px;">#</th>
                    <th style="padding: 5px;">Name</th>
                    <th style="padding: 5px;">Ort</th>
                    <th style="padding: 5px;">Level</th>
                    <th style="padding: 5px;">Taler</th>
                </tr>
                <?php foreach ($topPlayers as $index => $p): ?>
                <tr style="border-bottom: 1px solid #444;">
                    <td style="padding: 5px;"><?php echo $index + 1; ?>.</td>
                    <td style="padding: 5px;"><strong><?php echo htmlspecialchars($p['username']); ?></strong></td>
                    <td style="padding: 5px; color: #888;"><?php echo htmlspecialchars($p['location']); ?></td>
                    <td style="padding: 5px;"><?php echo $p['level']; ?></td>
                    <td style="padding: 5px; color: gold;"><?php echo $p['taler']; ?> 💰</td>
                </tr>
                <?php
endforeach; ?>
            </table>
        </div>

        <!-- TOP CLANS -->
        <div style="flex: 1; min-width: 300px;">
            <h3>Mächtigste Clans</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <tr style="background: #333; text-align: left;">
                    <th style="padding: 5px;">#</th>
                    <th style="padding: 5px;">Tag</th>
                    <th style="padding: 5px;">Name</th>
                    <th style="padding: 5px;">Mitglieder</th>
                    <th style="padding: 5px;">Kasse</th>
                </tr>
                <?php
if (empty($topClans)) {
    echo "<tr><td colspan='5' style='padding:10px;'>Noch keine Clans gegründet.</td></tr>";
}
foreach ($topClans as $index => $c): ?>
                <tr style="border-bottom: 1px solid #444;">
                    <td style="padding: 5px;"><?php echo $index + 1; ?>.</td>
                    <td style="padding: 5px;">[<?php echo htmlspecialchars($c['tag']); ?>]</td>
                    <td style="padding: 5px;"><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                    <td style="padding: 5px;"><?php echo $c['members']; ?></td>
                    <td style="padding: 5px; color: gold;"><?php echo $c['taler']; ?> 💰</td>
                </tr>
                <?php
endforeach; ?>
            </table>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
