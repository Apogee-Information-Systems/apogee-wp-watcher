# Contributing

- Target PHP 7.4+ and WordPress 5.8+.
- Prefix options, transients, and AJAX actions with `apogee_wp_watcher_`.
- Escape all output; capability-check `manage_options` for admin actions; use nonces on AJAX.
- Do not contact Apogee Watcher until the site admin clicks **Connect**.
- No Google PSI keys in the plugin.
- Conventional commits preferred (`feat`, `fix`, `docs`).
