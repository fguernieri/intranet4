# Copilot Instructions for intranet4

## Big Picture Architecture
- The main system is a PHP intranet with modular structure under `modules/`, each module handling a business domain (e.g., `ficha_tecnica`, `acompanhamento_financeiro`, etc.).
- There is a secondary app in `php-mysql-app/` that follows strict MVC (see its README for details).
- Data flows via MySQL, with config in `config/db_config.php` and module-specific configs in `modules/*/db_config.php`.
- UI is built with PHP, Tailwind CSS, and DataTables JS. Responsiveness and accessibility are guided by `config/Front‑end Style Guide.md` and `UX_guidelines.md`.

## Developer Workflows
- No build step for main PHP app; changes are live.
- For `php-mysql-app/`, run `composer install` for dependencies, edit config, and access via browser.
- Debugging: Enable error reporting in PHP files (`ini_set('display_errors', 1); error_reporting(E_ALL);`).
- Database changes: Most modules do not require DB migrations; check module README for exceptions.

## Project-Specific Conventions
- All UI components use Tailwind classes for color, spacing, and responsiveness. See `assets/css/style.css` for overrides.
- DataTables controls (pagination, info, filter) are styled for dark backgrounds; always set text color to white for contrast.
- Status indicators use color-coded backgrounds (green, red, yellow, cyan) per UX guide.
- Mobile: Use `md:hidden` and `hidden md:block` for responsive tables/cards.
- Print: Use `@media print` in CSS to hide controls and optimize for A4.

## Integration Points & Dependencies
- External JS/CSS via CDN (Tailwind, DataTables).
- PHP modules communicate via includes and shared config/database connection.
- No API layer; all logic is server-side PHP.
- For new modules, follow the pattern in `modules/ficha_tecnica/` (see its README for UI/UX standards).

## Examples
- Auditoria workflow: See `modules/ficha_tecnica/auditoria.php` for status calculation, UI, and DataTables integration.
- Style conventions: See `assets/css/style.css` for DataTables and Tailwind overrides.
- UX standards: See `config/Front‑end Style Guide.md` and `UX_guidelines.md` for color, spacing, and accessibility rules.

---

For unclear workflows or missing conventions, check module-specific README files or ask for clarification.
