<?php

declare(strict_types=1);

function getConfig(): array
{
    $configFile = __DIR__ . '/config.php';
    if (!file_exists($configFile)) {
        $configFile = __DIR__ . '/config.sample.php';
    }

    return require $configFile;
}

function getPdo(): PDO
{
    $config = getConfig();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['db_host'],
        $config['db_port'],
        $config['db_name'],
        $config['db_charset']
    );

    return new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function ensureSessionStarted(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function currentUser(): ?array
{
    ensureSessionStarted();
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
        return null;
    }

    try {
        $pdo = getPdo();
        $stmt = $pdo->prepare('SELECT id, username, is_admin, is_banned, avatar_path FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$_SESSION['user']['id']]);
        $row = $stmt->fetch();
        if ($row === false) {
            unset($_SESSION['user']);
            return null;
        }

        $_SESSION['user'] = [
            'id' => (int)$row['id'],
            'username' => (string)$row['username'],
            'is_admin' => (int)$row['is_admin'],
            'is_banned' => (int)$row['is_banned'],
            'avatar_path' => $row['avatar_path'],
        ];
    } catch (Throwable $e) {
        unset($_SESSION['user']);
        return null;
    }

    return $_SESSION['user'];
}

function requireLogin(): void
{
    if (currentUser() === null) {
        header('Location: login.php');
        exit;
    }
}


function humanTime(?string $datetime): string
{
    if ($datetime === null || trim($datetime) === '') {
        return 'Never';
    }

    $ts = strtotime($datetime . ' UTC');
    if ($ts === false) {
        return $datetime;
    }

    $now = time();
    $diff = $now - $ts;

    if ($diff < 0) {
        $diff = abs($diff);
    }

    if ($diff < 60) {
        return $diff . ' sec';
    }

    if ($diff < 3600) {
        return (int)floor($diff / 60) . ' minutes ago';
    }

    if ($diff < 86400) {
        return (int)floor($diff / 3600) . ' hours ago';
    }

    if ($diff < 2592000) {
        return (int)floor($diff / 86400) . ' days ago';
    }

    if ($diff < 31536000) {
        return (int)floor($diff / 2592000) . ' month ago';
    }

    return gmdate('Y-m-d H:i:s', $ts);
}


function isAdmin(): bool
{
    $user = currentUser();
    if ($user === null || !isset($user['id'])) {
        return false;
    }

    try {
        $pdo = getPdo();
        $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$user['id']]);
        $row = $stmt->fetch();
        $isAdmin = $row !== false && (int)$row['is_admin'] === 1;
        $_SESSION['user']['is_admin'] = $isAdmin ? 1 : 0;

        return $isAdmin;
    } catch (Throwable $e) {
        return false;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}


function pageKey(): string
{
    $name = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    return match ($name) {
        'index.php' => 'home',
        'paste.php' => 'paste',
        'recent.php' => 'recent',
        'top.php' => 'top',
        'search.php' => 'search',
        'profile.php' => 'profile',
        'settings.php' => 'settings',
        'admin.php' => 'admin',
        'login.php' => 'login',
        'register.php' => 'register',
        default => 'all',
    };
}

function renderPageAd(): void
{
    try {
        $pdo = getPdo();
        $page = pageKey();
        $stmt = $pdo->prepare(
            'SELECT gif_url, target_url, display_seconds
             FROM advertisements
             WHERE is_active = 1 AND (page_location = :page OR page_location = \'all\')
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute([':page' => $page]);
        $ad = $stmt->fetch();
        if (!$ad) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }

    $seconds = max(0, (int)$ad['display_seconds']);
    ?>
    <div class="ad-slot" data-hide-after="<?= $seconds ?>">
        <a href="<?= htmlspecialchars((string)$ad['target_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
            <img src="<?= htmlspecialchars((string)$ad['gif_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Advertisement">
        </a>
    </div>
    <?php if ($seconds > 0): ?>
        <script>
        (() => {
            const ads = document.querySelectorAll('.ad-slot[data-hide-after="<?= $seconds ?>"]');
            ads.forEach((ad) => setTimeout(() => ad.remove(), <?= $seconds * 1000 ?>));
        })();
        </script>
    <?php endif; ?>
    <?php
}
