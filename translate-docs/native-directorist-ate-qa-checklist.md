# Native Directorist ATE QA Checklist

This checklist covers native Directorist and WPML behavior only. Extension-owned
screens and strings are tested separately.

## Completed

- [x] Add Listing
- [x] All Listings and its search bar
- [x] Single Listing and listing ATE fields

## Batch 1 - Complete

- [x] Search Listings
- [x] Search Result
- [x] All Categories
- [x] Single Category
- [x] All Locations

### Batch 1 Evidence

- Search Listings: refreshed ATE job 101 exposes the search-form Pricing label;
  Dutch page 228 renders the verified page UI values.
- Search Result: refreshed ATE job 100 exposes both Pricing and Items Found;
  Dutch page 229 renders the verified page UI values.
- Single Category: refreshed ATE job 99 exposes the search-form Pricing label;
  Dutch page 232 renders the verified archive UI values.
- All Categories: refreshed ATE job 104 contains the page title and native
  `No Results found!` empty state. Category names remain taxonomy translations.
  Dutch page 243 is linked to the English source page 238.
- All Locations: refreshed ATE job 105 contains the page title and native
  `No Results found!` empty state. Location names remain taxonomy translations.
  Dutch page 244 is linked to the English source page 239.
- The source English pages remain unchanged, and no frontend database write or
  extension-owned translation path was added.

## Batch 2 - Complete

- [x] Single Location
- [x] Single Tag
- [x] User Login
- [x] Registration and password recovery
- [x] User Dashboard tabs and messages

### Batch 2 Evidence

- Single Location: refreshed ATE job 106 exposes 61 native page fields,
  including the search-form Pricing label. Dutch page 233 renders the verified
  ATE values while English page 12 remains unchanged.
- Single Tag: refreshed ATE job 107 exposes 61 native page fields, including
  the search-form Pricing label. Dutch page 234 renders the verified ATE values
  while English page 13 remains unchanged.
- User Login, registration, and password recovery: refreshed ATE job 108
  exposes 53 native page fields. The logged-out Dutch frontend was visually
  checked in all three states without submitting a form.
- User Dashboard: refreshed ATE job 109 exposes 85 native page fields. The
  logged-in Dutch frontend was visually checked across My Listings, My Profile,
  Favorite Listings, and Preferences. The dynamic listing count is preserved
  while the ATE label is translated.
- Extension-owned Chat, Booking, Wallet, and pricing-plan tabs remain outside
  this native Directorist package and were not modified.
- The four target-page audits report no missing, orphaned, empty, placeholder,
  mismatched, or unfinished fields. All four WPML jobs deliberately remain
  incomplete (`translated=0`); ATE was not falsely completed.
- All 108 newly collected account/dashboard template strings were confirmed in
  the installed Directorist core source. No Dutch target text, frontend
  database write, or broad frontend scan was added to production code.

## Batch 3 - Complete

- [x] Author Profile and All Authors
- [x] Checkout
- [x] Payment receipt
- [x] Transaction failure
- [x] Directory Type name and Directory Builder package

### Batch 3 Evidence

- Author Profile: refreshed ATE job 121 exposes all 10 native page UI values.
  Dutch page 224 was visually checked for the member-since, review-count,
  listing-count, contact, about, filter, and empty-state values. Its dynamic
  count was also checked with two listings, and the English source page remains
  unchanged.
- All Authors: ATE job 117 exposes all three native page UI values. Dutch page
  252 renders the verified filter and listing-button values.
- Checkout: ATE job 118 exposes all 22 native page UI values, including the
  payment actions, configured bank-transfer text, dynamic formats, and
  reachable error states. Dutch page 254 renders the verified inactive-
  monetization state, while the English source remains unchanged.
- Payment receipt: ATE job 119 exposes all 34 native page UI values, including
  statuses, actions, bank instructions, dynamic percentages, and order error
  states. Dutch page 256 renders the verified missing-order state, while the
  English source remains unchanged.
- Transaction failure: ATE job 120 exposes its native failure message. Dutch
  page 258 renders that exact ATE value, while the English source remains
  unchanged.
- Directory Builder ATE job 111 visually exposes 1,405 package rows (1,404
  translatable). The Directory Name, Directory Type Name, and builder General
  label use the existing native package translation `General` to `Algemeen`.
  Source term 115 and Dutch term 144 share WPML translation group 616. This
  surface already worked through the native package flow, so no duplicate
  production code was added.
- The five page jobs deliberately remain incomplete (`translated=0`); ATE was
  not falsely completed. Only genuine ATE-visible values were synced to the
  matching Dutch target-page metadata.
- All 64 unique Batch 3 page values were confirmed as exact strings in the
  installed Directorist core source. Extension-owned pricing-plan strings were
  excluded. No Dutch target text, frontend database write, broad frontend scan,
  or orphan production helper was added.

## Batch 4 - Complete

- [x] Listing grid, list, and map cards
- [x] Empty results, pagination, sorting, and filter states
- [x] Gutenberg Directorist blocks
- [x] Elementor Directorist widgets
- [x] Category, Location, Tag, and Directory Type taxonomy translation

