<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
ensureSessionStarted();

if (currentUser() !== null) {
    header('Location: profile.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim((string)($_POST['username'] ?? '')));
    $password = trim((string)($_POST['password'] ?? ''));

    if ($username === '' || !preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3-30 chars (a-z, 0-9, _).';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($errors === []) {
        try {
            $pdo = getPdo();
            $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $isAdmin = $userCount === 0 ? 1 : 0;

            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, is_admin, created_at) VALUES (:username, :password_hash, :is_admin, UTC_TIMESTAMP())');
            $stmt->execute([
                ':username' => $username,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':is_admin' => $isAdmin,
            ]);

            $_SESSION['user'] = [
                'id' => (int)$pdo->lastInsertId(),
                'username' => $username,
                'is_admin' => $isAdmin,
            ];

            header('Location: profile.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Registration failed. Username may already exist.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Register</title><link rel="stylesheet" href="style.css"></head>
<body>
<?php $user = currentUser(); include __DIR__ . '/topnav.php'; ?>
<main class="container"><h1>Create Account</h1>
<?php if ($errors !== []): ?><div class="alert error"><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="card auth-form"><label>Username</label><input name="username" required><label>Password</label><input type="password" name="password" required><button type="submit">Register</button></form>
</main><footer class="site-footer"><p>© <?= date('Y') ?> DarkPaste</p></footer></body></html>
