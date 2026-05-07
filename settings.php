<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
requireLogin();
$user = currentUser();
$errors = [];
$success = null;
$pdo = getPdo();

$profile = $pdo->prepare('SELECT username, avatar_path, website_url, telegram_id, discord_id FROM users WHERE id = :id LIMIT 1');
$profile->execute([':id' => (int)$user['id']]);
$profileData = $profile->fetch() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $websiteUrl = trim((string)($_POST['website_url'] ?? ''));
    $telegramId = trim((string)($_POST['telegram_id'] ?? ''));
    $discordId = trim((string)($_POST['discord_id'] ?? ''));

    if ($websiteUrl !== '' && !filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'Website URL must be valid.';
    }

    if (isset($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['avatar'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Please select a valid image file.';
        } else {
            $tmp = (string)$file['tmp_name'];
            $mime = mime_content_type($tmp) ?: '';
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
                $errors[] = 'Allowed avatar formats: PNG, JPG, WEBP.';
            } else {
                $ext = $allowed[$mime];
                $name = 'u' . (int)$user['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $rel = 'uploads/avatars/' . $name;
                $abs = __DIR__ . '/' . $rel;
                if (!move_uploaded_file($tmp, $abs)) {
                    $errors[] = 'Could not save avatar file.';
                } else {
                    $profileData['avatar_path'] = $rel;
                    $_SESSION['user']['avatar_path'] = $rel;
                }
            }
        }
    }

    if ($errors === []) {
        $pdo->prepare('UPDATE users SET avatar_path = :avatar, website_url = :website_url, telegram_id = :telegram_id, discord_id = :discord_id WHERE id = :id')->execute([
            ':avatar' => $profileData['avatar_path'] ?? null,
            ':website_url' => $websiteUrl !== '' ? $websiteUrl : null,
            ':telegram_id' => $telegramId !== '' ? $telegramId : null,
            ':discord_id' => $discordId !== '' ? $discordId : null,
            ':id' => (int)$user['id'],
        ]);
        $success = 'Settings saved successfully.';

        $profile->execute([':id' => (int)$user['id']]);
        $profileData = $profile->fetch() ?: [];
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Settings</title><link rel="stylesheet" href="style.css"></head>
<body><?php include __DIR__ . '/topnav.php'; ?>
<main class="container"><h1>Account Settings</h1>
<?php if ($errors !== []): ?><div class="alert error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($success !== null): ?><div class="alert success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card"><p>Welcome, <?= htmlspecialchars((string)$user['username'], ENT_QUOTES, 'UTF-8') ?>.</p>
<form method="post" enctype="multipart/form-data" class="auth-form">
<label for="avatar">Avatar (optional)</label><input type="file" id="avatar" name="avatar" accept="image/png,image/jpeg,image/webp">
<label for="website_url">Website URL</label><input id="website_url" name="website_url" value="<?= htmlspecialchars((string)($profileData['website_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="https://example.com">
<label for="telegram_id">Telegram ID</label><input id="telegram_id" name="telegram_id" value="<?= htmlspecialchars((string)($profileData['telegram_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="@username">
<label for="discord_id">Discord ID</label><input id="discord_id" name="discord_id" value="<?= htmlspecialchars((string)($profileData['discord_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="user#1234">
<button type="submit">Save Settings</button></form></div>
</main>
<footer class="site-footer"><p>© <?= date('Y') ?> DarkPaste</p></footer></body></html>
