# Dev Reset Toolkit

Dev Reset Toolkit is a WordPress admin plugin that provides safe, developer-oriented reset tools inspired by WP Reset.

## Installation

1. Copy the `dev-reset-toolkit` folder into your WordPress `wp-content/plugins` directory.
2. Or zip the folder:
   - From a terminal, run `zip -r dev-reset-toolkit.zip dev-reset-toolkit/`.
   - Upload the zip from **WordPress Admin → Plugins → Add New → Upload Plugin**.
3. Activate **Dev Reset Toolkit**.
4. Open **Dev Reset Toolkit** in the admin menu.

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
