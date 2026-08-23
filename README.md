# Directorist WPML Integration

Official WPML integration extension for [Directorist](https://directorist.com) that lets you build fully multilingual directory sites.

## Key features

- Syncs Directorist listings, directory types, categories, locations and tags across WPML languages.
- Makes Directorist settings, search forms, widgets/blocks and email templates translatable via WPML String Translation.
- Automatically syncs category and listing `_directory_type` meta plus directory `_default` flags across translations.
- Tested with WordPress 6.9, PHP 7.4+, Directorist 8.7.1, WPML 4.9.2.1, and WP-CLI.
- Verified with a 138-check WP-CLI compatibility suite across English, French, German, Romanian, and Bengali.

## Changelog (short)

- **2.2.3 (2026-05-06)**
  - Fixed translated listing visibility for all-listing and search-result queries.
  - Fixed directory type meta synchronization across WPML translation groups.
  - Added WP-CLI compatibility verification across `en`, `fr`, `de`, `ro`, and `bn`.

- **2.2.1 (2026-02-05)**
  - Added automatic syncing of Directorist category directory assignments across WPML languages.
  - Added WPML config for copying the default directory type flag across translations.
  - Improved overall compatibility with WordPress 6.8 and current WPML releases.

For full details, see `readme.txt` or the [plugin page](https://github.com/sovware/directorist-wpml-integration).
