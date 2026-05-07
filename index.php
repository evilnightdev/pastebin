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

$errors = [];

function expirationDate(string $option): ?string
{
    $map = [
        '10m' => '+10 minutes',
        '1h' => '+1 hour',
        '1d' => '+1 day',
        '1w' => '+1 week',
        'never' => null,
    ];

    if (!array_key_exists($option, $map) || $map[$option] === null) {
        return null;
    }

    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify($map[$option])
        ->format('Y-m-d H:i:s');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
    $syntax = trim((string)($_POST['syntax'] ?? 'text'));
    $expireOption = trim((string)($_POST['expire'] ?? 'never'));
    $visibility = trim((string)($_POST['visibility'] ?? 'public'));
    $content = trim((string)($_POST['content'] ?? ''));
    $privatePassword = trim((string)($_POST['private_password'] ?? ''));

    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    if ($content === '') {
        $errors[] = 'Paste content is required.';
    }

    $allowedSyntaxes = ['text', 'php', 'html', 'css', 'javascript', 'json', 'sql', 'bash'];
    if (!in_array($syntax, $allowedSyntaxes, true)) {
        $errors[] = 'Invalid syntax selection.';
    }

    if (!in_array($expireOption, ['10m', '1h', '1d', '1w', 'never'], true)) {
        $errors[] = 'Invalid expiration selection.';
    }

    if (!in_array($visibility, ['public', 'private'], true)) {
        $errors[] = 'Invalid visibility selection.';
    }

    if ($visibility === 'private' && $privatePassword === '') {
        $errors[] = 'Password is required for private pastes.';
    }

    if ($user !== null && (int)($user['is_banned'] ?? 0) === 1) {
        $errors[] = 'Your account is banned and cannot create new pastes.';
    }

    if ($errors === []) {
        try {
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $userId = null;
            if (isset($user['id'])) {
                $checkUser = $pdo->prepare('SELECT id, is_banned FROM users WHERE id = :id LIMIT 1');
                $checkUser->execute([':id' => (int)$user['id']]);
                $foundUser = $checkUser->fetch();
                if ($foundUser && (int)$foundUser['is_banned'] === 0) {
                    $userId = (int)$foundUser['id'];
                } elseif ($foundUser && (int)$foundUser['is_banned'] === 1) {
                    $errors[] = 'Your account is banned and cannot create new pastes.';
                } else {
                    unset($_SESSION['user']);
                }
            }

            if ($errors !== []) {
                throw new RuntimeException(implode(' ', $errors));
            }

            $isPublic = $visibility === 'public' ? 1 : 0;
            $expiresAt = expirationDate($expireOption);
            $privatePasswordHash = $isPublic ? null : password_hash($privatePassword, PASSWORD_DEFAULT);

            $slug = bin2hex(random_bytes(6));
            while (true) {
                $check = $pdo->prepare('SELECT id FROM pastes WHERE slug = :slug LIMIT 1');
                $check->execute([':slug' => $slug]);
                if (!$check->fetch()) {
                    break;
                }
                $slug = bin2hex(random_bytes(6));
            }

            $stmt = $pdo->prepare(
                'INSERT INTO pastes (user_id, slug, title, syntax, content, expires_at, is_public, private_password_hash, created_at)
                 VALUES (:user_id, :slug, :title, :syntax, :content, :expires_at, :is_public, :private_password_hash, UTC_TIMESTAMP())'
            );

            $stmt->execute([
                ':user_id' => $userId,
                ':slug' => $slug,
                ':title' => $title,
                ':syntax' => $syntax,
                ':content' => $content,
                ':expires_at' => $expiresAt,
                ':is_public' => $isPublic,
                ':private_password_hash' => $privatePasswordHash,
            ]);

            header('Location: paste.php?s=' . urlencode($slug));
            exit;
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            $errors[] = 'Database error: ' . $exception->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dark Pastebin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include __DIR__ . '/topnav.php'; ?>

<main class="container">
    <h1>Create New Paste</h1>

    <?php if ($errors !== []): ?>
        <div class="alert error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    
    <form method="post" class="paste-form">
        <div class="grid">
            <section class="left-column card">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" placeholder="Paste title" required>

                <label for="syntax">Syntax</label>
                <select id="syntax" name="syntax">
                    <option value="text">Text</option>
                    <option value="php">PHP</option>
                    <option value="html">HTML</option>
                    <option value="css">CSS</option>
                    <option value="javascript">JavaScript</option>
                    <option value="json">JSON</option>
                    <option value="sql">SQL</option>
                    <option value="bash">Bash</option>
                </select>

                <label for="expire">Expiration</label>
                <select id="expire" name="expire">
                    <option value="10m">10 minutes</option>
                    <option value="1h">1 hour</option>
                    <option value="1d">1 day</option>
                    <option value="1w">1 week</option>
                    <option value="never" selected>Never</option>
                </select>

                <label for="visibility">Visibility</label>
                <select id="visibility" name="visibility">
                    <option value="public" selected>Public</option>
                    <option value="private">Private</option>
                </select>

                <div id="passwordWrap" class="hidden">
                    <label for="private_password">Private Password</label>
                    <input type="password" id="private_password" name="private_password" placeholder="Password for private paste">
                </div>
            </section>

            <section class="right-column card">
                <label for="content">Paste content</label>
                <textarea id="content" name="content" placeholder="Write or paste text here..." required></textarea>
            </section>
        </div>

        <div class="submit-wrap">
            <button type="submit">Create Paste</button>
        </div>
    </form>
</main>

<footer class="site-footer">
    <p>© <?= date('Y') ?> DarkPaste — Simple and secure snippet sharing.</p>
    <div class="social-links" aria-label="Social links">
        <a href="https://discord.com" target="_blank" rel="noopener noreferrer" aria-label="Discord">
            <svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path d="M20.3 4.4A16.6 16.6 0 0 0 16.2 3l-.2.4a15 15 0 0 1 3.8 1.4A12.3 12.3 0 0 0 12 2a12.3 12.3 0 0 0-7.8 2.8A15 15 0 0 1 8 3.4L7.8 3a16.6 16.6 0 0 0-4.1 1.4A17.3 17.3 0 0 0 2 16.2a16.8 16.8 0 0 0 5 2.5l.8-1.3a10.8 10.8 0 0 1-1.7-.8l.4-.3c3.3 1.5 7.6 1.5 11 0l.4.3c-.5.3-1.1.6-1.7.8l.8 1.3a16.8 16.8 0 0 0 5-2.5 17.3 17.3 0 0 0-1.7-11.8zM9.2 13.8c-1 0-1.7-.9-1.7-2s.8-2 1.7-2 1.7.9 1.7 2-.8 2-1.7 2zm5.6 0c-1 0-1.7-.9-1.7-2s.8-2 1.7-2 1.7.9 1.7 2-.8 2-1.7 2z"/></svg>
        </a>
        <a href="https://t.me" target="_blank" rel="noopener noreferrer" aria-label="Telegram">
            <svg viewBox="0 0 24 24" role="img" aria-hidden="true"><path d="M21.5 4.5a1 1 0 0 0-1-.1L3.2 11a1 1 0 0 0 .1 1.9l4.3 1.4 1.6 4.9a1 1 0 0 0 1.8.2l2.4-3.3 4.2 3.1a1 1 0 0 0 1.6-.6l2.4-13.1a1 1 0 0 0-.1-1zM10.4 14.7l-.7 2.2-.8-2.5 8.9-7.4-7.4 7.7z"/></svg>
        </a>
    </div>
</footer>

<script>
    const visibilitySelect = document.getElementById('visibility');
    const passwordWrap = document.getElementById('passwordWrap');
    const passwordInput = document.getElementById('private_password');

    function togglePasswordField() {
        const isPrivate = visibilitySelect.value === 'private';
        passwordWrap.classList.toggle('hidden', !isPrivate);
        passwordInput.required = isPrivate;
        if (!isPrivate) {
            passwordInput.value = '';
        }
    }

    visibilitySelect.addEventListener('change', togglePasswordField);
    togglePasswordField();
</script>
</body>
</html>
