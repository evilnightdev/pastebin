<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

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
if ($slug === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid paste link.';
    exit;
}

try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->prepare('SELECT content, is_public, private_password_hash, expires_at FROM pastes WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $paste = $stmt->fetch();

    if (!$paste) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Paste not found.';
        exit;
    }

    if ($paste['expires_at'] !== null && strtotime((string)$paste['expires_at']) <= time()) {
        http_response_code(410);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'This paste has expired.';
        exit;
    }

    if ((int)$paste['is_public'] === 0) {
        $password = trim((string)($_GET['password'] ?? ''));
        if ($password === '' || !password_verify($password, (string)$paste['private_password_hash'])) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Private paste password required (?password=...)';
            exit;
        }
    }

    header('Content-Type: text/plain; charset=utf-8');
    echo (string)$paste['content'];
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Database error.';
}
