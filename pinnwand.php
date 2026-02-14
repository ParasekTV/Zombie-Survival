<?php
// pinnwand.php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Neuen Post erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_post'])) {
    $content = trim($_POST['content']);
    if (!empty($content)) {
        $stmt = $pdo->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $content]);
    }
}

// Like toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_post_id'])) {
    $postId = (int)$_POST['like_post_id'];
    $userId = $_SESSION['user_id'];

    // Prüfen ob schon geliked
    $stmt = $pdo->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?")->execute([$postId, $userId]);
    }
    else {
        $pdo->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)")->execute([$postId, $userId]);
    }
    // Redirect um Resubmission zu verhindern
    header("Location: pinnwand.php");
    exit;
}

// Kommentar erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_content'], $_POST['post_id'])) {
    $content = trim($_POST['comment_content']);
    $postId = (int)$_POST['post_id'];
    if (!empty($content)) {
        $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$postId, $_SESSION['user_id'], $content]);
    }
    header("Location: pinnwand.php");
    exit;
}

// Posts laden (mit User und Like-Anzahl)
$sql = "SELECT p.*, u.username, 
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id AND user_id = ?) as user_liked
        FROM posts p 
        JOIN users u ON p.user_id = u.id 
        ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$posts = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="pinnwand-container">
    <h2>Schwarzes Brett</h2>
    
    <!-- Neuer Post Formular -->
    <div class="new-post-box" style="margin-bottom: 2rem; padding: 1rem; background: var(--panel-bg); border: 1px solid var(--border-color);">
        <form method="post" action="">
            <textarea name="content" placeholder="Was hast du gesehen? Warnungen? Fundorte?" style="width: 100%; height: 80px; margin-bottom: 10px; background: #111; color: #fff; border: 1px solid #444; padding: 10px;"></textarea>
            <button type="submit" name="new_post" style="width: auto;">Nachricht annageln</button>
        </form>
    </div>

    <!-- Posts Liste -->
    <div class="posts-list">
        <?php foreach ($posts as $post): ?>
            <div class="post" style="border: 1px solid #444; margin-bottom: 1.5rem; padding: 1rem; background: #222;">
                <div class="post-header" style="display: flex; justify-content: space-between; border-bottom: 1px solid #333; padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
                    <strong style="color: var(--primary-color);"><?php echo htmlspecialchars($post['username']); ?></strong>
                    <span style="font-size: 0.8rem; color: #888;"><?php echo $post['created_at']; ?></span>
                </div>
                
                <div class="post-content" style="font-size: 1.1rem; margin-bottom: 1rem;">
                    <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                </div>
                
                <div class="post-actions" style="display: flex; gap: 1rem; align-items: center;">
                    <form method="post" style="margin: 0;">
                        <input type="hidden" name="like_post_id" value="<?php echo $post['id']; ?>">
                        <button type="submit" style="width: auto; padding: 5px 10px; background: <?php echo $post['user_liked'] ? 'var(--primary-color)' : '#444'; ?>;">
                            👍 <?php echo $post['like_count']; ?>
                        </button>
                    </form>
                    
                    <!-- Kommentare (Simple implementation: Laden wir direkt dazu) -->
                    <?php
    $cStmt = $pdo->prepare("SELECT c.*, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? ORDER BY c.created_at ASC");
    $cStmt->execute([$post['id']]);
    $comments = $cStmt->fetchAll();
?>
                    <span style="color: #888;"><?php echo count($comments); ?> Kommentare</span>
                </div>

                <!-- Kommentar Liste -->
                <?php if (!empty($comments)): ?>
                    <div class="comments" style="margin-top: 1rem; padding-left: 1rem; border-left: 2px solid #333;">
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment" style="margin-bottom: 0.5rem; font-size: 0.9rem;">
                                <strong style="color: #aaa;"><?php echo htmlspecialchars($comment['username']); ?>:</strong>
                                <?php echo htmlspecialchars($comment['content']); ?>
                            </div>
                        <?php
        endforeach; ?>
                    </div>
                <?php
    endif; ?>

                <!-- Kommentar Form -->
                 <form method="post" style="margin-top: 0.5rem; display: flex;">
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    <input type="text" name="comment_content" placeholder="Kommentieren..." required style="flex: 1; margin-bottom: 0; margin-right: 5px;">
                    <button type="submit" style="width: auto; padding: 5px 10px;">Send</button>
                </form>
            </div>
        <?php
endforeach; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
