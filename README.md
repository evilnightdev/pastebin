# Dark Pastebin (PHP + MySQL)

## Setup

1. Import schema:
   ```bash
   mysql -u root -p < schema.sql
   ```
2. Copy config and edit DB credentials:
   ```bash
   cp config.sample.php config.php
   ```
3. Run local PHP server:
   ```bash
   php -S localhost:8080
   ```
4. Open:
   - `http://localhost:8080/index.php`

## Features

- User system: register, login, logout, and profile page.
- Private/public pastes with optional expiration.
- Private pastes protected by password hash.
- Dark modern UI, centered English menu, sticky footer, and small social icons.
- Paste creator layout: left options + fixed-size editor on right.

- After creating a paste, user is redirected directly to the paste page.
- Paste links are random slugs (not sequential IDs).
- Recent Pastes page with pagination (20 pastes per page).
- Paste view page now has a two-column layout with metadata and action buttons (Copy URL, View Raw, Copy Text, Download).
- Recent table columns are Title, Syntax, Views, Created and timestamps are shown in human-readable relative format.
- Authentication now uses username + password (no email), and header has an Account dropdown menu.
- Added Top Pastes page (sorted by view count) and Search page.
- Settings page supports avatar upload; paste page shows enhanced author panel with avatar and two-column action buttons.
- Paste creation now validates session user id against DB before insert to avoid FK errors from stale sessions.
- Profile page redesigned with hero info, statistics, contacts, and recent/top user pastes; contacts editable in settings.
- Admin panel with Overview, Pastes, and Users tabs; first registered user becomes admin by default.
- Admin Overview includes last-5-month view totals; Pastes/Users tabs include search plus management actions.
- Banned users cannot create pastes; deleted-user sessions are cleared on refresh; admin Ads tab manages GIF ads per page above page titles.
