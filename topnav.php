<header class="site-header">
    <nav class="main-nav" aria-label="Main menu">
        <a href="index.php">Home</a>
        <a href="recent.php">Recent Pastes</a>
        <a href="top.php">Top Pastes</a>
        <a href="search.php">Search</a>
        <div class="account-menu" id="accountMenu">
            <button type="button" class="account-btn" id="accountBtn" aria-haspopup="true" aria-expanded="false">Account ▾</button>
            <div class="account-dropdown" id="accountDropdown">
                <?php if (($user ?? null) === null): ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                <?php else: ?>
                    <a href="profile.php">Profile</a>
                    <?php if (isAdmin()): ?>
                        <a href="admin.php">Admin Panel</a>
                    <?php endif; ?>
                    <a href="settings.php">Settings</a>
                    <a href="logout.php">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>
<?php renderPageAd(); ?>
<script>
(() => {
  const menu = document.getElementById('accountMenu');
  const btn = document.getElementById('accountBtn');
  if (!menu || !btn) return;

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const open = menu.classList.toggle('open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  document.addEventListener('click', (e) => {
    if (!menu.contains(e.target)) {
      menu.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
    }
  });
})();
</script>
