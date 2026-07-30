# Directorist Page Shortcode UI Translation in WPML ATE

## Problem

Directorist pages often contain only a shortcode or Directorist block in the WordPress page content.

Example:

```text
[directorist_add_listing]
[directorist_all_listing]
[directorist_search_result]
```

Before this fix, WPML Advanced Translation Editor only received the page title/body shortcode text. It did not receive the real UI text rendered by Directorist, for example:

```text
What are you looking for?
Filters
Sort By
Apply Filters
You are about to publish
Drop Here
Add Social
```

So users had to translate those texts separately from String Translation or other Directorist/WPML screens. This was confusing because the user expected all page-related visible text to appear inside the page ATE job.

## Goal

Send page-related static Directorist shortcode UI text into the page ATE job.

The fix must not send dynamic listing data. These should not be part of page translation:

```text
Listing title
Listing date
Listing count
Listing category assigned to a listing
Rating value
Author/listing result data
```

## Files Changed

```text
app/Controller/Hook/Page_Shortcode_UI_Translation.php
app/Controller/Hook/Init.php
wpml-config.xml
```

## Main Class

```php
Directorist_WPML_Integration\Controller\Hook\Page_Shortcode_UI_Translation
```

This class handles three responsibilities:

1. Detect Directorist shortcode/block pages.
2. Save page-related static UI text into hidden page meta.
3. Replace those static UI strings on translated frontend pages using the translated page meta.

## Registration Code

The hook class is registered from:

```text
app/Controller/Hook/Init.php
```

Code:

```php
protected function get_hooks() {
    return [
        // Other hooks...

        Directory_Translation::class,
        Page_Shortcode_UI_Translation::class,
        Sorting_Options_Translation::class,
    ];
}
```

## Hook Setup Code

File:

```text
app/Controller/Hook/Page_Shortcode_UI_Translation.php
```

Constructor:

```php
public function __construct() {
    add_action( 'admin_init', [ $this, 'sync_source_pages' ], 50 );
    add_action( 'save_post_page', [ $this, 'sync_page_on_save' ], 40, 3 );
    add_action( 'elementor/editor/after_save', [ $this, 'sync_elementor_page_on_save' ], 40, 2 );
    add_action( 'elementor/frontend/widget/before_render', [ $this, 'translate_elementor_widget_taxonomy_settings' ], 20 );
    add_filter( 'do_shortcode_tag', [ $this, 'translate_shortcode_output' ], 20, 4 );
    add_filter( 'render_block', [ $this, 'translate_block_output' ], 20, 2 );
    add_filter( 'elementor/widget/render_content', [ $this, 'translate_elementor_widget_output' ], 20, 2 );
}
```

Meaning:

```text
admin_init
```

syncs hidden UI meta before WPML creates/updates page translation jobs.

```text
save_post_page
```

syncs hidden UI meta when the source page is saved.

```text
do_shortcode_tag
```

replaces translated UI text on frontend after Directorist renders the shortcode output.

## Hidden Page Meta

The source/default-language page stores collected UI strings in:

```text
_directorist_wpml_page_ui_strings
```

The hash is stored in:

```text
_directorist_wpml_page_ui_strings_hash
```

The hash prevents unnecessary meta updates and prevents unnecessary WPML job refreshes.

## WPML Config

The hidden meta is registered in `wpml-config.xml`:

```xml
<custom-field action="translate">_directorist_wpml_page_ui_strings</custom-field>
<custom-field action="copy">_directorist_wpml_page_ui_strings_hash</custom-field>

<custom-fields-texts>
    <key name="_directorist_wpml_page_ui_strings">
        <key name="*"/>
    </key>
</custom-fields-texts>
```

Why:

```text
_directorist_wpml_page_ui_strings
```

must be translatable because ATE needs each string.

```text
_directorist_wpml_page_ui_strings_hash
```

must be copied because it is only internal sync state, not user-facing content.

## Shortcode and Block Mapping Code

Code:

```php
private $shortcode_contexts = [
    'directorist_all_listing'    => 'all_listing',
    'directorist_search_result'  => 'search_result',
    'directorist_search_listing' => 'search_listing',
    'directorist_add_listing'    => 'add_listing',
    'directorist_category'       => 'category',
    'directorist_location'       => 'location',
    'directorist_tag'            => 'tag',
];

private $block_contexts = [
    'directorist/all-listing'     => 'all_listing',
    'directorist/search-result'   => 'search_result',
    'directorist/search-listing'  => 'search_listing',
    'directorist/add-listing'     => 'add_listing',
    'directorist/category'        => 'category',
    'directorist/location'        => 'location',
    'directorist/tag'             => 'tag',
];
```

