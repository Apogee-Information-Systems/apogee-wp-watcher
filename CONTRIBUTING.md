# Contributing

- Target PHP 7.4+ and WordPress 5.8+.
- Prefix options, transients, and AJAX actions with `apogee_wp_watcher_`.
- Escape all output; capability-check `manage_options` for admin actions; use nonces on AJAX.
- Do not contact Apogee Watcher until the site admin clicks **Connect**.
- No Google PSI keys in the plugin.
- Conventional commits preferred (`feat`, `fix`, `docs`).
- **i18n:** wrap user-facing strings with the `apogee-wp-watcher` text domain. Regenerate the catalogue after string changes:

```bash
xgettext --language=PHP --from-code=UTF-8 \
  --keyword=__ --keyword=_e --keyword=_n:1,2 --keyword=_x:1,2c \
  --keyword=esc_attr__ --keyword=esc_html__ --keyword=esc_attr_e --keyword=esc_html_e \
  --add-comments=translators \
  --package-name="Apogee Watcher for WordPress" \
  -o languages/apogee-wp-watcher.pot \
  apogee-wp-watcher.php uninstall.php includes/*.php
msgfmt -c -o languages/apogee-wp-watcher-el.mo languages/apogee-wp-watcher-el.po
# likewise fr_FR / de_DE after merging new msgids into each .po
```
