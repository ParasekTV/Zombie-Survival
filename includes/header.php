<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zombie Survival Browser Game</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="wrapper">
        <header>
            <h1>Zombie Survival</h1>
            <nav>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="looten.php">Plündern</a>
                    <a href="inventar.php">Inventar</a>
                    <a href="herstellen.php">Herstellen</a>
                    <a href="arena.php">Arena</a>
                    <a href="clan.php">Clan</a>
                    <a href="rangliste.php">Rangliste</a>
                    <a href="base.php">Base</a>
                    <a href="pinnwand.php">Pinnwand</a>
                    <?php if ($_SESSION['user_id'] == 1): ?>
                        <a href="admin.php" style="color: #ff5555;">[ADMIN]</a>
                    <?php
    endif; ?>
                    <a href="logout.php">Logout</a>
                <?php
else: ?>
                    <a href="index.php">Startseite</a>
                    <a href="login.php">Login</a>
                    <a href="register.php">Registrieren</a>
                <?php
endif; ?>
            </nav>
        </header>
        <main>