## Supported Shortcodes

The class currently supports:

```text
directorist_all_listing
directorist_search_result
directorist_search_listing
directorist_add_listing
directorist_category
directorist_location
directorist_tag
```

## Supported Blocks

The class currently supports:

```text
directorist/all-listing
directorist/search-result
directorist/search-listing
directorist/add-listing
directorist/category
directorist/location
directorist/tag
```

## How Strings Are Collected

On admin page/WPML screens, the class scans source-language pages that contain Directorist shortcodes or Directorist blocks.

It collects strings from these sources:

```text
Directorist global options
Directorist search form builder meta
Directorist add listing form builder meta
Directorist listing card builder meta
Static template labels used by Directorist shortcode output
```

Admin sync code:

```php
public function sync_source_pages() {
    if ( ! is_admin() || wp_doing_ajax() || ! current_user_can( 'edit_pages' ) || ! $this->should_sync_admin_pages() ) {
        return;
    }

    global $wpdb;

    $page_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
            WHERE post_type = %s
                AND post_status NOT IN ('trash', 'auto-draft')
                AND ( post_content LIKE %s OR post_content LIKE %s )",
            'page',
            '%[directorist_%',
            '%wp:directorist/%'
        )
    );

    foreach ( wp_parse_id_list( $page_ids ) as $page_id ) {
        $this->sync_page_ui_meta( $page_id );
    }
}
```

Admin screen guard code:

```php
private function should_sync_admin_pages() {
    global $pagenow;

    $pagenow = is_string( $pagenow ) ? $pagenow : '';

    if ( 'post.php' === $pagenow ) {
        $post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;

        return $post_id > 0 && 'page' === get_post_type( $post_id );
    }

    if ( 'admin.php' === $pagenow ) {
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        return false !== strpos( $page, 'tm/menu/main.php' )
            || false !== strpos( $page, 'sitepress-multilingual-cms' )
            || false !== strpos( $page, 'wpml' );
    }

    return (bool) apply_filters( 'directorist_wpml_should_sync_page_ui_strings', false );
}
```

Meta sync code:

```php
private function sync_page_ui_meta( $post_id, $post_content = null ) {
    $post_id = (int) $post_id;
    if ( $post_id <= 0 || ! $this->is_source_page( $post_id ) ) {
        return;
    }

    if ( null === $post_content ) {
        $post = get_post( $post_id );
        if ( ! $post || 'page' !== $post->post_type ) {
            return;
        }

        $post_content = (string) $post->post_content;
    }

    $strings = $this->collect_page_ui_strings( (string) $post_content );
    if ( empty( $strings ) ) {
        $previous_hash = get_post_meta( $post_id, self::META_HASH_KEY, true );

        delete_post_meta( $post_id, self::META_KEY );
        delete_post_meta( $post_id, self::META_HASH_KEY );

        if ( '' !== $previous_hash ) {
            $this->mark_page_translations_need_update( $post_id );
        }

        return;
    }

    ksort( $strings );

    $hash          = md5( wp_json_encode( $strings ) );
    $previous_hash = get_post_meta( $post_id, self::META_HASH_KEY, true );
    if ( $hash === $previous_hash ) {
        return;
    }

    update_post_meta( $post_id, self::META_KEY, $strings );
    update_post_meta( $post_id, self::META_HASH_KEY, $hash );

    $this->mark_page_translations_need_update( $post_id );
}
```

Example collected strings for All Listings:

```text
What are you looking for?
Filters
Reset Filters
Apply Filters
Sort By
View As
Grid
List
Map
No listings found.
Review
```

Example collected strings for Add Listing:

```text
General Information
Contact Information
You are about to publish
Are you sure you want to publish this listing?
Add Social
Drop Here
Preview
Drag and drop an image
Add More
Maximum __DT__ files are allowed
```

## What Is Ignored

The collector avoids internal/non-visible builder data such as:

```text
widget_key
field_key
type
icon
hook
preset
section_id
post_type
required
enable
value
option_value
```

This prevents ATE pollution with technical values.

## ATE Job Refresh

WPML can reuse an existing ATE job. If a job was created before the hidden page UI meta existed, ATE may show only the page title/body.

To fix that, when `_directorist_wpml_page_ui_strings` changes, the integration marks existing translations as:

```text
needs_update = 1
```

This tells WPML to refresh the translation package and include the new page UI strings.

This is done only when the hidden page UI meta hash changes.

## Frontend Translation Flow

