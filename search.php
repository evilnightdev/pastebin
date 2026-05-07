<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
ensureSessionStarted();
$user = currentUser();
$pdo = getPdo();

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$pastes = [];
$totalPages = 1;

if ($q !== '') {
    $count = $pdo->prepare("SELECT COUNT(*) FROM pastes WHERE is_public = 1 AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP()) AND (title LIKE :q OR content LIKE :q)");
    $count->execute([':q' => '%' . $q . '%']);
    $total = (int)$count->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $stmt = $pdo->prepare("SELECT slug, title, syntax, view_count, created_at FROM pastes WHERE is_public = 1 AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP()) AND (title LIKE :q OR content LIKE :q) ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pastes = $stmt->fetchAll();
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Search</title><link rel="stylesheet" href="style.css"></head>
<body><?php include __DIR__ . '/topnav.php'; ?>
<main class="container"><h1>Search</h1>
<form method="get" class="card auth-form"><label for="q">Search in title/content</label><input id="q" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Type keyword..."><button type="submit">Search</button></form>
<?php if ($q !== ''): ?>
<div class="card"><p>Results for: <strong><?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?></strong></p>
<?php if ($pastes === []): ?><p>No results found.</p><?php else: ?><table class="recent-table"><thead><tr><th>Title</th><th>Syntax</th><th>Views</th><th>Created</th></tr></thead><tbody>
<?php foreach ($pastes as $paste): ?><tr><td><a href="paste.php?s=<?= urlencode((string)$paste['slug']) ?>"><?= htmlspecialchars((string)$paste['title'], ENT_QUOTES, 'UTF-8') ?></a></td><td><?= htmlspecialchars((string)$paste['syntax'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int)$paste['view_count'] ?></td><td><?= htmlspecialchars(humanTime((string)$paste['created_at']), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
<div class="pagination"><?php for ($i=1; $i <= $totalPages; $i++): ?><a class="page-btn <?= $i === $page ? 'active' : '' ?>" href="search.php?q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a><?php endfor; ?></div>
<?php endif; ?>
</main><footer class="site-footer"><p>© <?= date('Y') ?> DarkPaste</p></footer></body></html>
