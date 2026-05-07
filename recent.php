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

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$error = null;
$pastes = [];
$totalPages = 1;

try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM pastes WHERE is_public = 1 AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())");
    $total = (int)$countStmt->fetch()['total'];
    $totalPages = max(1, (int)ceil($total / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $stmt = $pdo->prepare(
        "SELECT slug, title, syntax, view_count, created_at
         FROM pastes
         WHERE is_public = 1 AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
         ORDER BY created_at DESC
         LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pastes = $stmt->fetchAll();
} catch (Throwable $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recent Pastes</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include __DIR__ . '/topnav.php'; ?>

<main class="container">
    <h1>Recent Pastes</h1>

    <?php if ($error !== null): ?>
        <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="card">
        <?php if ($pastes === []): ?>
            <p>No public pastes found.</p>
        <?php else: ?>
            <table class="recent-table">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Syntax</th>
                    <th>Views</th>
                    <th>Created</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($pastes as $paste): ?>
                    <tr>
                        <td><a href="paste.php?s=<?= urlencode((string)$paste['slug']) ?>"><?= htmlspecialchars((string)$paste['title'], ENT_QUOTES, 'UTF-8') ?></a></td>
                        <td><?= htmlspecialchars((string)$paste['syntax'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$paste['view_count'] ?></td>
                        <td><?= htmlspecialchars(humanTime((string)$paste['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="pagination" aria-label="Pages">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="page-btn <?= $i === $page ? 'active' : '' ?>" href="recent.php?page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</main>

<footer class="site-footer">
    <p>© <?= date('Y') ?> DarkPaste — Simple and secure snippet sharing.</p>
</footer>
</body>
</html>
