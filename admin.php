<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
requireAdmin();
$user = currentUser();
$pdo = getPdo();

$tab = (string)($_GET['tab'] ?? 'overview');
$allowedTabs = ['overview', 'pastes', 'users', 'ads'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'overview';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete_paste') {
        $slug = trim((string)($_POST['slug'] ?? ''));
        if ($slug !== '') {
            $stmt = $pdo->prepare('DELETE FROM pastes WHERE slug = :slug');
            $stmt->execute([':slug' => $slug]);
        }
        header('Location: admin.php?tab=pastes');
        exit;
    }

    if ($action === 'ban_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0 && $userId !== (int)$user['id']) {
            $stmt = $pdo->prepare('UPDATE users SET is_banned = 1 WHERE id = :id');
            $stmt->execute([':id' => $userId]);
        }
        header('Location: admin.php?tab=users');
        exit;
    }

    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0 && $userId !== (int)$user['id']) {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute([':id' => $userId]);
        }
        header('Location: admin.php?tab=users');
        exit;
    }

    if ($action === 'create_ad') {
        $gifUrl = trim((string)($_POST['gif_url'] ?? ''));
        $targetUrl = trim((string)($_POST['target_url'] ?? ''));
        $displaySeconds = max(0, (int)($_POST['display_seconds'] ?? 0));
        $pageLocation = trim((string)($_POST['page_location'] ?? 'all'));
        $allowedPages = ['all', 'home', 'paste', 'recent', 'top', 'search', 'profile', 'settings', 'admin', 'login', 'register'];
        if ($gifUrl !== '' && $targetUrl !== '' && filter_var($gifUrl, FILTER_VALIDATE_URL) && filter_var($targetUrl, FILTER_VALIDATE_URL) && in_array($pageLocation, $allowedPages, true)) {
            $stmt = $pdo->prepare('INSERT INTO advertisements (gif_url, target_url, display_seconds, page_location, created_at) VALUES (:gif_url, :target_url, :display_seconds, :page_location, UTC_TIMESTAMP())');
            $stmt->execute([
                ':gif_url' => $gifUrl,
                ':target_url' => $targetUrl,
                ':display_seconds' => $displaySeconds,
                ':page_location' => $pageLocation,
            ]);
        }
        header('Location: admin.php?tab=ads');
        exit;
    }

    if ($action === 'delete_ad') {
        $adId = (int)($_POST['ad_id'] ?? 0);
        if ($adId > 0) {
            $stmt = $pdo->prepare('DELETE FROM advertisements WHERE id = :id');
            $stmt->execute([':id' => $adId]);
        }
        header('Location: admin.php?tab=ads');
        exit;
    }
}

$pasteSearch = trim((string)($_GET['paste_q'] ?? ''));
$userSearch = trim((string)($_GET['user_q'] ?? ''));

$stats = [
    'users' => 0,
    'pastes' => 0,
    'public_pastes' => 0,
    'private_pastes' => 0,
    'views' => 0,
];
$recentPastes = [];
$monthlyViews = [];
$pastes = [];
$users = [];
$ads = [];

