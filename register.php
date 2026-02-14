<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Bitte Benutzername und Passwort ausfüllen.";
    }
    else {
        // Prüfen ob User existiert
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->rowCount() > 0) {
            $error = "Benutzername bereits vergeben.";
        }
        else {
            // User anlegen
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            if ($stmt->execute([$username, $hash])) {
                $success = "Erfolgreich registriert! Du kannst dich jetzt einloggen.";
            }
            else {
                $error = "Datenbankfehler beim Registrieren.";
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="form-container">
    <h2>Registrieren</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php
endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <a href="login.php"><button>Zum Login</button></a>
    <?php
else: ?>
        <form method="post" action="">
            <label>Benutzername:</label>
            <input type="text" name="username" required minlength="3" maxlength="20">
            
            <label>Passwort:</label>
            <input type="password" name="password" required minlength="4">
            
            <button type="submit">Account erstellen</button>
        </form>
    <?php
endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
