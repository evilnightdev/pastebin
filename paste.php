<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
ensureSessionStarted();
$user = currentUser();

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    $configFile = __DIR__ . '/config.sample.php';
}

$config = require $configFile;

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $config['db_host'],
    $config['db_port'],
    $config['db_name'],
    $config['db_charset']
);

$slug = trim((string)($_GET['s'] ?? ''));
$error = null;
$paste = null;
$authPassed = false;

if ($slug === '') {
    $error = 'Invalid paste link.';
} else {
    try {
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $stmt = $pdo->prepare('SELECT p.*, u.username AS username, u.avatar_path AS avatar_path FROM pastes p LEFT JOIN users u ON u.id = p.user_id WHERE p.slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $paste = $stmt->fetch();

        if (!$paste) {
            $error = 'Paste not found.';
        } elseif ($paste['expires_at'] !== null && strtotime((string)$paste['expires_at']) <= time()) {
            $error = 'This paste has expired.';
            $paste = null;
        } elseif ((int)$paste['is_public'] === 0) {
            $password = trim((string)($_POST['private_password'] ?? ''));
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $password !== '') {
                $authPassed = password_verify($password, (string)$paste['private_password_hash']);
                if (!$authPassed) {
                    $error = 'Incorrect private paste password.';
                }
            }
            if (!$authPassed) {
                $paste['content'] = '';
            }
        } else {
            $authPassed = true;
        }

        if ($paste !== null && (((int)$paste['is_public'] === 1) || $authPassed)) {
            $pdo->prepare('UPDATE pastes SET view_count = view_count + 1 WHERE id = :id')->execute([':id' => (int)$paste['id']]);
            $pdo->prepare('INSERT INTO paste_views (paste_id, viewed_at) VALUES (:paste_id, UTC_TIMESTAMP())')->execute([':paste_id' => (int)$paste['id']]);
            $paste['view_count'] = ((int)$paste['view_count']) + 1;
        }

        if (isset($_GET['download']) && $_GET['download'] === '1' && $paste !== null && (((int)$paste['is_public'] === 1) || $authPassed)) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="paste-' . $slug . '.txt"');
            echo (string)$paste['content'];
            exit;
        }
    } catch (Throwable $exception) {
        $error = 'Database error: ' . $exception->getMessage();
    }
}

$pageUrl = 'paste.php?s=' . urlencode($slug);
$rawUrl = 'raw.php?s=' . urlencode($slug);
$downloadUrl = 'paste.php?s=' . urlencode($slug) . '&download=1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Paste</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include __DIR__ . '/topnav.php'; ?>

<main class="container">
    <h1 class="paste-title"><?= $paste !== null ? htmlspecialchars((string)$paste['title'], ENT_QUOTES, 'UTF-8') : 'View Paste' ?></h1>
    <div class="title-divider"></div>

    <?php if ($error !== null): ?>
        <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($paste !== null): ?>
        <div class="grid paste-view-grid">
            <section class="left-column card profile-side">
                <div class="user-head">
                    <?php if (!empty($paste['avatar_path'])): ?>
                        <img class="avatar" src="<?= htmlspecialchars((string)$paste['avatar_path'], ENT_QUOTES, 'UTF-8') ?>" alt="avatar">
                    <?php else: ?>
                        <div class="avatar avatar-fallback"><?= strtoupper(substr((string)($paste['username'] ?? 'G'), 0, 1)) ?></div>
                    <?php endif; ?>
                    <div>
                        <p class="user-kicker">Author</p>
                        <p class="username"><?= htmlspecialchars((string)($paste['username'] ?? ($paste['user_id'] ? 'User' : 'Guest')), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <div class="meta-grid">
                    <div class="meta-item"><span>Views</span><strong><?= (int)$paste['view_count'] ?></strong></div>
                    <div class="meta-item"><span>Visibility</span><strong><?= ((int)$paste['is_public'] === 1) ? 'Public' : 'Private' ?></strong></div>
                    <div class="meta-item"><span>Expires</span><strong><?= htmlspecialchars(humanTime($paste['expires_at'] !== null ? (string)$paste['expires_at'] : null), ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="meta-item"><span>Created</span><strong><?= htmlspecialchars(humanTime((string)$paste['created_at']), ENT_QUOTES, 'UTF-8') ?></strong></div>
                </div>

                <div class="action-buttons two-col">
                    <button type="button" class="small-btn glass-btn" id="copyUrlBtn">Copy URL</button>
                    <a class="small-btn glass-btn link-btn" href="<?= htmlspecialchars($rawUrl, ENT_QUOTES, 'UTF-8') ?>">View Raw</a>
                    <button type="button" class="small-btn glass-btn" id="copyTextBtn">Copy Text</button>
                    <a class="small-btn glass-btn link-btn" href="<?= htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') ?>">Download</a>
                </div>
            </section>

            <section class="right-column card">
                <?php if ((int)$paste['is_public'] === 0 && !$authPassed): ?>
                    <form method="post" class="auth-form">
                        <label for="private_password">Enter private paste password</label>
                        <input type="password" id="private_password" name="private_password" required>
                        <button type="submit">Unlock</button>
                    </form>
                <?php else: ?>
                    <pre class="paste-content-box"><code id="pasteContent"><?= htmlspecialchars((string)$paste['content'], ENT_QUOTES, 'UTF-8') ?></code></pre>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</main>

<footer class="site-footer">
    <p>© <?= date('Y') ?> DarkPaste — Simple and secure snippet sharing.</p>
</footer>

<script>
const pageUrl = <?= json_encode($pageUrl, JSON_UNESCAPED_SLASHES) ?>;
const copyUrlBtn = document.getElementById('copyUrlBtn');
const copyTextBtn = document.getElementById('copyTextBtn');

if (copyUrlBtn) {
    copyUrlBtn.addEventListener('click', async () => {
        await navigator.clipboard.writeText(window.location.origin + '/' + pageUrl);
        copyUrlBtn.textContent = 'Copied!';
        setTimeout(() => copyUrlBtn.textContent = 'Copy URL', 1200);
    });
}

if (copyTextBtn) {
    copyTextBtn.addEventListener('click', async () => {
        const el = document.getElementById('pasteContent');
        const text = el ? el.innerText : '';
        await navigator.clipboard.writeText(text);
        copyTextBtn.textContent = 'Copied!';
        setTimeout(() => copyTextBtn.textContent = 'Copy Text', 1200);
    });
}
</script>
</body>
</html>
