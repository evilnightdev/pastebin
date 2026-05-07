<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
ensureSessionStarted();
if (currentUser() !== null) { header('Location: profile.php'); exit; }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim((string)($_POST['username'] ?? '')));
    $password = trim((string)($_POST['password'] ?? ''));
    try {
        $pdo = getPdo();
        $stmt = $pdo->prepare('SELECT id, username, password_hash, is_admin, is_banned FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, (string)$row['password_hash'])) {
            $errors[] = 'Invalid username or password.';
        } elseif ((int)$row['is_banned'] === 1) {
            $errors[] = 'This account is banned.';
        } else {
            $_SESSION['user'] = ['id' => (int)$row['id'], 'username' => (string)$row['username'], 'is_admin' => (int)$row['is_admin']];
            header('Location: profile.php');
            exit;
        }
    } catch (Throwable $e) { $errors[] = 'Login failed.'; }
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login</title><link rel="stylesheet" href="style.css"></head>
<body><?php $user = currentUser(); include __DIR__ . '/topnav.php'; ?>
<main class="container"><h1>Login</h1><?php if ($errors !== []): ?><div class="alert error"><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="card auth-form"><label>Username</label><input name="username" required><label>Password</label><input type="password" name="password" required><button type="submit">Login</button></form></main>
<footer class="site-footer"><p>© <?= date('Y') ?> DarkPaste</p></footer></body></html>
