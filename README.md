# SEDCO Training Management System

The repository contains two delivery modes:

- **GitHub Pages preview**: `.html` pages use browser storage so the UI can be previewed without PHP.
- **PHP/MySQL application**: `.php` pages use the database schema in `SQL.sql`.

## Shared front-end

- `sedco-shell.js` — one navbar/sidebar implementation for all application pages.
- `sedco-shell.css` — navbar/sidebar layout and responsive behaviour.
- `sedco-saas.css` — shared SaaS visual system and page component styles.
- `sedco-submission.js` — static GitHub Pages form preview only.
- `application-status.js` — Application Status rendering for both preview and PHP data.

## PHP back-end

- `includes/db.php` — single database connection.
- `includes/auth.php` — session/auth helpers.
- `submit_application.php` — saves BPL, PKK and TEA forms.
- `SQL.sql` — canonical MySQL schema.

### Database environment variables

Set these on the PHP host for production:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

Local XAMPP defaults are used only when the variables are not set.

## Important

GitHub Pages cannot execute PHP. Use the `.html` pages only for preview. Deploy the PHP files to a PHP/MySQL host for the real application.
