=== Directorist - WPML Integration ===
Contributors: wpwax
Tags: directory, directorist, multilingual, wpml
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 3.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WPML ATE integration for multilingual Directorist listings, directory builders, pages, settings UI, admin labels, and emails.

== Description ==

Directorist - WPML Integration connects Directorist with WPML so directory owners can run multilingual listing websites with translated listings, directory types, directory pages, search forms, settings strings, settings UI labels, admin menu labels, and email content.

Useful links:

* [Documentation](https://directorist.com/documentation/directorist/directorist-wpml-translation-guide/directory-type-translation/)
* [Support](https://directorist.com/contact/)
* [Directorist extensions](https://directorist.com/extensions/)

== Requirements ==

The following plugins are required:

* Directorist - WordPress Business Directory Plugin with Classified Ads Listings
* WPML Multilingual CMS
* Directorist - WPML Integration

Recommended WPML add-ons:

* WPML String Translation
* WPML Media Translation

== Features ==

* Translate Directorist listings across WPML languages.
* Translate Directorist directory types and keep directory type post meta aligned.
* Translate Directorist categories, locations, tags, and directory-related taxonomy data.
* Show listing archives and search results in the current WPML language.
* Translate Directorist Directory Builder visual labels, placeholders, sections, layouts, controls, and field options through dedicated WPML ATE packages.
* Translate static Directorist shortcode UI text inside the matching WordPress page ATE job.
* Translate Directorist Settings UI tabs, labels, descriptions, notices, and admin menu labels through WPML package translations.
* Keep technical builder keys, IDs, icons, hooks, presets, and layout configuration out of translation jobs.
* Translate Directorist settings strings and email templates through the appropriate WPML workflow.
* Apply translated Directorist admin submenu labels through a lightweight admin-only runtime bridge that does not load on the frontend.
* Resolve Directorist page links to the matching WPML language page.
* Support Directorist REST requests with WPML language parameters.

== Installation ==

1. Install and activate Directorist.
2. Install and configure WPML Multilingual CMS.
3. Install and activate WPML String Translation for WPML package and gettext translation support.
4. Install and activate Directorist - WPML Integration.
5. Send the Directorist Directory Builder package and configured Directorist pages from WPML Translation Dashboard to the Advanced Translation Editor.
6. Translate the visual builder strings, page UI, settings UI, directory types, listings, and page content in their matching ATE jobs.

== Compatibility Testing ==

Version 3.0.0 was tested with:

* WordPress 7.0.2
* PHP 8.2.29
* Directorist 8.9.2
* WPML Multilingual CMS 4.9.5
* WPML String Translation 3.5.3
* WPML Media Translation 3.1.2
* Active WPML languages: English and Dutch

The release was verified through WPML Translation Dashboard, ATE, the Directorist builder, frontend pages, and WP-CLI covering:

* Plugin activation and WPML language configuration.
* Directory Builder package registration and ATE string exposure without technical configuration values.
* Translated builder tabs, nested form fields, section labels, placeholders, layouts, and controls.
* Search Home, Add Listing, All Listings, Search Result, taxonomy, account, dashboard, author, checkout, payment receipt, and transaction failure page ATE jobs.
* Settings UI package registration, admin submenu translation bridge, Directorist settings values, and native email template packages.
* Directory type translation groups and translated builder term meta synchronization.
* Source-change detection followed by Translation Dashboard re-send and ATE completion of all seven configured Directorist page jobs.
* Dutch frontend and admin rendering for Directorist pages, Directory Builder, Settings UI, and admin menu labels, including translated shortcode UI text.
* Production ZIP contents, PHP syntax, text domain, WPML XML, version metadata, catalog coverage, and removal of legacy duplicate builder strings.

== Release Coverage Snapshot ==

LocalWP release verification on 2026-07-30 covered the English-to-Dutch WPML/ATE flow:

* Directory Builder package: 1,313 registered strings; 1,313 strings present in the active ATE job; 372 Dutch translations complete; 935 Dutch strings still pending in ATE.
* Settings UI package: 964 registered strings; 950 strings present in the current ATE job; 950 Dutch translations complete; 14 newly registered strings pending job refresh/re-send.
* Directorist settings package: 64 registered strings; 64 strings present in ATE/package translation; 64 Dutch translations complete.
* Email templates package: 30 registered strings; 30 strings present in ATE/package translation; 30 Dutch translations complete.

The latest repair pass translated 26 ATE-visible Builder segments and synchronized 64 exact WPML string rows through WPML APIs after database backups. The active Builder ATE job still has untranslated segments and was not falsely marked complete.

== Frequently Asked Questions ==

= Listings show in English but not in another language. What should I check first? =

Confirm that the listing post type is translatable in WPML, the listing has a translation in the target language, the translated listing has a translated directory type, and the translated All Listings or Search Result page exists.

= Do Directorist pages need translations? =

Yes. Translate the Directorist pages selected in Directorist settings so WPML can resolve each language to its own All Listings, Add Listing, and Search Result page.

= Where should Directorist content be translated? =

Use WPML Translation Dashboard and ATE for listings, directory builder packages, pages, page-specific Directorist UI text, and Settings UI packages. WPML String Translation remains responsible for regular Directorist gettext strings and any settings or email strings that do not belong to a package/page ATE job.

== Changelog ==

= 3.0.0 =
* Breaking: Moved Directory Builder visual translation from raw translated term meta and mixed string workflows to dedicated WPML Translation Dashboard/ATE packages.
* Added: Directory Builder labels, placeholders, sections, layouts, field options, validation messages, dialogs, and controls are exposed in their directory-specific ATE package.
* Added: Static Directorist shortcode UI text is included in the matching page ATE job for Search Home, Add Listing, All Listings, Search Result, taxonomy, account, dashboard, checkout, and related Directorist pages.
* Added: Directorist Settings UI tabs, labels, descriptions, notices, and admin menu labels are exposed through WPML package translations.
* Added: Lightweight admin-only Directorist submenu translation bridge that uses the same package translations and does not load on the frontend.
* Added: Completed builder ATE translations are synchronized back to the matching translated directory term meta.
* Added: Source UI changes mark existing page and builder translation jobs for refresh.
* Added: Cached Builder runtime fallback for path-specific ATE labels so translated labels render when Directorist exposes the same source text through value-based runtime keys.
* Improved: Only user-facing visual text is exposed; technical keys, IDs, icons, hooks, presets, URLs, numeric values, and layout configuration remain protected.
* Fixed: Directorist listing queries now keep WPML SQL filtering enabled for all-listing, search-result, dashboard, and author listing contexts.
* Fixed: Directory type meta queries now include the full WPML directory translation group, so translated listings remain visible even when older listing meta stores a source-language directory ID.
* Fixed: Directory type taxonomy IDs are converted through stable term taxonomy IDs for WPML API calls.
* Fixed: Directorist REST requests now respect `language` and `wpml_lang` request parameters instead of falling back to English.
* Fixed: Directorist Add Listing and Search Form field translation contexts now use stable directory translation group IDs.
* Added: Synchronization support for translated listing `_directory_type` post meta and directory type term relationships.
* Added: WPML admin-text configuration for Directorist page options required by multilingual page resolution.
* Tested: Verified the real English-to-Dutch WPML Translation Dashboard, ATE, Directorist builder, Settings UI, admin menu, and frontend page flow.

= 2.2.1 =
* Added: Built-in synchronization of Directorist category `_directory_type` meta across WPML languages.
* Added: WPML config to copy the `_default` directory type flag across translations.
* Improved: WPML compatibility for directory types, categories, search form fields, and settings strings.
* Fixed: New translatable data displays correctly on the frontend.
* Fixed: Post, taxonomy, frontend string, and email translation handling.

= 2.1.4 =
* Added: Directorist as a required dependency.
* Added: Translation support for Claim Listing settings.

= 2.0.0 =
* Added: Directorist compatibility.

== Upgrade Notice ==

= 3.0.0 =
Directory Builder, page UI, and Settings UI translations now use WPML Translation Dashboard and ATE jobs/packages. Back up the site before upgrading and refresh existing translation jobs so they include the new visual-string packages and admin label coverage.
