# Contributing

- Target PHP 7.4+ and WordPress 5.8+.
- Prefix options, transients, and AJAX actions with `apogee_watcher_`.
- Escape all output; capability-check `manage_options` for admin actions; use nonces on AJAX.
- Do not contact Apogee Watcher until the site admin clicks **Connect**.
- No Google PSI keys in the plugin.
- Conventional commits preferred (`feat`, `fix`, `docs`).
- **i18n:** wrap user-facing strings with the `apogee-watcher` text domain. Regenerate the catalogue after string changes:

```bash
xgettext --language=PHP --from-code=UTF-8 \
  --keyword=__ --keyword=_e --keyword=_n:1,2 --keyword=_x:1,2c \
  --keyword=esc_attr__ --keyword=esc_html__ --keyword=esc_attr_e --keyword=esc_html_e \
  --add-comments=translators \
  --package-name="Apogee Watcher: Automated PageSpeed Monitoring" \
  -o languages/apogee-watcher.pot \
  apogee-watcher.php uninstall.php includes/*.php
msgfmt -c -o languages/apogee-watcher-el.mo languages/apogee-watcher-el.po
# likewise fr_FR / de_DE after merging new msgids into each .po
```

## Distribution ZIP

Never ship a GitHub “Download ZIP” of `main` as the release artefact (folder becomes `apogee-watcher-main`, and repo files pollute Plugin Auditor / Plugin Check).

```bash
./bin/build-release-zip.sh              # self-hosted (keeps Update URI)
./bin/build-release-zip.sh --wporg      # WordPress.org candidate (strips Update URI)
```

Excluded from the package: `.git`, `.gitignore`, `.gitattributes`, `CONTRIBUTING.md`, `bin/`, `dist/`, IDE folders, nested `*.zip`.

### Plugin Check / WordPress.org notes

| Finding | Our stance |
| --- | --- |
| Hidden `.gitignore` | Excluded from release ZIP |
| `Update URI` / updater | Required for apogeewatcher.com updates; use `--wporg` when submitting to the directory |
| Trademark “WordPress” in name | Display name is **Apogee Watcher: Automated PageSpeed Monitoring** |
| Plugin slug | Directory / text domain / Update API slug is **`apogee-watcher`** (renamed from `apogee-wp-watcher` in 0.3.1) |
| `load_plugin_textdomain` | Called only when Update URI is present (self-hosted); .org packs omit the header and rely on language packs |
| `Tested up to` | Keep current with the latest WP major in `readme.txt` |