### Batch 4 Evidence

- Gutenberg ATE job 124 visually exposes the native block, listing-card,
  archive accessibility, account-button, and pagination values. Its Dutch page
  263 renders the verified custom block headings/buttons, favorite label, grid,
  list, and map labels. Grid, list, map, empty-result, expanded-filter, sorting,
  and page-2 states were all opened in the browser. English page 260 remains
  unchanged.
- Elementor ATE job 125 visually exposes the configured fields plus the native
  page UI from all 16 installed general Directorist widgets. Dutch page 264
  renders the verified widget headings/buttons, listing-card labels, view
  labels, and transaction failure value. English page 261 remains unchanged.
- AddonsKit renders its widgets by calling shortcode callbacks directly, which
  bypasses WordPress's `do_shortcode_tag` filter. Native Directorist Elementor
  output now uses the same exact page ATE map through Elementor's scoped
  `elementor/widget/render_content` filter. Unrelated Elementor widgets return
  unchanged.
- AddonsKit stores its Directorist taxonomy selectors as slugs, while WPML's
  `taxonomy-ids` converter converts numeric IDs only. A target-language-only
  Elementor pre-render mapper now resolves each unique selected source slug to
  its linked WPML target slug once per request. The browser proof used source
  slugs in the Dutch page data and rendered all widgets without an invalid
  directory notice; the English source bypassed the mapper and remained valid.
- The Gutenberg and Elementor target-page audits report 331/331 and 320/320
  matching page UI keys, with no missing, orphaned, empty, or QA-placeholder
  values. Both WPML jobs deliberately remain incomplete (`translated=0` and no
  finished fields); ATE was not falsely completed.
- WPML Taxonomy Translation visually reports `Edit translation` for the Dutch
  versions of the one Listing Category, all nine Listing Locations, all nine
  Listing Tags, and the General Listing Directory. The Commercial tag dialog
  visibly shows the Dutch name `Commercieel`; the remaining exact term values
  are covered by the target-term database audit.
- Native page strings are collected only during bounded admin/page-save flows.
  Frontend rendering performs in-memory replacement only. The existing
  Directorist/WPML string-registration count remained unchanged, and no
  extension-owned string or hardcoded Dutch production value was added.
- This batch used the existing Dutch language and General directory. The
  separate fresh-language/fresh-directory proof remains part of the global
  release checklist below.

## Batch 5 - Complete

- [x] Directorist settings and admin texts
- [x] Native Directorist email subjects and templates

### Batch 5 Evidence

- Translation Dashboard exposes two independent native packages:
  `Directorist Settings` and `Directorist Email Templates`.
- Settings ATE job 126 visually exposes all 64 configured user-facing values,
  including the first settings fields and the Bank Transfer title. Email ATE
  job 127 visually exposes all 30 native subject/body values, from New Listing
  through Email Verification.
- The Dutch Directorist settings screen visually renders the package value
  `Bankoverschrijving`. Its Listing submitted email editor renders the exact
  translated subject and body while preserving every Directorist placeholder.
- The independent database audit reports 64/64 completed Dutch package values
  for settings and 30/30 for email. It reports no empty target, placeholder
  mismatch, duplicate package string, QA placeholder, Claim Listing string, or
  Booking string.
- Both jobs deliberately remain open in ATE: job 126 and job 127 have
  `translated=0` and zero finished fields. No Save and Complete action was
  triggered.
- Numeric IDs, post IDs, URLs, colors, booleans, internal option values, and
  extension-owned settings are excluded. Missing defaults on fresh/older
  installs are collected dynamically from Directorist's native settings panel.
- Runtime translation performs one cached indexed read for both small package
  maps per target-language request, performs no frontend database write, and
  bypasses source-language requests. The settings-editor option filter is
  limited to the non-AJAX Directorist settings screen so translated values
  cannot overwrite source options.
- The obsolete email transient and nonexistent before/after-send hooks were
  removed. No language-specific production value or extension email path was
  added.

## Remaining

- Native Directorist surface inventory: none.
- Global release proof still requires the separate fresh-language and
  fresh-directory run below.

## Required Proof for Every Surface

- [ ] Every native user-facing source text that belongs to the page is visible in ATE.
- [ ] Technical keys, slugs, IDs, option values, and internal configuration are absent from ATE.
- [ ] Completed ATE values are synced to the exact translated WPML/page data.
- [ ] The translated frontend renders the exact ATE values.
- [ ] A fresh language and fresh directory type receive the same coverage.
- [ ] Existing translations and unrelated Directorist surfaces remain unchanged.
- [ ] Source changes mark only affected translations as needing an update.
- [ ] No frontend database writes, broad frontend scans, or extension strings are added.

## Translation Workflow

1. Inspect the real ATE job first without editing or completing it.
2. Record visible and missing native source texts.
3. Sync only genuine completed ATE values to the matching WPML/target page data.
4. For missing text, fix the native package/configuration flow at the source.
5. Refresh the WPML job, recheck ATE, then verify the translated frontend and database.