On translated frontend pages:

1. Directorist renders the normal shortcode output.
2. The integration reads source page UI strings.
3. The integration reads translated page UI strings.
4. It builds a source-to-translation map.
5. It replaces only visible text nodes and exact attribute values.

Example:

```html
<input placeholder="What are you looking for?">
<span>Filters</span>
```

becomes:

```html
<input placeholder="Que recherchez-vous ?">
<span>Filtres</span>
```

It does not replace arbitrary URLs/classes/data keys.

## Performance Notes

Frontend scanning is not done.

The expensive builder scan happens only in admin context, mainly page/WPML related screens.

On frontend, the integration only works for non-default language pages and only when Directorist shortcode output is being rendered.

Normal English/default-language pages return early.

The current local test generated:

```text
All Listings: 45 UI strings
Add Listing: 58 UI strings
Search Result: 40 UI strings
```

This is small enough for safe runtime replacement.

## How To Test

### 1. Clear stale ATE view

Close any already-open ATE browser tab. Do not use an old direct ATE URL.

### 2. Open WPML Translation Dashboard

Go to:

```text
WPML > Translation Dashboard
```

### 3. Select a Directorist page

Test with:

```text
Add Listing
All Listings
Search Result
Single Category
Single Location
Single Tag
```

### 4. Open or update the translation job

If WPML shows update/refresh icon, use that. The job must be opened from Translation Dashboard after the integration has synced page UI meta.

### 5. Search in ATE

For Add Listing, search:

```text
Drop Here
You are about to publish
Add Social
General Information
Contact Information
```

For All Listings, search:

```text
What are you looking for?
Filters
Sort By
Apply Filters
Review
```

For Search Result, search:

```text
What are you looking for?
Filters
Sort By
Reset Filters
```

### 6. Save and Complete

Translate the strings and complete the ATE job.

### 7. Check frontend

Open translated language page.

Expected result:

```text
Static page UI text is translated.
Listings still show normally.
Dynamic listing data is not translated through page ATE.
```

## Local Verification Commands

Check PHP syntax:

```powershell
& 'C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe' -l app\Controller\Hook\Page_Shortcode_UI_Translation.php
```

Check XML validity:

```powershell
[xml](Get-Content -Raw wpml-config.xml)
```

Check generated page UI meta:

```powershell
$code = @'
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['HTTP_HOST'] = 'directorist-wpml.host';
define('WP_ADMIN', true);
require 'C:/laragon/www/Directorist-wpml/wp-load.php';
foreach (array(9, 10, 8) as $page_id) {
    $post = get_post($page_id);
    $strings = get_post_meta($page_id, '_directorist_wpml_page_ui_strings', true);
    echo 'page=' . $page_id . ' title=' . ($post ? $post->post_title : 'missing') . ' count=' . (is_array($strings) ? count($strings) : 'not-array') . PHP_EOL;
}
'@
& 'C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe' -r $code
```

Check whether a new WPML package includes Directorist page UI fields:

```powershell
$code = @'
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['HTTP_HOST'] = 'directorist-wpml.host';
define('WP_ADMIN', true);
require 'C:/laragon/www/Directorist-wpml/wp-load.php';
$package_builder = new WPML_Element_Translation_Package();
$package = $package_builder->create_translation_package(get_post(9));
$directorist = 0;
foreach ($package['contents'] as $field => $data) {
    if (false !== strpos($field, '_directorist_wpml_page_ui_strings')) {
        $directorist++;
    }
}
echo 'total=' . count($package['contents']) . ' directorist_page_ui_fields=' . $directorist . PHP_EOL;
'@
& 'C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe' -r $code
```

Expected example result:

```text
total=180 directorist_page_ui_fields=174
```

Why 174:

```text
58 UI strings x 3 WPML custom-field records
```

WPML stores each serialized custom-field item as:

```text
value
name
type
```

## Troubleshooting

### ATE shows only page title

Cause:

```text
The ATE job is stale or was opened from an old direct ATE URL.
```

Fix:

```text
Close the ATE tab.
Reload WPML Translation Dashboard.
Open the page job again from the dashboard.
```

If needed, mark the page translation for update again by saving the source page or visiting the WPML dashboard after the integration is active.

### UI strings exist in post meta but not in ATE

Check:

```text
_directorist_wpml_page_ui_strings exists on the source page.
WPML custom field setting is translate.
The translation status has needs_update=1 if an old translation job exists.
```

### ATE shows some English source text that should be different

Cause:

```text
The source/default-language Directorist settings already contain that text.
```

Example:

