# Directorist WPML ATE release coverage

Verification date: 2026-07-30

LocalWP site: `directorist-wpml.host`

Runtime versions verified:

- WordPress 7.0.2
- PHP 8.2.29
- Directorist 8.9.2
- WPML Multilingual CMS 4.9.5
- WPML String Translation 3.5.3
- WPML Media Translation 3.1.2
- Source language: English
- Target language used for verification: Dutch (`nl`)

## ATE/package coverage counts

These counts were verified from the local WPML tables and active translation jobs.

| Area | WPML string context | Registered strings | Current ATE job coverage | Dutch complete | Pending Dutch | Verdict |
| --- | --- | ---: | ---: | ---: | ---: | --- |
| Directory Builder package | `directorist-directory-builder-directory_builder_616` | 1,313 | 1,313 | 372 | 935 | All registered Builder strings are present in the active ATE job. The remaining Dutch strings are ATE-visible and intentionally not marked complete. |
| Settings UI package | `directorist-settings-ui-directorist_settings_ui` | 964 | 950 | 950 | 14 | The current ATE job is complete for its 950 strings, but 14 newly registered strings require a job refresh/re-send. |
| Directorist settings package | `directorist-settings-directorist_settings` | 64 | 64 | 64 | 0 | Complete. |
| Email templates package | `directorist-email-templates-directorist_email_templates` | 30 | 30 | 30 | 0 | Complete. |

Active job evidence:

- Directory Builder ATE job: local job `154`, ATE editor job `205659794`, 1 technical row plus 1,313 translatable rows, `needs_update=0`.
- Settings UI ATE job: local job `153`, ATE editor job `205572877`, 1 technical row plus 950 translatable rows, `needs_update=1`.
- Builder missing-Dutch rows not present in the active ATE job: `0`.
- Settings UI registered rows not present in the current ATE job: `14`.

Settings UI strings pending job refresh/re-send:

- All Directories
- Deprecated Directorist item requires an upgrade
- Deprecated Directorist items require upgrades
- Directorist detected a deprecated theme or extension that is not compatible with this version. Upgrade it to the compatible version listed below.
- Directorist detected deprecated themes or extensions that are not compatible with this version. Upgrade them to the compatible versions listed below.
- Directory names
- Installed Version
- Item
- Manage Plugins
- Manage Themes
- Required Version
- Show deprecated items
- Status
- Type

## Native Directorist surfaces already covered

The saved native ATE checklist covers:

- Add Listing
- All Listings and search bar
- Single Listing fields
- Search Listings
- Search Result
- All Categories
- Single Category
- All Locations
- Single Location
- Single Tag
- User Login
- Registration
- Password Recovery
- User Dashboard tabs/messages
- Author Profile
- All Authors
- Checkout
- Payment Receipt
- Transaction Failure
- Directory Builder package
- Listing grid cards
- Listing list cards
- Listing map view
- Empty-results states
- Pagination
- Sorting
- Filter states
- Gutenberg Directorist blocks
- Elementor Directorist widgets
- Native email subjects/templates
- Existing Directorist setting values

Separate WPML taxonomy flow, not page ATE:

- Listing Categories
- Listing Locations
- Listing Tags
- Directory Type name

## Current repair pass

The latest repair pass handled the visible Builder/Settings/Admin examples from the release QA screenshots:

- 26 ATE-visible Builder segments were manually translated through the active ATE UI.
- 64 exact WPML string rows were synchronized through WPML APIs after database backups.
- ATE jobs were not falsely saved as complete when untranslated strings remained.
- No Dutch text was hardcoded in production PHP/JS.
- Local DB backups were kept under `translate-docs/ate-db-backups/` and are not release artifacts.

## Runtime safety

- The Directorist admin submenu translation bridge is admin-only.
- The submenu bridge uses existing WPML package/string translations and does not write to the database.
- Frontend requests do not load the admin submenu bridge.
- Builder runtime translation fallback is cached per request and skips ambiguous duplicate source strings to avoid incorrect data mapping.
- Technical builder configuration, IDs, hooks, icons, URLs, numbers, and layout keys stay outside ATE translation jobs.

## Release verdict

The integration is now ATE/package-first for Directorist pages, Directory Builder UI, Settings UI, admin labels, Directorist setting values, and native email templates.

Before final release, refresh/re-send the Settings UI package job so the 14 newly registered strings are included in ATE, then complete any remaining Builder strings in ATE if a fully translated Dutch demo is required.
