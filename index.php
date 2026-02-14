<?php
require_once 'config.php';
include 'includes/header.php';
?>

<div style="text-align: center; padding: 2rem;">
    <h2>Willkommen in der Apokalypse</h2>
    <p>Die Welt ist nicht mehr das, was sie einmal war. Überlebe gegen Horden von Untoten, suche nach Nahrung und kämpfe um dein Leben.</p>
    
    <div style="margin: 2rem 0; font-size: 4rem;">🧟</div>

    <?php if (isset($_SESSION['user_id'])): ?>
        <p>Willkommen zurück, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>.</p>
        <a href="dashboard.php"><button style="max-width: 200px;">Zum Dashboard</button></a>
    <?php
else: ?>
        <p>Bist du bereit zu überleben?</p>
        <a href="register.php"><button style="max-width: 200px;">Jetzt Registrieren</button></a>
    <?php
endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