If the English source option contains `Trier Par`, ATE will show `Trier Par` as source text.

Fix:

```text
Correct the source/default-language Directorist setting first, then refresh the translation job.
```

### Translated frontend still shows old strings

Check:

```text
ATE job was saved and completed.
Translated page has _directorist_wpml_page_ui_strings meta.
Frontend URL is the translated page URL, not the English page URL.
Cache is cleared if a cache plugin/CDN is active.
```

## Important Limitation

This feature is for page-level static UI text rendered by Directorist shortcodes/blocks.

It is not intended to translate:

```text
Listing content
Listing taxonomy terms
Directory builder source configuration itself
Runtime search result data
```

Those still belong to their own WPML translation flows.

## String Collection Code Examples

All Listings/Search Result archive strings:

```php
private function collect_listing_archive_strings( &$strings, $context, $directory_id, $search_result ) {
    $options = $search_result
        ? [
            'search_viewas_text'               => 'View As',
            'search_sortby_text'               => 'Sort By',
            'search_result_filter_button_text' => 'Filters',
            'sresult_reset_text'               => 'Reset Filters',
            'sresult_sidebar_reset_text'       => 'Clear All',
            'sresult_apply_text'               => 'Apply Filters',
        ]
        : [
            'all_listing_title'                 => 'Items Found',
            'view_as_text'                      => 'View As',
            'sort_by_text'                      => 'Sort By',
            'listings_filter_button_text'       => 'Filters',
            'listings_sidebar_filter_text'      => 'Filters',
            'listings_reset_text'               => 'Reset Filters',
            'listings_sidebar_reset_text'       => 'Clear All',
            'listings_apply_text'               => 'Apply Filters',
            'listings_category_placeholder'     => 'Select a category',
            'listings_location_placeholder'     => 'Select a location',
            'listings_search_text_placeholder'  => 'What are you looking for?',
            'popular_badge_text'                => 'Popular',
            'feature_badge_text'                => 'Featured',
            'readmore_text'                     => 'Read More',
        ];

    foreach ( $options as $option_key => $default ) {
        $this->add_setting_string( $strings, $context, $option_key, $default );
    }

    foreach ( $this->get_sorting_strings() as $key => $label ) {
        $this->add_string( $strings, $context, 'sorting_' . $key, $label );
    }

    foreach ( $this->get_view_strings() as $key => $label ) {
        $this->add_string( $strings, $context, 'view_' . $key, $label );
    }

    if ( $directory_id > 0 ) {
        $this->collect_builder_meta_strings( $strings, $context, $directory_id, 'search_form_fields' );
        $this->collect_builder_meta_strings( $strings, $context, $directory_id, 'listings_card_grid_view' );
        $this->collect_builder_meta_strings( $strings, $context, $directory_id, 'listings_card_list_view' );
    }
}
```

Add Listing strings:

```php
private function collect_add_listing_strings( &$strings, $context, $directory_id ) {
    if ( $directory_id > 0 ) {
        $this->collect_builder_meta_strings( $strings, $context, $directory_id, 'submission_form_fields' );
        $this->collect_builder_meta_strings( $strings, $context, $directory_id, 'submit_button_label' );
    }

    foreach ( $this->get_add_listing_template_strings() as $key => $value ) {
        $this->add_string( $strings, $context, $key, $value );
    }
}
```

Builder meta extraction:

```php
private function collect_builder_meta_strings( &$strings, $context, $directory_id, $meta_key ) {
    $meta_value = $this->get_raw_term_meta( $directory_id, $meta_key );

    if ( is_array( $meta_value ) ) {
        $this->extract_builder_strings( $strings, $context, $meta_key, $meta_value );
        return;
    }

    if ( $this->is_translatable_builder_value( $meta_key, $meta_value, [ $meta_key ] ) ) {
        $this->add_string( $strings, $context, $meta_key, $meta_value );
    }
}
```

Raw term meta query:

```php
private function get_raw_term_meta( $term_id, $meta_key ) {
    global $wpdb;

    $value = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1",
            (int) $term_id,
            $meta_key
        )
    );

    return null === $value ? '' : maybe_unserialize( $value );
}
```

This raw query is intentional. It avoids WPML language/meta filters while reading the source directory builder configuration.

## ATE Refresh Code

When hidden UI meta changes, the integration marks existing translated page jobs as needing update.

Code:

