# Dev Reset Toolkit

Dev Reset Toolkit is a WordPress admin plugin that provides safe, developer-oriented reset tools inspired by WP Reset.

## Installation

1. Create a folder named `dev-reset-toolkit` and place these files inside it:
   - `dev-reset-toolkit.php`
   - `includes/`
   - `assets/`
   - `README.md` (optional)
2. Zip the folder (the plugin main file must be at the top level inside that folder):
   - `zip -r dev-reset-toolkit.zip dev-reset-toolkit/`
3. Upload from **WordPress Admin → Plugins → Add New → Upload Plugin**.
4. Activate **Dev Reset Toolkit** and open it in the admin menu.

## Dangerous operations notes

- Always back up your files and database before using any reset option.
- **Dry-run mode** is available and should be used first.
- **Nuclear Reset** can delete uploaded files and users (except current admin).
- Enabling "include custom plugin tables" can permanently drop plugin-created database tables.
- The plugin intentionally protects critical options like `siteurl`, `home`, `admin_email`, and `blogname`.

## Feature summary

- Reset tab with a comparison table and guarded confirmation flow.
- Reset types: Options Reset, Site Reset, Nuclear Reset.
- Snapshot + reactivation workflows for themes and plugins.
- Logs tab that tracks status, user, reset type, and errors.
- Security controls: nonces, capability checks, admin-only execution.
