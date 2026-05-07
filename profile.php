<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
requireLogin();
$user = currentUser();
$pdo = getPdo();

$uStmt = $pdo->prepare('SELECT username, avatar_path, created_at, website_url, telegram_id, discord_id, is_banned FROM users WHERE id = :id LIMIT 1');
$uStmt->execute([':id' => (int)$user['id']]);
$u = $uStmt->fetch();

$statsStmt = $pdo->prepare('SELECT COUNT(*) AS total_pastes, COALESCE(SUM(view_count), 0) AS total_views, MAX(created_at) AS last_paste_at FROM pastes WHERE user_id = :id');
$statsStmt->execute([':id' => (int)$user['id']]);
$stats = $statsStmt->fetch() ?: ['total_pastes' => 0, 'total_views' => 0, 'last_paste_at' => null];

$recentStmt = $pdo->prepare('SELECT slug, title, created_at, view_count FROM pastes WHERE user_id = :id ORDER BY created_at DESC LIMIT 10');
$recentStmt->execute([':id' => (int)$user['id']]);
$recentPastes = $recentStmt->fetchAll();

$topStmt = $pdo->prepare('SELECT slug, title, created_at, view_count FROM pastes WHERE user_id = :id ORDER BY view_count DESC, created_at DESC LIMIT 10');
$topStmt->execute([':id' => (int)$user['id']]);
$topPastes = $topStmt->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Profile</title><link rel="stylesheet" href="style.css"></head>
<body><?php include __DIR__ . '/topnav.php'; ?>
<main class="container"><h1>User Profile</h1>

<div class="card user-profile-hero">
    <?php if (!empty($u['avatar_path'])): ?><img class="avatar hero-avatar" src="<?= htmlspecialchars((string)$u['avatar_path'], ENT_QUOTES, 'UTF-8') ?>" alt="avatar"><?php else: ?><div class="avatar avatar-fallback hero-avatar"><?= strtoupper(substr((string)$u['username'],0,1)) ?></div><?php endif; ?>
    <div><h2 class="profile-username <?= (int)$u['is_banned'] === 1 ? 'banned-name' : '' ?>"><?= htmlspecialchars((string)$u['username'], ENT_QUOTES, 'UTF-8') ?></h2><?php if ((int)$u['is_banned'] === 1): ?><span class="banned-badge">banned</span><?php endif; ?><p>Joined: <?= htmlspecialchars(humanTime((string)$u['created_at']), ENT_QUOTES, 'UTF-8') ?></p></div>
</div>

<div class="grid two-panel">
    <section class="card"><h3>Statistics</h3>
        <div class="meta-grid">
            <div class="meta-item"><span>Total pastes</span><strong><?= (int)$stats['total_pastes'] ?></strong></div>
            <div class="meta-item"><span>Total views</span><strong><?= (int)$stats['total_views'] ?></strong></div>
            <div class="meta-item"><span>Last paste</span><strong><?= htmlspecialchars($stats['last_paste_at'] ? humanTime((string)$stats['last_paste_at']) : 'Never', ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div class="meta-item"><span>User ID</span><strong><?= (int)$user['id'] ?></strong></div>
        </div>
    </section>
    <section class="card"><h3>Contacts</h3>
        <div class="meta-grid">
            <div class="meta-item"><span>Website</span><strong><?= htmlspecialchars((string)($u['website_url'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div class="meta-item"><span>Telegram</span><strong><?= htmlspecialchars((string)($u['telegram_id'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div class="meta-item"><span>Discord</span><strong><?= htmlspecialchars((string)($u['discord_id'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div class="meta-item"><span>Settings</span><strong><a href="settings.php">Edit profile</a></strong></div>
        </div>
    </section>
</div>

<div class="grid two-panel">
    <section class="card"><h3>Recent Pastes</h3>
        <table class="recent-table"><thead><tr><th>Title</th><th>Views</th><th>Created</th></tr></thead><tbody>
        <?php foreach ($recentPastes as $p): ?><tr><td><a href="paste.php?s=<?= urlencode((string)$p['slug']) ?>"><?= htmlspecialchars((string)$p['title'], ENT_QUOTES, 'UTF-8') ?></a></td><td><?= (int)$p['view_count'] ?></td><td><?= htmlspecialchars(humanTime((string)$p['created_at']), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?>
        </tbody></table>
    </section>
    <section class="card"><h3>Top Pastes</h3>
        <table class="recent-table"><thead><tr><th>Title</th><th>Views</th><th>Created</th></tr></thead><tbody>
        <?php foreach ($topPastes as $p): ?><tr><td><a href="paste.php?s=<?= urlencode((string)$p['slug']) ?>"><?= htmlspecialchars((string)$p['title'], ENT_QUOTES, 'UTF-8') ?></a></td><td><?= (int)$p['view_count'] ?></td><td><?= htmlspecialchars(humanTime((string)$p['created_at']), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?>
        </tbody></table>
    </section>
</div>

</main><footer class="site-footer"><p>© <?= date('Y') ?> DarkPaste</p></footer></body></html>