```php
private function mark_page_translations_need_update( $source_page_id ) {
    if ( ! $this->is_wpml_active() ) {
        return;
    }

    $translations = WPML_Helper::get_element_translations( $source_page_id, 'page' );
    if ( empty( $translations ) ) {
        return;
    }

    global $wpdb;

    foreach ( $translations as $translation ) {
        if ( ! is_object( $translation ) || ! empty( $translation->original ) || empty( $translation->source_language_code ) ) {
            continue;
        }

        $translation_id = ! empty( $translation->translation_id ) ? absint( $translation->translation_id ) : 0;
        if ( $translation_id <= 0 ) {
            continue;
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'icl_translation_status',
            [ 'needs_update' => 1 ],
            [ 'translation_id' => $translation_id ],
            [ '%d' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            continue;
        }

        do_action(
            'wpml_updated_translation_status',
            [
                'translation_id' => $translation_id,
                'needs_update'   => 1,
            ]
        );

        do_action(
            'wpml_translation_status_update',
            [
                'post_id' => ! empty( $translation->element_id ) ? absint( $translation->element_id ) : (int) $source_page_id,
                'type'    => 'needs_update',
                'value'   => 1,
            ]
        );
    }
}
```

Why this is needed:

```text
If a page translation job already exists, ATE can reuse the old package.
The old package may contain only title/body/excerpt.
Marking needs_update forces WPML to create a refreshed package including _directorist_wpml_page_ui_strings.
```

## Frontend Replacement Code

Shortcode output filter:

```php
public function translate_shortcode_output( $output, $tag, $attr, $m ) {
    if ( empty( $output ) || ! is_string( $output ) || empty( $this->shortcode_contexts[ $tag ] ) ) {
        return $output;
    }

    $map = $this->get_current_page_translation_map();
    if ( empty( $map ) ) {
        return $output;
    }

    return $this->replace_output_strings( $output, $map );
}
```

Replacement map:

```php
private function get_current_page_translation_map() {
    if ( null !== $this->current_page_map ) {
        return $this->current_page_map;
    }

    $this->current_page_map = [];

    if ( ! $this->is_wpml_active() ) {
        return $this->current_page_map;
    }

    $current_language = apply_filters( 'wpml_current_language', null );
    $default_language = apply_filters( 'wpml_default_language', null );

    if ( empty( $current_language ) || empty( $default_language ) || $current_language === $default_language ) {
        return $this->current_page_map;
    }

    $current_page_id = (int) get_queried_object_id();
    if ( $current_page_id <= 0 || 'page' !== get_post_type( $current_page_id ) ) {
        return $this->current_page_map;
    }

    $source_page_id = $this->get_source_page_id( $current_page_id );
    if ( $source_page_id <= 0 || $source_page_id === $current_page_id ) {
        return $this->current_page_map;
    }

    $source_strings     = get_post_meta( $source_page_id, self::META_KEY, true );
    $translated_strings = get_post_meta( $current_page_id, self::META_KEY, true );

    if ( ! is_array( $source_strings ) || ! is_array( $translated_strings ) ) {
        return $this->current_page_map;
    }

    foreach ( $source_strings as $key => $source_value ) {
        if ( empty( $translated_strings[ $key ] ) || ! is_string( $source_value ) || ! is_string( $translated_strings[ $key ] ) ) {
            continue;
        }

        $translated_value = $translated_strings[ $key ];
        if ( '' === trim( $translated_value ) || $translated_value === $source_value ) {
            continue;
        }

        $this->current_page_map[ $source_value ] = $translated_value;
    }

    return $this->current_page_map;
}
```

Safe HTML replacement:

```php
private function replace_output_strings( $output, $map ) {
    foreach ( $map as $source => $translated ) {
        if ( '' === $source || '' === $translated || $source === $translated ) {
            continue;
        }

        $source_text     = esc_html( $source );
        $translated_text = esc_html( $translated );

        $replaced_output = preg_replace_callback(
            '/>[^<]*</s',
            function ( $matches ) use ( $source_text, $translated_text ) {
                return str_replace( $source_text, $translated_text, $matches[0] );
            },
            $output
        );

        if ( null !== $replaced_output ) {
            $output = $replaced_output;
        }

        $output = str_replace(
            [
                '="' . esc_attr( $source ) . '"',
                "='" . esc_attr( $source ) . "'",
            ],
            [
                '="' . esc_attr( $translated ) . '"',
                "='" . esc_attr( $translated ) . "'",
            ],
            $output
        );
    }

    return $output;
}
```

## Commit History

Relevant branch:

```text
pagewise-directorist-ate-ui
```

Relevant commits:

```text
ff3d9a9 Add page shortcode UI strings to WPML ATE
7b4e48b Refresh page ATE jobs when shortcode UI changes
```