if ($tab === 'overview') {
    $stats['users'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['pastes'] = (int)$pdo->query('SELECT COUNT(*) FROM pastes')->fetchColumn();
    $stats['public_pastes'] = (int)$pdo->query('SELECT COUNT(*) FROM pastes WHERE is_public = 1')->fetchColumn();
    $stats['private_pastes'] = (int)$pdo->query('SELECT COUNT(*) FROM pastes WHERE is_public = 0')->fetchColumn();
    $stats['views'] = (int)$pdo->query('SELECT COALESCE(SUM(view_count), 0) FROM pastes')->fetchColumn();

    $stmt = $pdo->query('SELECT p.slug, p.title, p.view_count, p.created_at, u.username FROM pastes p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.created_at DESC LIMIT 10');
    $recentPastes = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT DATE_FORMAT(viewed_at, '%Y-%m') AS month_key, COUNT(*) AS views FROM paste_views WHERE viewed_at >= DATE_FORMAT(DATE_SUB(UTC_DATE(), INTERVAL 4 MONTH), '%Y-%m-01') GROUP BY month_key ORDER BY month_key ASC");
    $viewRows = [];
    foreach ($stmt->fetchAll() as $row) {
        $viewRows[(string)$row['month_key']] = (int)$row['views'];
    }

    for ($i = 4; $i >= 0; $i--) {
        $monthKey = gmdate('Y-m', strtotime('-' . $i . ' months'));
        $monthlyViews[] = [
            'label' => gmdate('M Y', strtotime($monthKey . '-01')),
            'views' => $viewRows[$monthKey] ?? 0,
        ];
    }
}

if ($tab === 'pastes') {
    if ($pasteSearch !== '') {
        $stmt = $pdo->prepare("SELECT p.slug, p.title, p.syntax, p.is_public, p.view_count, p.created_at, u.username FROM pastes p LEFT JOIN users u ON u.id = p.user_id WHERE p.title LIKE :q OR p.slug LIKE :q OR u.username LIKE :q ORDER BY p.created_at DESC LIMIT 100");
        $stmt->execute([':q' => '%' . $pasteSearch . '%']);
    } else {
        $stmt = $pdo->query('SELECT p.slug, p.title, p.syntax, p.is_public, p.view_count, p.created_at, u.username FROM pastes p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.created_at DESC LIMIT 100');
    }
    $pastes = $stmt->fetchAll();
}

if ($tab === 'users') {
    if ($userSearch !== '') {
        $stmt = $pdo->prepare("SELECT u.id, u.username, u.is_admin, u.is_banned, u.created_at, COUNT(p.id) AS paste_count, COALESCE(SUM(p.view_count), 0) AS total_views FROM users u LEFT JOIN pastes p ON p.user_id = u.id WHERE u.username LIKE :q GROUP BY u.id, u.username, u.is_admin, u.is_banned, u.created_at ORDER BY u.created_at DESC LIMIT 100");
        $stmt->execute([':q' => '%' . $userSearch . '%']);
    } else {
        $stmt = $pdo->query('SELECT u.id, u.username, u.is_admin, u.is_banned, u.created_at, COUNT(p.id) AS paste_count, COALESCE(SUM(p.view_count), 0) AS total_views FROM users u LEFT JOIN pastes p ON p.user_id = u.id GROUP BY u.id, u.username, u.is_admin, u.is_banned, u.created_at ORDER BY u.created_at DESC LIMIT 100');
    }
    $users = $stmt->fetchAll();
}

if ($tab === 'ads') {
    $stmt = $pdo->query('SELECT id, gif_url, target_url, display_seconds, page_location, is_active, created_at FROM advertisements ORDER BY created_at DESC LIMIT 100');
    $ads = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include __DIR__ . '/topnav.php'; ?>
<main class="container">
    <h1>Admin Panel</h1>

    <div class="admin-tabs">
        <a class="admin-tab <?= $tab === 'overview' ? 'active' : '' ?>" href="admin.php?tab=overview">Overview</a>
        <a class="admin-tab <?= $tab === 'pastes' ? 'active' : '' ?>" href="admin.php?tab=pastes">Pastes</a>
        <a class="admin-tab <?= $tab === 'users' ? 'active' : '' ?>" href="admin.php?tab=users">Users</a>
        <a class="admin-tab <?= $tab === 'ads' ? 'active' : '' ?>" href="admin.php?tab=ads">Ads</a>
    </div>

    <?php if ($tab === 'overview'): ?>
        <section class="card">
            <h2>Overview</h2>
            <div class="meta-grid admin-stat-grid">
                <div class="meta-item"><span>Users</span><strong><?= $stats['users'] ?></strong></div>
                <div class="meta-item"><span>Total pastes</span><strong><?= $stats['pastes'] ?></strong></div>
                <div class="meta-item"><span>Public pastes</span><strong><?= $stats['public_pastes'] ?></strong></div>
                <div class="meta-item"><span>Private pastes</span><strong><?= $stats['private_pastes'] ?></strong></div>
                <div class="meta-item"><span>Total views</span><strong><?= $stats['views'] ?></strong></div>
            </div>
        </section>

        <section class="card admin-section">
            <h2>Views by Month (Last 5 Months)</h2>
            <div class="month-grid">
                <?php foreach ($monthlyViews as $month): ?>
                    <div class="month-card">
                        <span><?= htmlspecialchars($month['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= (int)$month['views'] ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card admin-section">
            <h2>Latest Pastes</h2>
            <table class="recent-table">
                <thead><tr><th>Title</th><th>Author</th><th>Views</th><th>Created</th></tr></thead>
                <tbody>
                <?php foreach ($recentPastes as $paste): ?>
                    <tr>
                        <td><a href="paste.php?s=<?= urlencode((string)$paste['slug']) ?>"><?= htmlspecialchars((string)$paste['title'], ENT_QUOTES, 'UTF-8') ?></a></td>
                        <td><?= htmlspecialchars((string)($paste['username'] ?? 'Guest'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$paste['view_count'] ?></td>
                        <td><?= htmlspecialchars(humanTime((string)$paste['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'pastes'): ?>
        <section class="card">
            <h2>Pastes</h2>
            <form class="admin-search" method="get">
                <input type="hidden" name="tab" value="pastes">
                <input name="paste_q" value="<?= htmlspecialchars($pasteSearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search title, slug, or author...">
                <button type="submit">Search</button>
            </form>
            <table class="recent-table">
                <thead><tr><th>Title</th><th>Author</th><th>Syntax</th><th>Visibility</th><th>Views</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($pastes as $paste): ?>
                    <tr>
                        <td><a href="paste.php?s=<?= urlencode((string)$paste['slug']) ?>"><?= htmlspecialchars((string)$paste['title'], ENT_QUOTES, 'UTF-8') ?></a></td>
                        <td><?= htmlspecialchars((string)($paste['username'] ?? 'Guest'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)$paste['syntax'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$paste['is_public'] === 1 ? 'Public' : 'Private' ?></td>
                        <td><?= (int)$paste['view_count'] ?></td>
                        <td><?= htmlspecialchars(humanTime((string)$paste['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="admin-actions">
                            <a class="mini-action" href="paste.php?s=<?= urlencode((string)$paste['slug']) ?>">View</a>
                            <form method="post" onsubmit="return confirm('Delete this paste?');">
                                <input type="hidden" name="action" value="delete_paste">
                                <input type="hidden" name="slug" value="<?= htmlspecialchars((string)$paste['slug'], ENT_QUOTES, 'UTF-8') ?>">
                                <button class="mini-action danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'users'): ?>
        <section class="card">
            <h2>Users</h2>
            <form class="admin-search" method="get">
                <input type="hidden" name="tab" value="users">
                <input name="user_q" value="<?= htmlspecialchars($userSearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search username...">
                <button type="submit">Search</button>
            </form>
            <table class="recent-table">
                <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Status</th><th>Pastes</th><th>Views</th><th>Joined</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= htmlspecialchars((string)$row['username'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)$row['is_admin'] === 1 ? 'Admin' : 'User' ?></td>
                        <td><?= (int)$row['is_banned'] === 1 ? 'Banned' : 'Active' ?></td>
                        <td><?= (int)$row['paste_count'] ?></td>
                        <td><?= (int)$row['total_views'] ?></td>
                        <td><?= htmlspecialchars(humanTime((string)$row['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="admin-actions">
                            <form method="post" onsubmit="return confirm('Ban this user?');">
                                <input type="hidden" name="action" value="ban_user">
                                <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                <button class="mini-action" type="submit" <?= ((int)$row['id'] === (int)$user['id'] || (int)$row['is_banned'] === 1) ? 'disabled' : '' ?>>Ban</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Delete this user?');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                <button class="mini-action danger" type="submit" <?= (int)$row['id'] === (int)$user['id'] ? 'disabled' : '' ?>>Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'ads'): ?>
        <section class="card">
            <h2>Advertisements</h2>
            <form class="admin-ad-form" method="post">
                <input type="hidden" name="action" value="create_ad">
                <label>GIF URL</label>
                <input name="gif_url" placeholder="https://example.com/ad.gif" required>
                <label>Ad Link</label>
                <input name="target_url" placeholder="https://example.com" required>
                <label>Ad Time (seconds, 0 = always visible)</label>
                <input type="number" name="display_seconds" min="0" value="0" required>
                <label>Show On Page</label>
                <select name="page_location">
                    <option value="all">All pages</option>
                    <option value="home">Home</option>
                    <option value="paste">Paste</option>
                    <option value="recent">Recent Pastes</option>
                    <option value="top">Top Pastes</option>
                    <option value="search">Search</option>
                    <option value="profile">Profile</option>
                    <option value="settings">Settings</option>
                    <option value="admin">Admin</option>
                    <option value="login">Login</option>
                    <option value="register">Register</option>
                </select>
                <button type="submit">Add Advertisement</button>
            </form>
        </section>

        <section class="card admin-section">
            <h2>Advertisement List</h2>
            <table class="recent-table">
                <thead><tr><th>Preview</th><th>GIF URL</th><th>Ad Link</th><th>Time</th><th>Page</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($ads as $ad): ?>
                    <tr>
                        <td><img class="admin-ad-preview" src="<?= htmlspecialchars((string)$ad['gif_url'], ENT_QUOTES, 'UTF-8') ?>" alt="ad"></td>
                        <td><?= htmlspecialchars((string)$ad['gif_url'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><a href="<?= htmlspecialchars((string)$ad['target_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Open</a></td>
                        <td><?= (int)$ad['display_seconds'] ?> sec</td>
                        <td><?= htmlspecialchars((string)$ad['page_location'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(humanTime((string)$ad['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="admin-actions">
                            <form method="post" onsubmit="return confirm('Delete this ad?');">
                                <input type="hidden" name="action" value="delete_ad">
                                <input type="hidden" name="ad_id" value="<?= (int)$ad['id'] ?>">
                                <button class="mini-action danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</main>
<footer class="site-footer"><p>© <?= date('Y') ?> DarkPaste</p></footer>
</body>
</html>
