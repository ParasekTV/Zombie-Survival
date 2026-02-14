<?php
require_once 'config.php';

$error = '';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Bitte alle Felder ausfüllen.";
    }
    else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Login erfolgreich
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: dashboard.php");
            exit;
        }
        else {
            $error = "Ungültiger Benutzername oder Passwort.";
        }
    }
}

include 'includes/header.php';
?>

<div class="form-container">
    <h2>Login</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php
endif; ?>
    
    <form method="post" action="">
        <label>Benutzername:</label>
        <input type="text" name="username" required>
        
        <label>Passwort:</label>
        <input type="password" name="password" required>
        
        <button type="submit">Einloggen</button>
    </form>
    
    <p style="text-align: center; margin-top: 1rem;">
        Noch <strong>frisches Fleisch</strong>? <a href="register.php" style="color: var(--accent-color);">Hier registrieren</a>
    </p>
</div>

<?php include 'includes/footer.php'; ?>
