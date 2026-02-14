<?php
// config.php - Datenbankverbindung und Session-Start

$host = 'localhost';
$dbname = 'zombie_survival';
$user = 'survival'; // Standard XAMPP User
$pass = ''; // Standard XAMPP Passwort (leer)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
catch (PDOException $e) {
    // In Produktion: Fehler loggen, nicht ausgeben!
    die("Verbindungsfehler: " . $e->getMessage() . "<br>Bitte stellen Sie sicher, dass die Datenbank '$dbname' existiert und XAMPP/MySQL läuft.");
}

// Session starten, falls noch nicht geschehen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Online-Status aktualisieren (wenn eingeloggt)
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }
    catch (PDOException $e) {
    // Ignorieren falls Spalte noch nicht existiert (während Deployment)
    }
}

// Helper: Icon Pfad
// Helper: Icon Pfad
function getIconPath($name, $type = 'items')
{
    // Einfaches Mapping: Name kleinschreiben, Umlaute ersetzen
    $filename = strtolower($name);
    $filename = str_replace(['ä', 'ö', 'ü', ' ', ':'], ['ae', 'oe', 'ue', '_', ''], $filename);

    // Check SVG first (High Quality)
    $pathSvg = "assets/icons/$type/$filename.svg";
    if (file_exists($pathSvg)) {
        return $pathSvg;
    }

    // Check PNG second
    $pathPng = "assets/icons/$type/$filename.png";
    if (file_exists($pathPng)) {
        return $pathPng;
    }

    // Fallback: Online Placeholder
    $initial = strtoupper(mb_substr($name, 0, 1));
    return "https://placehold.co/32x32/333333/ffffff.png?text=$initial";
}
?>
