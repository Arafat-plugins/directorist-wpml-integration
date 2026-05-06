=== Directorist - WPML Integration ===
Contributors: wpwax
Tags: directory, directorist, multilingual, wpml
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 2.2.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WPML compatibility extension for Directorist multilingual listings, directories, search forms, settings, and emails.

== Description ==

Directorist - WPML Integration connects Directorist with WPML so directory owners can run multilingual listing websites with translated listings, directory types, directory pages, search forms, settings strings, and email content.

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
* Translate Directorist add-listing and search-form field labels, placeholders, options, and section labels.
* Translate Directorist settings strings and email templates.
* Resolve Directorist page links to the matching WPML language page.
* Support Directorist REST requests with WPML language parameters.

== Installation ==

1. Install and activate Directorist.
2. Install and configure WPML Multilingual CMS.
3. Install and activate WPML String Translation if settings, forms, and frontend strings need translation.
4. Install and activate Directorist - WPML Integration.
5. Translate the Directorist pages configured under Directorist settings, including All Listings, Add Listing, and Search Result.
6. Translate directory types and listing content with WPML.

== Compatibility Testing ==

Version 2.2.3 was tested with:

* WordPress 6.9.4
* PHP 8.3.26
* Directorist 8.7.1
* WPML Multilingual CMS 4.9.2.1
* WPML String Translation 3.5.1
* WPML Media Translation 3.1.0
* Active WPML languages: English, French, German, Romanian, and Bengali

The release was verified with WP-CLI using a 138-check compatibility suite covering:

* Plugin activation and WPML language configuration.
* Directorist listing post type and directory taxonomy WPML settings.
* Listing translations in every active language.
* Directory type translation groups in every active language.
* All Listings, Add Listing, and Search Result page translations and permalinks.
* Default listing queries and explicit directory search queries.
* Directorist REST language switching with `language` and `wpml_lang` parameters.
* Directorist AJAX hook registration.
* Shortcode rendering for all-listing, add-listing, and search-result pages.

== Frequently Asked Questions ==

= Listings show in English but not in another language. What should I check first? =

Confirm that the listing post type is translatable in WPML, the listing has a translation in the target language, the translated listing has a translated directory type, and the translated All Listings or Search Result page exists.

= Do Directorist pages need translations? =

Yes. Translate the Directorist pages selected in Directorist settings so WPML can resolve each language to its own All Listings, Add Listing, and Search Result page.

= Does this plugin replace WPML String Translation? =

No. WPML String Translation is recommended for translating Directorist settings, frontend strings, form labels, and email text.

== Changelog ==

= 2.2.3 =
* Fixed: Directorist listing queries now keep WPML SQL filtering enabled for all-listing, search-result, dashboard, and author listing contexts.
* Fixed: Directory type meta queries now include the full WPML directory translation group, so translated listings remain visible even when older listing meta stores a source-language directory ID.
* Fixed: Directory type taxonomy IDs are converted through stable term taxonomy IDs for WPML API calls.
* Fixed: Directorist REST requests now respect `language` and `wpml_lang` request parameters instead of falling back to English.
* Fixed: Directorist Add Listing and Search Form field translation contexts now use stable directory translation group IDs.
* Added: Synchronization support for translated listing `_directory_type` post meta and directory type term relationships.
* Added: WPML admin-text configuration for Directorist page options required by multilingual page resolution.
* Tested: Verified with WP-CLI across English, French, German, Romanian, and Bengali.

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

= 2.2.3 =
This release fixes translated listing visibility, directory type meta synchronization, REST language handling, and multilingual Directorist page/query resolution.
