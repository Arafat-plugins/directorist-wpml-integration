# Directorist WPML Integration

Official WPML integration extension for [Directorist](https://directorist.com) that lets you build fully multilingual directory sites.

## Key features

- Syncs Directorist listings, directory types, categories, locations and tags across WPML languages.
- Sends Directory Builder visual labels, placeholders, sections, layouts and controls through WPML Translation Dashboard and ATE packages.
- Adds static Directorist shortcode UI text to the matching WordPress page ATE job.
- Registers Directorist Settings UI tabs, labels, descriptions, notices and admin menu labels for WPML/ATE translation instead of relying on hardcoded language output.
- Keeps technical builder configuration out of translation jobs while preserving translated visual values in their original builder context.
- Makes Directorist settings, widgets/blocks and email templates available through the appropriate WPML translation workflow.
- Applies translated Directorist admin submenu labels through a lightweight admin-only runtime bridge that uses the same WPML package translations and never loads on the frontend.
- Automatically syncs category and listing `_directory_type` meta plus directory `_default` flags across translations.
- Tested with WordPress 7.0.2, PHP 8.2.29, Directorist 8.9.2, WPML 4.9.5, and WPML String Translation 3.5.3.

## Release coverage snapshot

LocalWP release verification on 2026-07-30 covered the English-to-Dutch WPML/ATE flow:

| Area | Registered strings | Current ATE job coverage | Dutch complete | Pending Dutch |
| --- | ---: | ---: | ---: | ---: |
| Directory Builder package | 1,313 | 1,313 | 372 | 935 |
| Settings UI package | 964 | 950 | 950 | 14 |
| Directorist settings package | 64 | 64 | 64 | 0 |
| Email templates package | 30 | 30 | 30 | 0 |

The current repair pass manually translated 26 ATE-visible Builder segments and synchronized 64 exact WPML string rows through WPML APIs after database backups. The active Builder ATE job still has untranslated segments, so it was deliberately not falsely marked complete. The Settings UI job is complete for its current 950 strings, but 14 newly registered strings require a Translation Dashboard refresh/re-send before they can be completed in ATE.

## Changelog (short)

- **3.0.0 (2026-07-20)**
  - Moved Directory Builder visual translation to dedicated WPML Translation Dashboard/ATE packages.
  - Added page-specific Directorist shortcode UI text to page ATE jobs.
  - Added Directorist Settings UI and admin menu translation coverage using WPML package data.
  - Excluded technical builder keys, IDs, icons, hooks and layout configuration from translation jobs.
  - Added translated directory meta synchronization and refresh detection when source UI text changes.
  - Added a cached Builder runtime fallback for path-specific ATE strings so translated labels render even when Directorist exposes the same label through a value-based runtime key.
  - Added a lightweight admin-only submenu bridge for Directorist admin pages.
  - Improved multilingual listing queries, REST requests, page mapping and directory type synchronization.

- **2.2.1 (2026-02-05)**
  - Added automatic syncing of Directorist category directory assignments across WPML languages.
  - Added WPML config for copying the default directory type flag across translations.
  - Improved overall compatibility with WordPress 6.8 and current WPML releases.

For full details, see `readme.txt` or the [plugin page](https://github.com/sovware/directorist-wpml-integration).
