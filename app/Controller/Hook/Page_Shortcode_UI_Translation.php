<?php
/**
 * Page-level Directorist shortcode UI translation support.
 *
 * Adds the static UI text rendered by Directorist shortcodes to the page ATE
 * job, without sending dynamic listing/result data.
 *
 * @package Directorist_WPML_Integration
 */

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Page_Shortcode_UI_Translation {

	const META_KEY      = '_directorist_wpml_page_ui_strings';
	const META_HASH_KEY = '_directorist_wpml_page_ui_strings_hash';

	/**
	 * Maximum subkey length that leaves room for WPML's field metadata suffixes.
	 *
	 * WPML stores these keys in icl_translate.field_type (varchar(160)) with a
	 * 42-character custom-field prefix plus internal "-name" / "-type" suffixes.
	 */
	const MAX_ATE_SUBKEY_LENGTH = 110;

	/**
	 * Shortcodes whose visible static UI text can belong to a page.
	 *
	 * @var array
	 */
	private $shortcode_contexts = [
		'directorist_all_listing'    => 'all_listing',
		'directorist_search_result'  => 'search_result',
		'directorist_search_listing' => 'search_listing',
		'directorist_add_listing'    => 'add_listing',
		'directorist_all_categories' => 'all_categories',
		'directorist_all_locations'  => 'all_locations',
		'directorist_category'       => 'category',
		'directorist_location'       => 'location',
		'directorist_tag'            => 'tag',
		'directorist_signin_signup'  => 'signin_signup',
		'directorist_user_login'     => 'signin_signup',
		'directorist_custom_registration' => 'signin_signup',
		'directorist_user_dashboard' => 'user_dashboard',
		'directorist_author_profile' => 'author_profile',
		'directorist_all_authors'    => 'all_authors',
		'directorist_checkout'       => 'checkout',
		'directorist_payment_receipt' => 'payment_receipt',
		'directorist_transaction_failure' => 'transaction_failure',
	];

	/**
	 * Gutenberg block names mapped to Directorist shortcode contexts.
	 *
	 * @var array
	 */
	private $block_contexts = [
		'directorist/all-listing'     => 'all_listing',
		'directorist/search-result'   => 'search_result',
		'directorist/search-listing'  => 'search_listing',
		'directorist/search-modal'    => 'search_listing',
		'directorist/add-listing'     => 'add_listing',
		'directorist/all-categories'  => 'all_categories',
		'directorist/all-locations'   => 'all_locations',
		'directorist/category'        => 'category',
		'directorist/location'        => 'location',
		'directorist/tag'             => 'tag',
		'directorist/signin-signup'   => 'signin_signup',
		'directorist/user-dashboard'  => 'user_dashboard',
		'directorist/author-profile'   => 'author_profile',
		'directorist/all-authors'      => 'all_authors',
		'directorist/checkout'         => 'checkout',
		'directorist/payment-receipt'  => 'payment_receipt',
		'directorist/transaction-failure' => 'transaction_failure',
		'directorist/account-button'   => 'account_button',
	];

	/**
	 * Elementor widget names mapped to their native Directorist contexts.
	 *
	 * Single-listing field widgets are intentionally excluded: their labels
	 * belong to the listing and Directory Builder ATE packages, not page ATE.
	 *
	 * @var array
	 */
	private $elementor_widget_contexts = [
		'directorist_add_listing'         => 'add_listing',
		'directorist_all_authors'         => 'all_authors',
		'directorist_all_categories'      => 'all_categories',
		'directorist_all_listing'         => 'all_listing',
		'directorist_all_locations'       => 'all_locations',
		'directorist_author_profile'      => 'author_profile',
		'directorist_checkout'            => 'checkout',
		'directorist_payment_receipt'     => 'payment_receipt',
		'directorist_search_listing'      => 'search_listing',
		'directorist_search_result'       => 'search_result',
		'directorist_category'            => 'category',
		'directorist_location'            => 'location',
		'directorist_tag'                 => 'tag',
		'directorist_transaction_failure' => 'transaction_failure',
		'directorist_user_dashboard'      => 'user_dashboard',
		'directorist_user_login'          => 'signin_signup',
	];

	/**
	 * Cached replacement map for the current request.
	 *
	 * @var array|null
	 */
	private $current_page_map = null;

	/**
	 * Cached Elementor taxonomy slug translations for the current request.
	 *
	 * @var array
	 */
	private $elementor_taxonomy_slug_cache = [];

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'sync_source_pages' ], 50 );
		add_action( 'admin_init', [ $this, 'sync_translated_page_ui_meta_after_ate_completion' ], 60 );
		add_action( 'save_post_page', [ $this, 'sync_page_on_save' ], 40, 3 );
		add_action( 'elementor/editor/after_save', [ $this, 'sync_elementor_page_on_save' ], 40, 2 );
		add_action( 'wpml_pro_translation_completed', [ $this, 'sync_page_ui_meta_after_wpml_translation_completed' ], 20, 3 );
		add_filter( 'do_shortcode_tag', [ $this, 'translate_shortcode_output' ], 20, 4 );
		add_filter( 'render_block', [ $this, 'translate_block_output' ], 20, 2 );
		add_action( 'elementor/frontend/widget/before_render', [ $this, 'translate_elementor_widget_taxonomy_settings' ], 20 );
		add_filter( 'elementor/widget/render_content', [ $this, 'translate_elementor_widget_output' ], 20, 2 );
	}

	/**
	 * Sync hidden page UI meta for source-language pages in admin.
	 *
	 * @return void
	 */
	public function sync_source_pages() {
		if ( ! is_admin() || wp_doing_ajax() || ! current_user_can( 'edit_pages' ) || ! $this->should_sync_admin_pages() ) {
			return;
		}

		global $wpdb;

		$page_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				WHERE p.post_type = %s
					AND p.post_status NOT IN ('trash', 'auto-draft')
					AND (
						p.post_content LIKE %s
						OR p.post_content LIKE %s
						OR EXISTS (
							SELECT 1
							FROM {$wpdb->postmeta} pm
							WHERE pm.post_id = p.ID
								AND pm.meta_key = '_elementor_data'
								AND pm.meta_value LIKE %s
						)
					)",
				'page',
				'%[directorist_%',
				'%wp:directorist/%',
				'%directorist_%'
			)
		);

		foreach ( wp_parse_id_list( $page_ids ) as $page_id ) {
			$this->sync_page_ui_meta( $page_id );
		}
	}

	/**
	 * Check whether the current admin screen can create/update page ATE jobs.
	 *
	 * @return bool
	 */
	private function should_sync_admin_pages() {
		global $pagenow;

		$pagenow = is_string( $pagenow ) ? $pagenow : '';

		if ( 'post.php' === $pagenow ) {
			$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			return $post_id > 0 && 'page' === get_post_type( $post_id );
		}

		if ( 'post-new.php' === $pagenow || 'edit.php' === $pagenow ) {
			$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			return 'page' === $post_type;
		}

		if ( 'admin.php' === $pagenow ) {
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			return false !== strpos( $page, 'tm/menu/main.php' )
				|| false !== strpos( $page, 'sitepress-multilingual-cms' )
				|| false !== strpos( $page, 'wpml' );
		}

		return (bool) apply_filters( 'directorist_wpml_should_sync_page_ui_strings', false );
	}

	/**
	 * Sync page UI meta when a page is saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 * @return void
	 */
	public function sync_page_on_save( $post_id, $post, $update ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || 'page' !== $post->post_type ) {
			return;
		}

		$this->sync_page_ui_meta( (int) $post_id, (string) $post->post_content );
	}

	/**
	 * Sync after Elementor has persisted its page data.
	 *
	 * @param int   $post_id     Post ID.
	 * @param array $editor_data Elementor document data.
	 * @return void
	 */
	public function sync_elementor_page_on_save( $post_id, $editor_data ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || 'page' !== get_post_type( $post_id ) ) {
			return;
		}

		$this->sync_page_ui_meta( $post_id, null, $editor_data );
	}

	/**
	 * Persist completed ATE page UI translations into translated page meta.
	 *
	 * WPML/ATE can complete the hidden custom-field strings without immediately
	 * refreshing the translated page's `_directorist_wpml_page_ui_strings` meta.
	 * Directorist page UI replacement reads that translated meta at render time,
	 * so keep this as a bounded admin-only post-ATE import for the exact
	 * completed page job.
	 *
	 * @return void
	 */
	public function sync_translated_page_ui_meta_after_ate_completion() {
		if ( ! $this->is_wpml_active() || ! $this->is_ate_completion_request() || ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		$editor_job_id = $this->get_ate_completion_editor_job_id();
		if ( $editor_job_id <= 0 ) {
			return;
		}

		$job = $this->get_completed_page_job_by_editor_job_id( $editor_job_id );
		if ( empty( $job->job_id ) || empty( $job->source_page_id ) || empty( $job->translated_page_id ) || empty( $job->language_code ) ) {
			return;
		}

		$source_page_id     = absint( $job->source_page_id );
		$translated_page_id = absint( $job->translated_page_id );

		if ( $source_page_id <= 0 || $translated_page_id <= 0 || $source_page_id === $translated_page_id ) {
			return;
		}

		$this->sync_page_ui_meta( $source_page_id );
		$this->sync_translated_page_ui_meta_from_job(
			$source_page_id,
			$translated_page_id,
			absint( $job->job_id ),
			(string) $job->language_code
		);
	}

	/**
	 * Persist translated page UI meta from WPML's completed translation payload.
	 *
	 * This is the primary sync point because ATE can return completed field
	 * values in this hook before/without the local icl_translate rows reflecting
	 * those values.
	 *
	 * @param int      $new_post_id Translated post ID.
	 * @param array    $fields      Completed translation fields.
	 * @param \stdClass $job        WPML job object.
	 * @return void
	 */
	public function sync_page_ui_meta_after_wpml_translation_completed( $new_post_id, array $fields, $job ) {
		if ( ! $this->is_wpml_active() || ! is_object( $job ) || empty( $job->element_type_prefix ) || 'post' !== $job->element_type_prefix ) {
			return;
		}

		$translated_page_id = absint( $new_post_id );
		$source_page_id     = ! empty( $job->original_doc_id ) ? absint( $job->original_doc_id ) : 0;
		$language_code      = ! empty( $job->language_code ) ? (string) $job->language_code : '';

		if ( $source_page_id <= 0 || $translated_page_id <= 0 || $source_page_id === $translated_page_id || 'page' !== get_post_type( $source_page_id ) || 'page' !== get_post_type( $translated_page_id ) ) {
			return;
		}

		$translations = $this->get_page_ui_translations_from_completed_fields( $fields );
		if ( empty( $translations ) ) {
			return;
		}

		$this->sync_page_ui_meta( $source_page_id );
		$this->sync_translated_page_ui_meta_from_translations( $source_page_id, $translated_page_id, $translations, $language_code );
	}

	/**
	 * Translate visible static strings inside Directorist shortcode output.
	 *
	 * @param string $output Shortcode output.
	 * @param string $tag    Shortcode tag.
	 * @param array  $attr   Shortcode attributes.
	 * @param array  $m      Regex match data.
	 * @return string
	 */
	public function translate_shortcode_output( $output, $tag, $attr, $m ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( empty( $output ) || ! is_string( $output ) || empty( $this->shortcode_contexts[ $tag ] ) ) {
			return $output;
		}

		return $this->translate_output_for_context( $output, $this->shortcode_contexts[ $tag ] );
	}

	/**
	 * Translate visible static strings in a Directorist Gutenberg block.
	 *
	 * Directorist's server-rendered blocks can bypass do_shortcode_tag. Apply
	 * the same exact page ATE map to recognized block output without registering
	 * strings or writing to the database on the frontend.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block data.
	 * @return string
	 */
	public function translate_block_output( $block_content, $block ) {
		$block_name = ! empty( $block['blockName'] ) ? (string) $block['blockName'] : '';

		if ( empty( $block_content ) || ! is_string( $block_content ) || empty( $this->block_contexts[ $block_name ] ) ) {
			return $block_content;
		}

		return $this->translate_output_for_context( $block_content, $this->block_contexts[ $block_name ] );
	}

	/**
	 * Translate visible static strings in a native Directorist Elementor widget.
	 *
	 * AddonsKit invokes registered shortcode callbacks directly instead of using
	 * do_shortcode(), so do_shortcode_tag cannot see that output. Elementor's
	 * final widget-content filter provides the same bounded render-time point
	 * without registering strings or writing to the database on the frontend.
	 *
	 * @param string $widget_content Rendered widget HTML.
	 * @param object $widget         Elementor widget instance.
	 * @return string
	 */
	public function translate_elementor_widget_output( $widget_content, $widget ) {
		if ( empty( $widget_content ) || ! is_string( $widget_content ) || ! is_object( $widget ) || ! is_callable( [ $widget, 'get_name' ] ) ) {
			return $widget_content;
		}

		$widget_name = (string) $widget->get_name();
		if ( empty( $this->elementor_widget_contexts[ $widget_name ] ) ) {
			return $widget_content;
		}

		return $this->translate_output_for_context( $widget_content, $this->elementor_widget_contexts[ $widget_name ] );
	}

	/**
	 * Translate slug-based Directorist taxonomy selectors before Elementor renders.
	 *
	 * AddonsKit stores its SELECT2 taxonomy values as slugs. WPML's
	 * `taxonomy-ids` Elementor conversion intentionally converts numeric IDs
	 * only, so translate those slug values in memory for secondary languages.
	 *
	 * @param object $widget Elementor widget instance.
	 * @return void
	 */
	public function translate_elementor_widget_taxonomy_settings( $widget ) {
		if ( ! is_object( $widget ) || ! is_callable( [ $widget, 'get_name' ] ) || ! is_callable( [ $widget, 'get_settings' ] ) || ! is_callable( [ $widget, 'set_settings' ] ) ) {
			return;
		}

		$widget_name = (string) $widget->get_name();
		if ( empty( $this->elementor_widget_contexts[ $widget_name ] ) || ! $this->is_wpml_active() ) {
			return;
		}

		$current_language = (string) apply_filters( 'wpml_current_language', '' );
		$source_language  = (string) apply_filters( 'wpml_default_language', '' );

		if ( '' === $current_language || '' === $source_language || $current_language === $source_language ) {
			return;
		}

		$settings = $widget->get_settings();
		if ( ! is_array( $settings ) ) {
			return;
		}

		$taxonomy_settings = [
			'type'                   => $this->get_directory_taxonomy(),
			'default_type'           => $this->get_directory_taxonomy(),
			'directory_type'         => $this->get_directory_taxonomy(),
			'default_directory_type' => $this->get_directory_taxonomy(),
			'listing_type'           => $this->get_directory_taxonomy(),
			'cat'                    => defined( 'ATBDP_CATEGORY' ) ? ATBDP_CATEGORY : 'at_biz_dir-category',
			'category'               => defined( 'ATBDP_CATEGORY' ) ? ATBDP_CATEGORY : 'at_biz_dir-category',
			'loc'                    => defined( 'ATBDP_LOCATION' ) ? ATBDP_LOCATION : 'at_biz_dir-location',
			'location'               => defined( 'ATBDP_LOCATION' ) ? ATBDP_LOCATION : 'at_biz_dir-location',
			'tag'                    => defined( 'ATBDP_TAGS' ) ? ATBDP_TAGS : 'at_biz_dir-tags',
		];

		foreach ( $taxonomy_settings as $setting_key => $taxonomy ) {
			if ( ! array_key_exists( $setting_key, $settings ) ) {
				continue;
			}

			$translated_value = $this->translate_elementor_taxonomy_setting_value(
				$settings[ $setting_key ],
				$taxonomy,
				$source_language,
				$current_language
			);

			if ( $translated_value !== $settings[ $setting_key ] ) {
				$widget->set_settings( $setting_key, $translated_value );
			}
		}
	}

	/**
	 * Translate one Elementor taxonomy setting while preserving its data shape.
	 *
	 * @param mixed  $value            Elementor setting value.
	 * @param string $taxonomy         Taxonomy name.
	 * @param string $source_language  Source language code.
	 * @param string $target_language  Target language code.
	 * @return mixed
	 */
	private function translate_elementor_taxonomy_setting_value( $value, $taxonomy, $source_language, $target_language ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->translate_elementor_taxonomy_setting_value( $item, $taxonomy, $source_language, $target_language );
			}

			return $value;
		}

		if ( ! is_string( $value ) || '' === trim( $value ) || is_numeric( $value ) ) {
			return $value;
		}

		if ( false !== strpos( $value, ',' ) ) {
			$items = array_map( 'trim', explode( ',', $value ) );
			foreach ( $items as $key => $item ) {
				$items[ $key ] = $this->translate_elementor_taxonomy_slug( $item, $taxonomy, $source_language, $target_language );
			}

			return implode( ',', $items );
		}

		return $this->translate_elementor_taxonomy_slug( trim( $value ), $taxonomy, $source_language, $target_language );
	}

	/**
	 * Resolve one source taxonomy slug to its linked target-language slug.
	 *
	 * @param string $slug             Source term slug.
	 * @param string $taxonomy         Taxonomy name.
	 * @param string $source_language  Source language code.
	 * @param string $target_language  Target language code.
	 * @return string
	 */
	private function translate_elementor_taxonomy_slug( $slug, $taxonomy, $source_language, $target_language ) {
		$cache_key = implode( '|', [ $source_language, $target_language, $taxonomy, $slug ] );
		if ( array_key_exists( $cache_key, $this->elementor_taxonomy_slug_cache ) ) {
			return $this->elementor_taxonomy_slug_cache[ $cache_key ];
		}

		global $wpdb;

		$translated_slug = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT target_term.slug
				FROM {$wpdb->terms} source_term
				INNER JOIN {$wpdb->term_taxonomy} source_tax
					ON source_tax.term_id = source_term.term_id
					AND source_tax.taxonomy = %s
				INNER JOIN {$wpdb->prefix}icl_translations source_translation
					ON source_translation.element_id = source_tax.term_taxonomy_id
					AND source_translation.element_type = %s
					AND source_translation.language_code = %s
				INNER JOIN {$wpdb->prefix}icl_translations target_translation
					ON target_translation.trid = source_translation.trid
					AND target_translation.language_code = %s
				INNER JOIN {$wpdb->term_taxonomy} target_tax
					ON target_tax.term_taxonomy_id = target_translation.element_id
				INNER JOIN {$wpdb->terms} target_term
					ON target_term.term_id = target_tax.term_id
				WHERE source_term.slug = %s
				LIMIT 1",
				$taxonomy,
				WPML_Helper::get_wpml_element_type( $taxonomy ),
				$source_language,
				$target_language,
				$slug
			)
		);

		$this->elementor_taxonomy_slug_cache[ $cache_key ] = is_string( $translated_slug ) && '' !== $translated_slug
			? $translated_slug
			: $slug;

		return $this->elementor_taxonomy_slug_cache[ $cache_key ];
	}

	/**
	 * Apply the translated page map to one native Directorist UI context.
	 *
	 * @param string $output  Rendered HTML.
	 * @param string $context Directorist page context.
	 * @return string
	 */
	private function translate_output_for_context( $output, $context ) {
		$map = $this->get_current_page_translation_map();
		if ( empty( $map ) ) {
			return $output;
		}

		$map     = $this->apply_shortcode_native_fallback_aliases( $map, $context );
		$map     = $this->apply_shortcode_dynamic_output_aliases( $map, $context, $output );
		$output  = $this->replace_shortcode_dynamic_fragments( $output, $map, $context );

		return $this->replace_output_strings( $output, $map );
	}

	/**
	 * Sync translatable UI strings for one source page.
	 *
	 * @param int         $post_id      Post ID.
	 * @param string|null $post_content Optional post content.
	 * @return void
	 */
	private function sync_page_ui_meta( $post_id, $post_content = null, $elementor_data = null ) {
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

		if ( null === $elementor_data ) {
			$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		}

		$strings = $this->collect_page_ui_strings( (string) $post_content, $elementor_data );
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

	/**
	 * Check whether the current admin request is WPML returning from ATE completion.
	 *
	 * @return bool
	 */
	private function is_ate_completion_request() {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return false;
		}

		$referer  = isset( $_GET['referer'] ) ? sanitize_key( wp_unslash( $_GET['referer'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$complete = isset( $_GET['complete'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['complete'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'ate' !== $referer || ! in_array( $complete, [ '1', 'true', 'yes' ], true ) ) {
			return false;
		}

		return $this->get_ate_completion_editor_job_id() > 0;
	}

	/**
	 * Get ATE editor job ID from WPML's return URL.
	 *
	 * @return int
	 */
	private function get_ate_completion_editor_job_id() {
		foreach ( [ 'ate_job_id', 'ate_original_id' ] as $request_key ) {
			$value = isset( $_GET[ $request_key ] ) ? absint( wp_unslash( $_GET[ $request_key ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $value > 0 ) {
				return $value;
			}
		}

		return 0;
	}

	/**
	 * Resolve a completed ATE editor job to a translated page job.
	 *
	 * @param int $editor_job_id ATE editor job ID.
	 * @return object|null
	 */
	private function get_completed_page_job_by_editor_job_id( $editor_job_id ) {
		global $wpdb;

		$editor_job_id = absint( $editor_job_id );
		if ( $editor_job_id <= 0 ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT tj.job_id,
					target.element_id AS translated_page_id,
					target.language_code,
					source.element_id AS source_page_id
				FROM {$wpdb->prefix}icl_translate_job tj
				INNER JOIN {$wpdb->prefix}icl_translation_status ts
					ON ts.rid = tj.rid
				INNER JOIN {$wpdb->prefix}icl_translations target
					ON target.translation_id = ts.translation_id
					AND target.element_type = %s
					AND target.source_language_code IS NOT NULL
				INNER JOIN {$wpdb->prefix}icl_translations source
					ON source.trid = target.trid
					AND source.element_type = target.element_type
					AND source.source_language_code IS NULL
				WHERE tj.editor_job_id = %d
					AND tj.translated = 1
					AND ts.needs_update = 0
				ORDER BY tj.job_id DESC
				LIMIT 1",
				'post_page',
				$editor_job_id
			)
		);
	}

	/**
	 * Sync completed ATE custom-field values into one translated page meta array.
	 *
	 * @param int    $source_page_id     Source/default page ID.
	 * @param int    $translated_page_id Translated page ID.
	 * @param int    $job_id             Local WPML translation job ID.
	 * @param string $language_code      Target language code.
	 * @return bool True when translated page meta changed.
	 */
	private function sync_translated_page_ui_meta_from_job( $source_page_id, $translated_page_id, $job_id, $language_code ) {
		$source_page_id     = absint( $source_page_id );
		$translated_page_id = absint( $translated_page_id );
		$job_id             = absint( $job_id );

		if ( $source_page_id <= 0 || $translated_page_id <= 0 || $job_id <= 0 || 'page' !== get_post_type( $source_page_id ) || 'page' !== get_post_type( $translated_page_id ) ) {
			return false;
		}

		$source_strings = get_post_meta( $source_page_id, self::META_KEY, true );
		if ( ! is_array( $source_strings ) || empty( $source_strings ) ) {
			return false;
		}

		$job_translations = $this->get_page_ui_job_translations( $job_id );
		if ( empty( $job_translations ) ) {
			return false;
		}

		return $this->sync_translated_page_ui_meta_from_translations( $source_page_id, $translated_page_id, $job_translations, $language_code );
	}

	/**
	 * Sync completed page UI custom-field values into one translated page meta array.
	 *
	 * @param int    $source_page_id     Source/default page ID.
	 * @param int    $translated_page_id Translated page ID.
	 * @param array  $job_translations   Translated values keyed by page UI meta key.
	 * @param string $language_code      Target language code.
	 * @return bool True when translated page meta changed.
	 */
	private function sync_translated_page_ui_meta_from_translations( $source_page_id, $translated_page_id, array $job_translations, $language_code ) {
		$source_page_id     = absint( $source_page_id );
		$translated_page_id = absint( $translated_page_id );

		if ( $source_page_id <= 0 || $translated_page_id <= 0 || empty( $job_translations ) || 'page' !== get_post_type( $source_page_id ) || 'page' !== get_post_type( $translated_page_id ) ) {
			return false;
		}

		$source_strings = get_post_meta( $source_page_id, self::META_KEY, true );
		if ( ! is_array( $source_strings ) || empty( $source_strings ) ) {
			return false;
		}

		$translated_strings = get_post_meta( $translated_page_id, self::META_KEY, true );
		if ( ! is_array( $translated_strings ) ) {
			$translated_strings = [];
		}

		$pruned_translated_strings = array_intersect_key( $translated_strings, $source_strings );
		$updated                    = $pruned_translated_strings !== $translated_strings;
		$translated_strings        = $pruned_translated_strings;

		foreach ( $source_strings as $key => $source_value ) {
			if ( ! is_string( $source_value ) || ! array_key_exists( $key, $job_translations ) ) {
				continue;
			}

			$translated_value = $job_translations[ $key ];
			$current_value         = array_key_exists( $key, $translated_strings ) ? $translated_strings[ $key ] : '';
			$current_is_placeholder = $this->is_language_prefixed_placeholder( $current_value, $language_code );
			$same_as_source         = $this->strings_match_after_decoding( $source_value, $translated_value );

			if ( ! $this->is_usable_page_ui_translation( $source_value, $translated_value, $language_code ) && ! ( $same_as_source && $current_is_placeholder ) ) {
				continue;
			}

			if ( ! $this->should_replace_page_ui_translation( $source_value, $current_value, $language_code ) ) {
				continue;
			}

			$translated_strings[ $key ] = trim( $translated_value );
			$updated                   = true;
		}

		if ( ! $updated ) {
			return false;
		}

		update_post_meta( $translated_page_id, self::META_KEY, $translated_strings );
		clean_post_cache( $translated_page_id );

		if ( (int) get_queried_object_id() === $translated_page_id ) {
			$this->current_page_map = null;
		}

		return true;
	}

	/**
	 * Read page UI values from WPML's completed translation hook payload.
	 *
	 * @param array $fields Completed translation fields.
	 * @return array
	 */
	private function get_page_ui_translations_from_completed_fields( array $fields ) {
		$field_prefix = 'field-' . self::META_KEY . '-0-';
		$translations = [];

		foreach ( $fields as $field_id => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_type = ! empty( $field['field_type'] ) ? (string) $field['field_type'] : (string) $field_id;
			if ( 0 !== strpos( $field_type, $field_prefix ) ) {
				continue;
			}

			$key = substr( $field_type, strlen( $field_prefix ) );
			if ( '' === $key || $this->has_suffix( $key, '-name' ) || $this->has_suffix( $key, '-type' ) ) {
				continue;
			}

			$value = '';
			if ( isset( $field['data'] ) && is_string( $field['data'] ) ) {
				$value = $field['data'];
			} elseif ( isset( $field['field_data_translated'] ) && is_string( $field['field_data_translated'] ) ) {
				$value  = $field['field_data_translated'];
				$format = isset( $field['field_format'] ) ? (string) $field['field_format'] : '';
				$value  = $this->decode_wpml_translation_field( $value, $format );
			}

			$translations[ $key ] = $value;
		}

		return $translations;
	}

	/**
	 * Read translated page UI custom-field values from one completed WPML job.
	 *
	 * @param int $job_id Local WPML translation job ID.
	 * @return array
	 */
	private function get_page_ui_job_translations( $job_id ) {
		global $wpdb;

		$job_id       = absint( $job_id );
		$field_prefix = 'field-' . self::META_KEY . '-0-';

		if ( $job_id <= 0 ) {
			return [];
		}

		$fields = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT field_type, field_data_translated, field_format
				FROM {$wpdb->prefix}icl_translate
				WHERE job_id = %d
					AND field_translate = 1
					AND field_type LIKE %s",
				$job_id,
				$wpdb->esc_like( $field_prefix ) . '%'
			)
		);

		if ( empty( $fields ) ) {
			return [];
		}

		$translations = [];
		foreach ( $fields as $field ) {
			$field_type = isset( $field->field_type ) ? (string) $field->field_type : '';
			if ( 0 !== strpos( $field_type, $field_prefix ) ) {
				continue;
			}

			$key = substr( $field_type, strlen( $field_prefix ) );
			if ( '' === $key || $this->has_suffix( $key, '-name' ) || $this->has_suffix( $key, '-type' ) ) {
				continue;
			}

			$field_data = isset( $field->field_data_translated ) ? (string) $field->field_data_translated : '';
			$format     = isset( $field->field_format ) ? (string) $field->field_format : '';

			$translations[ $key ] = $this->decode_wpml_translation_field( $field_data, $format );
		}

		return $translations;
	}

	/**
	 * Decode WPML icl_translate field data.
	 *
	 * @param string $value  Raw field value.
	 * @param string $format WPML field format.
	 * @return string
	 */
	private function decode_wpml_translation_field( $value, $format = '' ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		if ( 'base64' !== $format ) {
			return $value;
		}

		$decoded = base64_decode( $value, true );
		if ( false === $decoded ) {
			return $value;
		}

		foreach ( [ 'gzuncompress', 'gzdecode', 'gzinflate' ] as $decoder ) {
			if ( ! function_exists( $decoder ) ) {
				continue;
			}

			$inflated = @$decoder( $decoded ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_string( $inflated ) ) {
				return $inflated;
			}
		}

		if ( strlen( $decoded ) > 6 && function_exists( 'gzinflate' ) ) {
			$inflated = @gzinflate( substr( $decoded, 2, -4 ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_string( $inflated ) ) {
				return $inflated;
			}
		}

		return $decoded;
	}

	/**
	 * Check whether a translated field value can be used.
	 *
	 * @param string $source_value     Source value.
	 * @param mixed  $translated_value Translated value.
	 * @param string $language_code    Target language code.
	 * @return bool
	 */
	private function is_usable_page_ui_translation( $source_value, $translated_value, $language_code = '' ) {
		return is_string( $translated_value )
			&& '' !== trim( $translated_value )
			&& ! $this->strings_match_after_decoding( $source_value, $translated_value )
			&& ! $this->is_language_prefixed_placeholder( $translated_value, $language_code );
	}

	/**
	 * Check if a translated page meta value may be replaced.
	 *
	 * @param string $source_value  Source value.
	 * @param mixed  $current_value Existing translated meta value.
	 * @param string $language_code Target language code.
	 * @return bool
	 */
	private function should_replace_page_ui_translation( $source_value, $current_value, $language_code = '' ) {
		if ( ! is_string( $current_value ) || '' === trim( $current_value ) ) {
			return true;
		}

		return $this->strings_match_after_decoding( $source_value, $current_value )
			|| $this->is_language_prefixed_placeholder( $current_value, $language_code );
	}

	/**
	 * Detect QA/import placeholders such as "NL: Title" for the target language.
	 *
	 * @param mixed  $value         Value to check.
	 * @param string $language_code Target language code.
	 * @return bool
	 */
	private function is_language_prefixed_placeholder( $value, $language_code = '' ) {
		if ( ! is_string( $value ) || '' === trim( $value ) || '' === trim( $language_code ) ) {
			return false;
		}

		$value           = trim( $this->decode_html_value( $value ) );
		$language_prefix = strtoupper( str_replace( '_', '-', trim( $language_code ) ) );

		return 0 === strpos( $value, $language_prefix . ': ' )
			|| 0 === strpos( $value, $language_prefix . '-QA: ' );
	}

	/**
	 * Compare strings after decoding HTML entities and trimming whitespace.
	 *
	 * @param string $left  First value.
	 * @param string $right Second value.
	 * @return bool
	 */
	private function strings_match_after_decoding( $left, $right ) {
		$left  = trim( $this->decode_html_value( $left ) );
		$right = trim( $this->decode_html_value( $right ) );

		return $left === $right;
	}

	/**
	 * Check if a string ends with a suffix without requiring PHP 8 helpers.
	 *
	 * @param string $value  Value.
	 * @param string $suffix Suffix.
	 * @return bool
	 */
	private function has_suffix( $value, $suffix ) {
		$value  = (string) $value;
		$suffix = (string) $suffix;

		return '' === $suffix || substr( $value, -strlen( $suffix ) ) === $suffix;
	}

	/**
	 * Collect all page-related Directorist UI strings from shortcodes/blocks.
	 *
	 * @param string $content Page content.
	 * @return array
	 */
	private function collect_page_ui_strings( $content, $elementor_data = '' ) {
		$strings = [];

		foreach ( $this->extract_shortcodes( $content ) as $shortcode ) {
			$this->collect_shortcode_strings( $strings, $shortcode['context'], $shortcode['attrs'] );
		}

		foreach ( $this->extract_blocks( $content ) as $block ) {
			$this->collect_shortcode_strings( $strings, $block['context'], $block['attrs'] );
		}

		foreach ( $this->extract_elementor_widgets( $elementor_data ) as $widget ) {
			$this->collect_shortcode_strings( $strings, $widget['context'], $widget['attrs'] );
		}

		return $strings;
	}

	/**
	 * Extract supported Directorist shortcodes from page content.
	 *
	 * @param string $content Page content.
	 * @return array
	 */
	private function extract_shortcodes( $content ) {
		$shortcodes = [];

		if ( false === strpos( $content, '[directorist_' ) ) {
			return $shortcodes;
		}

		$tags    = array_keys( $this->shortcode_contexts );
		$pattern = get_shortcode_regex( $tags );

		if ( ! preg_match_all( '/' . $pattern . '/s', $content, $matches, PREG_SET_ORDER ) ) {
			return $shortcodes;
		}

		foreach ( $matches as $match ) {
			$tag = isset( $match[2] ) ? (string) $match[2] : '';
			if ( empty( $this->shortcode_contexts[ $tag ] ) ) {
				continue;
			}

			$attrs = isset( $match[3] ) ? shortcode_parse_atts( $match[3] ) : [];
			if ( ! is_array( $attrs ) ) {
				$attrs = [];
			}

			$shortcodes[] = [
				'tag'     => $tag,
				'context' => $this->shortcode_contexts[ $tag ],
				'attrs'   => $attrs,
			];
		}

		return $shortcodes;
	}

	/**
	 * Extract supported Directorist blocks from page content.
	 *
	 * @param string $content Page content.
	 * @return array
	 */
	private function extract_blocks( $content ) {
		$blocks = [];

		if ( false === strpos( $content, 'wp:directorist/' ) || ! function_exists( 'parse_blocks' ) ) {
			return $blocks;
		}

		$this->collect_blocks_recursive( parse_blocks( $content ), $blocks );

		return $blocks;
	}

	/**
	 * Recursively collect Directorist blocks.
	 *
	 * @param array $parsed_blocks Parsed block data.
	 * @param array $blocks        Collected blocks.
	 * @return void
	 */
	private function collect_blocks_recursive( $parsed_blocks, &$blocks ) {
		foreach ( (array) $parsed_blocks as $block ) {
			if ( ! empty( $block['blockName'] ) && ! empty( $this->block_contexts[ $block['blockName'] ] ) ) {
				$blocks[] = [
					'context' => $this->block_contexts[ $block['blockName'] ],
					'attrs'   => ! empty( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : [],
				];
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->collect_blocks_recursive( $block['innerBlocks'], $blocks );
			}
		}
	}

	/**
	 * Extract supported Directorist Elementor widgets from saved page data.
	 *
	 * @param string|array $elementor_data Saved Elementor JSON or decoded data.
	 * @return array
	 */
	private function extract_elementor_widgets( $elementor_data ) {
		if ( is_string( $elementor_data ) ) {
			$elementor_data = json_decode( $elementor_data, true );
		}

		if ( ! is_array( $elementor_data ) ) {
			return [];
		}

		$widgets = [];
		$this->collect_elementor_widgets_recursive( $elementor_data, $widgets );

		return $widgets;
	}

	/**
	 * Recursively collect native Directorist Elementor widget contexts.
	 *
	 * @param array $elements Elementor elements.
	 * @param array $widgets  Collected widgets.
	 * @return void
	 */
	private function collect_elementor_widgets_recursive( $elements, &$widgets ) {
		foreach ( (array) $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			$widget_name = ! empty( $element['widgetType'] ) ? (string) $element['widgetType'] : '';
			if ( isset( $this->elementor_widget_contexts[ $widget_name ] ) ) {
				$settings = ! empty( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : [];
				$widgets[] = [
					'context' => $this->elementor_widget_contexts[ $widget_name ],
					'attrs'   => $this->get_elementor_directory_attrs( $settings ),
				];
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->collect_elementor_widgets_recursive( $element['elements'], $widgets );
			}
		}
	}

	/**
	 * Keep only directory selectors needed to resolve builder-owned UI.
	 *
	 * Elementor's own visible text settings are handled by wpml-config.xml.
	 * Repeating them inside the hidden page UI field would create duplicate ATE
	 * rows, so this collector only carries native directory selection context.
	 *
	 * @param array $settings Elementor widget settings.
	 * @return array
	 */
	private function get_elementor_directory_attrs( $settings ) {
		$attrs = [];

		foreach ( [ 'directory_type', 'default_directory_type', 'listing_type', 'default_type', 'type' ] as $key ) {
			if ( isset( $settings[ $key ] ) && ( is_scalar( $settings[ $key ] ) || is_array( $settings[ $key ] ) ) ) {
				$attrs[ $key ] = $settings[ $key ];
			}
		}

		return $attrs;
	}

	/**
	 * Collect strings for one Directorist page shortcode context.
	 *
	 * @param array  $strings Collected strings.
	 * @param string $context Shortcode context.
	 * @param array  $attrs   Shortcode/block attributes.
	 * @return void
	 */
	private function collect_shortcode_strings( &$strings, $context, $attrs ) {
		$directory_id = $this->get_directory_id_from_attrs( $attrs );

		switch ( $context ) {
			case 'all_listing':
			case 'category':
			case 'location':
			case 'tag':
				$this->collect_listing_archive_strings( $strings, $context, $directory_id, false );
				break;

			case 'search_result':
				$this->collect_listing_archive_strings( $strings, $context, $directory_id, true );
				break;

			case 'search_listing':
				$this->collect_search_listing_strings( $strings, $context, $directory_id, $attrs );
				break;

			case 'add_listing':
				$this->collect_add_listing_strings( $strings, $context, $directory_id );
				break;

			case 'all_categories':
			case 'all_locations':
				$this->add_string( $strings, $context, 'static_no_results_found', 'No Results found!' );
				break;

			case 'signin_signup':
				$this->collect_account_strings( $strings, $context, $attrs );
				break;

			case 'account_button':
				$this->collect_account_button_strings( $strings, $context );
				break;

			case 'user_dashboard':
				$this->collect_dashboard_strings( $strings, $context );
				break;

			case 'author_profile':
				$this->collect_author_profile_strings( $strings, $context );
				break;

			case 'all_authors':
				$this->collect_all_authors_strings( $strings, $context );
				break;

			case 'checkout':
				$this->collect_checkout_strings( $strings, $context );
				break;

			case 'payment_receipt':
				$this->collect_payment_receipt_strings( $strings, $context );
				break;

			case 'transaction_failure':
				$this->add_string( $strings, $context, 'failure_message', 'Your Transaction was not successful. Please contact support' );
				break;
		}
	}

	/**
	 * Collect every frontend state rendered by the Account Button block.
	 *
	 * @param array  $strings Collected strings.
	 * @param string $context Page context.
	 * @return void
	 */
	private function collect_account_button_strings( &$strings, $context ) {
		foreach ( [ 'Account', 'Close', 'Add Listing', 'Log Out' ] as $value ) {
			$this->add_string( $strings, $context, 'static_' . $value, $value );
		}

		$this->collect_account_strings( $strings, $context, [] );
		$this->collect_dashboard_strings( $strings, $context );
	}

	/**
	 * Collect native account, registration, and password-recovery UI strings.
	 *
	 * @param array  $strings Collected strings.
	 * @param string $context Page context.
	 * @param array  $attrs   Shortcode/block attributes.
	 * @return void
	 */
	private function collect_account_strings( &$strings, $context, $attrs ) {
		$fields = [
			'author_role_label'                   => [ '', 'I am an author' ],
			'user_role_label'                     => [ '', 'I am a user' ],
			'username_label'                      => [ 'reg_username', 'Username' ],
			'password_label'                      => [ 'reg_password', 'Password' ],
			'email_label'                         => [ 'reg_email', 'Email' ],
			'website_label'                       => [ 'reg_website', 'Website' ],
			'firstname_label'                     => [ 'reg_fname', 'First Name' ],
			'lastname_label'                      => [ 'reg_lname', 'Last Name' ],
			'bio_label'                           => [ 'reg_bio', 'About/bio' ],
			'privacy_label'                       => [ 'registration_privacy_label', 'I agree to the' ],
			'privacy_linking_text'                => [ 'registration_privacy_label_link', 'Privacy & Policy' ],
			'terms_label'                         => [ 'regi_terms_label', 'I agree with all' ],
			'terms_linking_text'                  => [ 'regi_terms_label_link', 'terms & conditions' ],
			'signup_button_label'                 => [ 'reg_signup', 'Sign Up' ],
			'signin_message'                      => [ 'login_text', 'Already have an account? Please Sign in' ],
			'signin_linking_text'                 => [ 'log_linkingmsg', 'Here' ],
			'signin_username_label'               => [ 'log_username', 'Username or Email Address' ],
			'signin_button_label'                 => [ 'log_button', 'Sign In' ],
			'signup_label'                        => [ 'reg_text', "Don't have an account?" ],
			'signup_linking_text'                 => [ 'reg_linktxt', 'Sign Up' ],
			'recovery_password_label'             => [ 'recpass_text', 'Forgot Password?' ],
			'recovery_password_description'       => [ 'recpass_desc', 'Lost your password? Please enter your email address. You will receive a link to create a new password via email.' ],
			'recovery_password_email_label'       => [ 'recpass_username', 'E-mail:' ],
			'recovery_password_email_placeholder' => [ 'recpass_placeholder', 'eg. mail@example.com' ],
			'recovery_password_button_label'      => [ 'recpass_button', 'Get New Password' ],
			'rememberme_label'                    => [ 'log_rememberme', 'Remember Me' ],
		];

		foreach ( $fields as $attr_key => $field ) {
			$option_key = $field[0];
			$value      = ! empty( $attrs[ $attr_key ] ) && is_string( $attrs[ $attr_key ] )
				? $attrs[ $attr_key ]
				: ( $option_key ? $this->get_raw_directorist_option( $option_key, $field[1] ) : $field[1] );

			$this->add_string( $strings, $context, 'account_' . $attr_key, $value );
		}

		foreach ( $this->get_account_template_strings() as $key => $value ) {
			$this->add_string( $strings, $context, 'account_' . $key, $value );
		}
	}

	/**
	 * Collect native dashboard tabs, forms, actions, and status messages.
	 *
	 * @param array  $strings Collected strings.
	 * @param string $context Page context.
	 * @return void
	 */
	private function collect_dashboard_strings( &$strings, $context ) {
		$options = [
			'my_listing_tab_text'       => 'My Listing',
			'my_profile_tab_text'       => 'My Profile',
			'fav_listings_tab_text'     => 'Favorite Listings',
			'become_author_button_text' => 'Become An Author',
			'pending_confirmation_msg'  => 'Thank you for your submission. Your listing is being reviewed and it may take up to 24 hours to complete the review.',
			'publish_confirmation_msg'  => 'Congratulations! Your listing has been approved/published. Now it is publicly available.',
		];

		foreach ( $options as $option_key => $default ) {
			$this->add_setting_string( $strings, $context, $option_key, $default );
		}

		foreach ( $this->get_dashboard_template_strings() as $key => $value ) {
			$this->add_string( $strings, $context, 'dashboard_' . $key, $value );
		}
	}

	/**
	 * Collect native author profile labels and count formats.
	 *
	 * Listing-card strings remain in their own listing-card surface; this page
	 * context covers only the author profile UI wrapped around those cards.
	 *
	 * @param array  $strings Collected strings.
	 * @param string $context Page context.
	 * @return void
	 */
	private function collect_author_profile_strings( &$strings, $context ) {
		$values = [
			'contact_info'       => 'Contact Info',
			'about'              => 'About',
			'nothing_to_show'    => 'Nothing to show!',
			'author_listings'    => 'Author Listings',
			'filter_by_category' => 'Filter by category',
			'member_since'       => 'Member since %s ago',
			'review_singular'    => '%s Review',
			'review_plural'      => '%s Reviews',
			'listing_singular'   => 'Listing',
			'listing_plural'     => 'Listings',
		];

		foreach ( $values as $key => $value ) {
			$this->add_string( $strings, $context, 'author_' . $key, $value );
		}
	}

	/**
	 * Collect native All Authors labels.
	 *
	 * @param array  $strings Collected strings.
	 * @param string $context Page context.
	 * @return void
	 */
	private function collect_all_authors_strings( &$strings, $context ) {
		$this->add_setting_string( $strings, $context, 'all_authors_button_text', 'View All Listings' );
		$this->add_string( $strings, $context, 'all', 'All' );
		$this->add_string( $strings, $context, 'no_authors_found', 'No authors found' );
	}

	/**
	 * Collect native Directorist checkout labels, settings, and error states.
	 *
	 * Pricing-plan rows use the directorist-pricing-plans text domain and are
	 * intentionally excluded from this native Directorist page package.
	 *
	 * @param array  $strings Collected strings.
	 * @param string $context Page context.
	 * @return void
	 */
	private function collect_checkout_strings( &$strings, $context ) {
		$this->add_setting_string( $strings, $context, 'bank_transfer_title', 'Bank Transfer' );
		$this->add_setting_string(
			$strings,
			$context,
			'bank_transfer_description',
			'You can make your payment directly to our bank account using this gateway. Please use your ORDER ID as a reference when making the payment. We will complete your order as soon as your deposit is cleared in our bank.'
		);

		foreach ( $this->get_checkout_template_strings() as $key => $value ) {
			$this->add_string( $strings, $context, 'checkout_' . $key, $value );
		}
	}

	/**
	 * Collect native Directorist payment receipt labels and error states.
	 *
	 * @param array  $strings Collected strings.
	 * @param string $context Page context.
	 * @return void
	 */
	private function collect_payment_receipt_strings( &$strings, $context ) {
		$this->add_setting_string( $strings, $context, 'bank_transfer_title', 'Bank Transfer' );
		$this->add_setting_string(
			$strings,
			$context,
			'bank_transfer_instruction',
			'Please transfer the amount to our bank account. We will process your order once we receive the payment.'
		);

		foreach ( $this->get_payment_receipt_template_strings() as $key => $value ) {
			$this->add_string( $strings, $context, 'receipt_' . $key, $value );
		}
	}

	/**
	 * Collect static UI text for listing archive-style pages.
	 *
	 * @param array  $strings       Collected strings.
	 * @param string $context       Page context.
	 * @param int    $directory_id  Directory type ID.
	 * @param bool   $search_result Whether this is the search result page.
	 * @return void
	 */
	private function collect_listing_archive_strings( &$strings, $context, $directory_id, $search_result ) {
		$options = $search_result
			? [
				'search_result_listing_title'    => 'Items Found',
				'search_viewas_text'              => 'View As',
				'search_sortby_text'              => 'Sort By',
				'search_result_filter_button_text' => 'Filters',
				'sresult_reset_text'              => 'Reset Filters',
				'sresult_sidebar_reset_text'      => 'Clear All',
				'sresult_apply_text'              => 'Apply Filters',
			]
			: [
				'all_listing_title'               => 'Items Found',
				'view_as_text'                    => 'View As',
				'sort_by_text'                    => 'Sort By',
				'listings_filter_button_text'     => 'Filters',
				'listings_sidebar_filter_text'    => 'Filters',
				'listings_reset_text'             => 'Reset Filters',
				'listings_sidebar_reset_text'     => 'Clear All',
				'listings_apply_text'             => 'Apply Filters',
				'listings_category_placeholder'   => 'Select a category',
				'listings_location_placeholder'   => 'Select a location',
				'listings_search_text_placeholder' => 'What are you looking for?',
				'popular_badge_text'              => 'Popular',
				'feature_badge_text'              => 'Featured',
				'readmore_text'                   => 'Read More',
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

		foreach ( [ 'No listings found.', 'No listing found.', 'Filter', 'Filters', 'Sidebar Filter Toggle Button', 'Sidebar Filter Close Button', 'Add to Favorite Button', 'grid view', 'list view', 'map view', 'Listings Pagination', 'Review' ] as $static_string ) {
			$this->add_string( $strings, $context, 'static_' . $static_string, $static_string );
		}

		if ( $directory_id > 0 ) {
			$this->collect_builder_meta_strings( $strings, $context, $directory_id, 'search_form_fields' );

			$this->collect_builder_meta_strings( $strings, $context, $directory_id, 'listings_card_grid_view' );
			$this->collect_builder_meta_strings( $strings, $context, $directory_id, 'listings_card_list_view' );
		}
	}

	/**
	 * Collect static UI text for the search listing page.
	 *
	 * @param array  $strings      Collected strings.
	 * @param string $context      Page context.
	 * @param int    $directory_id Directory type ID.
	 * @param array  $attrs        Shortcode/block attributes.
	 * @return void
	 */
	private function collect_search_listing_strings( &$strings, $context, $directory_id, $attrs ) {
		$options = [
			'search_title'                    => 'Search here',
			'search_subtitle'                 => 'Find the best match of your interest',
			'search_listing_text'             => 'Search Listing',
			'search_more_filters'             => 'More Filters',
			'search_reset_text'               => 'Reset Filters',
			'search_apply_filter'             => 'Apply Filters',
			'listings_search_text_placeholder' => 'What are you looking for?',
			'connectors_title'                => 'Or',
			'popular_cat_title'               => 'Browse by popular categories',
		];

		foreach ( $options as $option_key => $default ) {
			$this->add_setting_string( $strings, $context, $option_key, $default );
		}

		foreach ( [ 'search_bar_title', 'search_bar_sub_title', 'search_button_text', 'more_filters_text', 'reset_filters_text', 'apply_filters_text' ] as $attr_key ) {
			if ( ! empty( $attrs[ $attr_key ] ) && is_string( $attrs[ $attr_key ] ) ) {
				$this->add_string( $strings, $context, 'attr_' . $attr_key, $attrs[ $attr_key ] );
			}
		}

		if ( $directory_id > 0 ) {
			$this->collect_builder_meta_strings( $strings, $context, $directory_id, 'search_form_fields' );
		}
	}

	/**
	 * Collect static UI text for the Add Listing page.
	 *
	 * @param array  $strings      Collected strings.
	 * @param string $context      Page context.
	 * @param int    $directory_id Directory type ID.
	 * @return void
	 */
	private function collect_add_listing_strings( &$strings, $context, $directory_id ) {
		if ( $directory_id > 0 ) {
			$this->collect_builder_meta_strings( $strings, $context, $directory_id, 'submission_form_fields' );
			$this->collect_builder_meta_strings( $strings, $context, $directory_id, 'submit_button_label' );
		}

		foreach ( $this->get_add_listing_template_strings() as $key => $value ) {
			$this->add_string( $strings, $context, $key, $value );
		}
	}

	/**
	 * Collect strings from selected Directorist directory builder meta.
	 *
	 * @param array  $strings      Collected strings.
	 * @param string $context      Page context.
	 * @param int    $directory_id Directory type ID.
	 * @param string $meta_key     Term meta key.
	 * @return void
	 */
	private function collect_builder_meta_strings( &$strings, $context, $directory_id, $meta_key ) {
		$meta_value = $this->apply_search_form_field_fallbacks(
			$meta_key,
			$this->get_raw_term_meta( $directory_id, $meta_key )
		);

		if ( is_array( $meta_value ) ) {
			$this->extract_builder_strings( $strings, $context, $meta_key, $meta_value );
			return;
		}

		if ( $this->is_translatable_builder_value( $meta_key, $meta_value, [ $meta_key ] ) ) {
			$this->add_string( $strings, $context, $meta_key, $meta_value );
		}
	}

	/**
	 * Mirror native search-field labels that Directorist renders as fallbacks.
	 *
	 * The pricing search field can store an empty label while its template still
	 * renders the native "Pricing" fallback. Add that source label only to the
	 * in-memory ATE inventory so the rendered text remains translatable without
	 * changing directory builder data.
	 *
	 * @param string $meta_key   Term meta key.
	 * @param mixed  $meta_value Raw builder meta value.
	 * @return mixed
	 */
	private function apply_search_form_field_fallbacks( $meta_key, $meta_value ) {
		if ( 'search_form_fields' !== $meta_key || ! is_array( $meta_value ) || empty( $meta_value['fields']['pricing'] ) || ! is_array( $meta_value['fields']['pricing'] ) ) {
			return $meta_value;
		}

		$pricing_label = isset( $meta_value['fields']['pricing']['label'] ) ? $meta_value['fields']['pricing']['label'] : '';

		if ( ! is_string( $pricing_label ) || '' === trim( $pricing_label ) ) {
			$meta_value['fields']['pricing']['label'] = 'Pricing';
		}

		return $meta_value;
	}

	/**
	 * Recursively extract user-facing strings from builder arrays.
	 *
	 * @param array  $strings  Collected strings.
	 * @param string $context  Page context.
	 * @param string $meta_key Term meta key.
	 * @param array  $data     Builder data.
	 * @param array  $path     Current nested path.
	 * @return void
	 */
	private function extract_builder_strings( &$strings, $context, $meta_key, $data, $path = [] ) {
		foreach ( $data as $key => $value ) {
			$current_path = array_merge( $path, [ (string) $key ] );

			if (
				in_array( $meta_key, [ 'listings_card_grid_view', 'listings_card_list_view' ], true )
				&& 'options' === $this->safe_slug( $key )
			) {
				continue;
			}

			if ( is_array( $value ) ) {
				$this->extract_builder_strings( $strings, $context, $meta_key, $value, $current_path );
				continue;
			}

			if ( ! $this->is_translatable_builder_value( $key, $value, $current_path ) ) {
				continue;
			}

			$this->add_string( $strings, $context, $meta_key . '_' . implode( '_', $current_path ), $value );
		}
	}

	/**
	 * Check whether a builder scalar is visible UI text.
	 *
	 * @param string|int $key   Array key.
	 * @param mixed      $value Value.
	 * @param array      $path  Nested path.
	 * @return bool
	 */
	private function is_translatable_builder_value( $key, $value, $path ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return false;
		}

		if ( ! $this->is_translatable_ui_string( $value ) ) {
			return false;
		}

		$key  = $this->safe_slug( $key );
		$path = array_map( [ $this, 'safe_slug' ], (array) $path );

		$blocked_keys = [
			'active_template',
			'align',
			'can_move',
			'conditional_logic',
			'custom_block_classes',
			'custom_block_id',
			'date_type',
			'default_radius_distance',
			'display_map_info',
			'draggable',
			'enable',
			'enable_tagline',
			'enable_title',
			'field_key',
			'hook',
			'icon',
			'id',
			'lock',
			'max',
			'max_image_limit',
			'max_location_creation',
			'max_per_image_limit',
			'max_radius_distance',
			'max_total_image_limit',
			'only_for_admin',
			'original_widget_key',
			'placeholderkey',
			'post_type',
			'price_range_options',
			'price_unit_field_type',
			'pricing_type',
			'required',
			'section_id',
			'show_label',
			'type',
			'value',
			'widget_group',
			'widget_key',
			'widget_name',
			'option_value',
		];

		if ( in_array( $key, $blocked_keys, true ) ) {
			return false;
		}

		$allowed_keys = [
			'button_label',
			'cancel_button_label',
			'confirmation_text',
			'confirm_button_label',
			'default_group_label',
			'description',
			'heading',
			'label',
			'lat_long',
			'max_widget_info_text',
			'model_header_text',
			'option_label',
			'placeholder',
			'price_range_label',
			'price_range_placeholder',
			'price_unit_field_label',
			'price_unit_field_placeholder',
			'search_button_label',
			'search_button_text',
			'section_title',
			'select_files_label',
			'show_readmore_text',
			'submit_button_label',
			'text',
			'title',
		];

		if ( in_array( $key, $allowed_keys, true ) ) {
			return true;
		}

		foreach ( [ 'label', 'placeholder', 'description', 'text', 'title', 'heading' ] as $suffix ) {
			if ( strlen( $key ) > strlen( $suffix ) && substr( $key, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}

		if ( in_array( 'options', $path, true ) && ! in_array( $key, [ 'value', 'option_value', 'id', 'key', 'slug' ], true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Add a Directorist setting value to the page UI strings.
	 *
	 * @param array  $strings    Collected strings.
	 * @param string $context    Page context.
	 * @param string $option_key Directorist option key.
	 * @param string $default    Default value.
	 * @return void
	 */
	private function add_setting_string( &$strings, $context, $option_key, $default ) {
		$this->add_string( $strings, $context, 'setting_' . $option_key, $this->get_raw_directorist_option( $option_key, $default ) );
	}

	/**
	 * Add a single page UI string.
	 *
	 * @param array  $strings Collected strings.
	 * @param string $context Page context.
	 * @param string $name    String name.
	 * @param string $value   Source value.
	 * @return void
	 */
	private function add_string( &$strings, $context, $name, $value ) {
		if ( ! $this->is_translatable_ui_string( $value ) ) {
			return;
		}

		$value = trim( (string) $value );

		foreach ( $strings as $existing_value ) {
			if ( $existing_value === $value ) {
				return;
			}
		}

		$key = $this->build_string_key( $context, $name );

		$strings[ $key ] = $value;
	}

	/**
	 * Check if a value is safe/useful for page UI translation.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private function is_translatable_ui_string( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return false;
		}

		$value        = trim( $value );
		$plain_value  = trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
		$lower_value  = strtolower( $plain_value );
		$value_length = strlen( $plain_value );

		if ( '' === $plain_value || is_numeric( $plain_value ) || $value_length > 1000 ) {
			return false;
		}

		if ( in_array( $lower_value, [ 'true', 'false', 'yes', 'no', 'on', 'off' ], true ) ) {
			return false;
		}

		if (
			preg_match( '/^[\p{P}\p{S}\s]+$/u', $plain_value )
			|| preg_match( '/^[a-z0-9_-]+(?:\.[a-z0-9_-]+)+$/i', $plain_value )
			|| preg_match( '/^[a-z0-9]+(?:_[a-z0-9]+)+$/i', $plain_value )
			|| is_email( $plain_value )
		) {
			return false;
		}

		if ( filter_var( $plain_value, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Build a stable custom-field subkey for ATE.
	 *
	 * @param string $context Page context.
	 * @param string $name    String name.
	 * @return string
	 */
	private function build_string_key( $context, $name ) {
		$key = 'directorist_page_ui_' . $this->safe_slug( $context ) . '_' . $this->safe_slug( $name );

		if ( strlen( $key ) > self::MAX_ATE_SUBKEY_LENGTH ) {
			$key = 'directorist_page_ui_' . $this->safe_slug( $context ) . '_' . md5( $name );
		}

		return $key;
	}

	/**
	 * Build a translation replacement map for the current translated page.
	 *
	 * @return array
	 */
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

		$this->current_page_map = $this->build_translation_map( $source_strings, $translated_strings, $current_language );

		return $this->current_page_map;
	}

	/**
	 * Build an exact source-to-translation lookup without cascading duplicates.
	 *
	 * @param array $source_strings     Source page strings keyed by ATE field key.
	 * @param array  $translated_strings Translated page strings keyed by ATE field key.
	 * @param string $language_code      Target language code.
	 * @return array
	 */
	private function build_translation_map( $source_strings, $translated_strings, $language_code = '' ) {
		$map = [];

		foreach ( $source_strings as $key => $source_value ) {
			if ( ! is_string( $source_value ) || ! isset( $translated_strings[ $key ] ) || ! is_string( $translated_strings[ $key ] ) ) {
				continue;
			}

			$source_value     = trim( $this->decode_html_value( $source_value ) );
			$translated_value = trim( $this->decode_html_value( $translated_strings[ $key ] ) );

			if ( '' === $source_value || '' === $translated_value || array_key_exists( $source_value, $map ) || $this->is_language_prefixed_placeholder( $translated_value, $language_code ) ) {
				continue;
			}

			$map[ $source_value ] = $translated_value;
		}

		$base_map = $map;
		foreach ( $base_map as $source_value => $translated_value ) {
			if ( preg_match( '/:\s*\*?$/u', $source_value ) ) {
				continue;
			}

			foreach ( [ ':', ': *' ] as $suffix ) {
				$source_variant = $source_value . $suffix;

				if ( ! array_key_exists( $source_variant, $map ) ) {
					$map[ $source_variant ] = $translated_value . $suffix;
				}
			}
		}

		return $map;
	}

	/**
	 * Let page ATE translations override native labels translated before output.
	 *
	 * Directorist translates the empty pricing-label fallback through gettext
	 * before the shortcode output filter runs. Add that rendered label as an
	 * alias for the exact page ATE translation in every shortcode context that
	 * renders Directorist's search form.
	 *
	 * @param array  $map     Source => translation map.
	 * @param string $context Shortcode context.
	 * @return array
	 */
	private function apply_shortcode_native_fallback_aliases( $map, $context ) {
		$search_form_contexts = [ 'all_listing', 'search_result', 'search_listing', 'category', 'location', 'tag' ];

		if ( in_array( $context, $search_form_contexts, true ) && ! empty( $map['Pricing'] ) && is_string( $map['Pricing'] ) ) {
			$native_pricing_label = trim( $this->decode_html_value( __( 'Pricing', 'directorist' ) ) );

			if ( '' !== $native_pricing_label && 'Pricing' !== $native_pricing_label && ! array_key_exists( $native_pricing_label, $map ) ) {
				$map[ $native_pricing_label ] = $map['Pricing'];
			}
		}

		$archive_contexts = [ 'all_listing', 'search_result', 'category', 'location', 'tag' ];

		if ( in_array( $context, $archive_contexts, true ) ) {
			foreach ( [ 'Grid' => 'grid view', 'List' => 'list view', 'Map' => 'map view' ] as $view_label => $source_view_label ) {
				if ( empty( $map[ $source_view_label ] ) || ! is_string( $map[ $source_view_label ] ) ) {
					continue;
				}

				$native_view_label = strtolower( trim( $this->decode_html_value( __( $view_label, 'directorist' ) ) ) ) . ' view';

				if ( $source_view_label !== $native_view_label && ! array_key_exists( $native_view_label, $map ) ) {
					$map[ $native_view_label ] = $map[ $source_view_label ];
				}
			}
		}

		$taxonomy_contexts = [ 'all_categories', 'all_locations' ];

		if ( in_array( $context, $taxonomy_contexts, true ) && ! empty( $map['No Results found!'] ) && is_string( $map['No Results found!'] ) ) {
			$native_empty_text = trim( $this->decode_html_value( __( 'No Results found!', 'directorist' ) ) );

			if ( '' !== $native_empty_text && 'No Results found!' !== $native_empty_text && ! array_key_exists( $native_empty_text, $map ) ) {
				$map[ $native_empty_text ] = $map['No Results found!'];
			}
		}

		$page_gettext_contexts = [
			'signin_signup',
			'user_dashboard',
			'author_profile',
			'all_authors',
			'checkout',
			'payment_receipt',
			'transaction_failure',
			'account_button',
		];

		if ( in_array( $context, $page_gettext_contexts, true ) ) {
			$source_map = $map;

			foreach ( $source_map as $source_text => $translated_text ) {
				$native_variants = [
					trim( $this->decode_html_value( __( $source_text, 'directorist' ) ) ),
					trim( $this->decode_html_value( __( $source_text ) ) ),
				];

				foreach ( array_unique( $native_variants ) as $native_text ) {
					if ( '' !== $native_text && $source_text !== $native_text && ! array_key_exists( $native_text, $map ) ) {
						$map[ $native_text ] = $translated_text;
					}
				}
			}
		}

		if ( 'author_profile' === $context ) {
			$count_labels = [
				'Listing'  => '<span>%s</span> Listing',
				'Listings' => '<span>%s</span> Listings',
			];

			foreach ( $count_labels as $source_label => $source_format ) {
				if ( empty( $map[ $source_label ] ) || ! is_string( $map[ $source_label ] ) ) {
					continue;
				}

				$native_format = _x( $source_format, 'author review count', 'directorist' );
				$native_label  = trim( str_replace( '%s', '', wp_strip_all_tags( $native_format ) ) );

				if ( '' !== $native_label && $source_label !== $native_label && ! array_key_exists( $native_label, $map ) ) {
					$map[ $native_label ] = $map[ $source_label ];
				}
			}
		}

		return $map;
	}

	/**
	 * Add exact aliases for native labels that contain dynamic, non-text data.
	 *
	 * Directorist appends the current listing count to the configured dashboard
	 * tab label. Preserve that number while replacing only the page ATE label.
	 *
	 * @param array  $map     Source => translation map.
	 * @param string $context Shortcode context.
	 * @param string $output  Rendered shortcode output.
	 * @return array
	 */
	private function apply_shortcode_dynamic_output_aliases( $map, $context, $output ) {
		if ( ! is_string( $output ) || '' === $output ) {
			return $map;
		}

		if ( in_array( $context, [ 'user_dashboard', 'account_button' ], true ) ) {
			$source_label = $this->get_raw_directorist_option( 'my_listing_tab_text', 'My Listing' );
			if ( ! is_string( $source_label ) || empty( $map[ $source_label ] ) || ! is_string( $map[ $source_label ] ) ) {
				return $map;
			}

			$rendered_labels = array_unique(
				[
					$source_label,
					trim( $this->decode_html_value( __( $source_label, 'directorist' ) ) ),
				]
			);

			foreach ( $rendered_labels as $rendered_label ) {
				if ( '' === $rendered_label ) {
					continue;
				}

				$pattern = '/>\s*(' . preg_quote( $rendered_label, '/' ) . '\s*\((\d+)\))\s*</u';
				if ( ! preg_match_all( $pattern, $output, $matches, PREG_SET_ORDER ) ) {
					continue;
				}

				foreach ( $matches as $match ) {
					$map[ trim( $match[1] ) ] = $map[ $source_label ] . ' (' . $match[2] . ')';
				}
			}
		}

		if ( 'author_profile' === $context ) {
			$map = $this->add_single_placeholder_output_aliases(
				$map,
				$output,
				[ 'Member since %s ago' ]
			);
			$map = $this->add_single_placeholder_output_aliases(
				$map,
				$output,
				[ '%s Review', '%s Reviews' ],
				'author review count'
			);
		}

		if ( 'checkout' === $context ) {
			$map = $this->add_single_placeholder_output_aliases(
				$map,
				$output,
				[ 'Total amount [%s]', 'Featured Listing: %s' ]
			);
		}

		if ( 'payment_receipt' === $context ) {
			$map = $this->add_single_placeholder_output_aliases(
				$map,
				$output,
				[ 'Discount ( %d%% )', 'Tax ( %d%% )', 'Featured Listing: %s' ]
			);
		}

		return $map;
	}

	/**
	 * Add exact rendered aliases for native strings with one dynamic value.
	 *
	 * The alias is derived from the ATE format and the value already present in
	 * one visible text node, so no listing/order data is stored or queried.
	 *
	 * @param array  $map             Source => translation map.
	 * @param string $output          Rendered shortcode output.
	 * @param array  $source_formats  Native source formats.
	 * @param string $gettext_context Optional gettext context.
	 * @return array
	 */
	private function add_single_placeholder_output_aliases( $map, $output, $source_formats, $gettext_context = '' ) {
		foreach ( $source_formats as $source_format ) {
			if ( empty( $map[ $source_format ] ) || ! is_string( $map[ $source_format ] ) ) {
				continue;
			}

			if ( ! preg_match( '/%(?:\d+\$)?[sd]/', $source_format, $placeholder_match ) ) {
				continue;
			}

			$placeholder       = $placeholder_match[0];
			$translated_format = $map[ $source_format ];

			if ( 1 !== substr_count( $translated_format, $placeholder ) ) {
				continue;
			}

			$native_format = '' !== $gettext_context
				? _x( $source_format, $gettext_context, 'directorist' )
				: __( $source_format, 'directorist' );

			$marker            = '__DIRECTORIST_DYNAMIC_VALUE__';
			$native_visible    = wp_strip_all_tags( str_replace( $placeholder, $marker, $native_format ) );
			$translated_visible = wp_strip_all_tags( str_replace( $placeholder, $marker, $translated_format ) );
			$native_visible    = str_replace( '%%', '%', $native_visible );
			$translated_visible = str_replace( '%%', '%', $translated_visible );

			if ( 1 !== substr_count( $native_visible, $marker ) || 1 !== substr_count( $translated_visible, $marker ) ) {
				continue;
			}

			list( $native_prefix, $native_suffix ) = explode( $marker, $native_visible, 2 );
			$pattern = '/>\s*(' . preg_quote( $native_prefix, '/' ) . '([^<>]+?)' . preg_quote( $native_suffix, '/' ) . ')\s*</u';

			if ( ! preg_match_all( $pattern, $output, $matches, PREG_SET_ORDER ) ) {
				continue;
			}

			foreach ( $matches as $match ) {
				$translated_rendered = str_replace( $marker, trim( $match[2] ), $translated_visible );
				$map[ trim( $match[1] ) ] = trim( $translated_rendered );
			}
		}

		return $map;
	}

	/**
	 * Replace dynamic fragments whose value and label are separate text nodes.
	 *
	 * Directorist renders the author listing number inside a nested span and
	 * the singular/plural label beside it. Scope the replacement to that exact
	 * wrapper so another identical word elsewhere on the page is untouched.
	 *
	 * @param string $output  Rendered shortcode output.
	 * @param array  $map     Source => translation map.
	 * @param string $context Shortcode context.
	 * @return string
	 */
	private function replace_shortcode_dynamic_fragments( $output, $map, $context ) {
		if ( ! is_string( $output ) || '' === $output ) {
			return $output;
		}

		if ( 'author_profile' === $context ) {
			$pattern = '~(<span\b[^>]*class=(["\'])[^"\']*\bdirectorist-listing-count\b[^"\']*\2[^>]*>.*?<span\b[^>]*>\s*(\d+)\s*</span>\s*)([^<]*?)(\s*</span>)~is';
			$replaced = preg_replace_callback(
				$pattern,
				function ( $match ) use ( $map ) {
					$source_label = 1 === (int) $match[3] ? 'Listing' : 'Listings';

					if ( empty( $map[ $source_label ] ) || ! is_string( $map[ $source_label ] ) ) {
						return $match[0];
					}

					return $match[1] . esc_html( $map[ $source_label ] ) . $match[5];
				},
				$output
			);

			$output = null === $replaced ? $output : $replaced;
		}

		if ( 'payment_receipt' === $context ) {
			$source_instruction = $this->get_raw_directorist_option(
				'bank_transfer_instruction',
				'Please transfer the amount to our bank account. We will process your order once we receive the payment.'
			);

			if (
				is_string( $source_instruction )
				&& 1 === substr_count( $source_instruction, '#==ORDER_ID==' )
				&& ! empty( $map[ $source_instruction ] )
				&& is_string( $map[ $source_instruction ] )
				&& 1 === substr_count( $map[ $source_instruction ], '#==ORDER_ID==' )
			) {
				$rendered_sources = [ $source_instruction ];

				if ( function_exists( 'get_directorist_option' ) ) {
					$runtime_instruction = get_directorist_option( 'bank_transfer_instruction', $source_instruction );
					if ( is_string( $runtime_instruction ) ) {
						$rendered_sources[] = $runtime_instruction;
					}
				}

				foreach ( array_unique( $rendered_sources ) as $rendered_source ) {
					if ( 1 !== substr_count( $rendered_source, '#==ORDER_ID==' ) ) {
						continue;
					}

					$source_html = nl2br( $rendered_source );
					$pattern     = '~' . str_replace(
						preg_quote( '#==ORDER_ID==', '~' ),
						'(\#[0-9]+)',
						preg_quote( $source_html, '~' )
					) . '~';

					if ( ! preg_match( $pattern, $output, $instruction_match ) ) {
						continue;
					}

					$translated_html = nl2br( str_replace( '#==ORDER_ID==', $instruction_match[1], $map[ $source_instruction ] ) );
					$translated_output = preg_replace( $pattern, wp_kses_post( $translated_html ), $output, 1 );
					$output            = null === $translated_output ? $output : $translated_output;
					break;
				}
			}
		}

		return $output;
	}

	/**
	 * Replace text nodes and exact attribute values in shortcode output.
	 *
	 * @param string $output Shortcode output.
	 * @param array  $map    Source => translation map.
	 * @return string
	 */
	private function replace_output_strings( $output, $map ) {
		$lookup = [];

		foreach ( $map as $source => $translated ) {
			$source     = trim( $this->decode_html_value( $source ) );
			$translated = trim( $this->decode_html_value( $translated ) );

			if ( '' === $source || '' === $translated || $source === $translated || array_key_exists( $source, $lookup ) ) {
				continue;
			}

			$lookup[ $source ] = $translated;
		}

		if ( empty( $lookup ) ) {
			return $output;
		}

		$skip_text = false;
		$replaced  = preg_replace_callback(
			'/<(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>|[^<]+/s',
			function ( $matches ) use ( $lookup, &$skip_text ) {
				$chunk = $matches[0];

				if ( '<' === $chunk[0] ) {
					if ( preg_match( '/^<\s*(script|style)\b/i', $chunk ) ) {
						$skip_text = true;
					} elseif ( preg_match( '/^<\s*\/\s*(script|style)\b/i', $chunk ) ) {
						$skip_text = false;
					}

					$tag = preg_replace_callback(
						'/(\s[\w:-]+\s*=\s*)(["\'])(.*?)\2/s',
						function ( $attribute ) use ( $lookup ) {
							$value = $this->decode_html_value( $attribute[3] );

							if ( ! array_key_exists( $value, $lookup ) ) {
								return $attribute[0];
							}

							return $attribute[1] . $attribute[2] . esc_attr( $lookup[ $value ] ) . $attribute[2];
						},
						$chunk
					);

					return null === $tag ? $chunk : $tag;
				}

				if ( $skip_text || ! preg_match( '/^(\s*)(.*?)(\s*)$/us', $chunk, $text ) ) {
					return $chunk;
				}

				$value = $this->decode_html_value( $text[2] );
				if ( ! array_key_exists( $value, $lookup ) ) {
					return $chunk;
				}

				return $text[1] . esc_html( $lookup[ $value ] ) . $text[3];
			},
			$output
		);

		return null === $replaced ? $output : $replaced;
	}

	/**
	 * Normalize HTML entities before exact string comparison.
	 *
	 * @param string $value Encoded or plain string.
	 * @return string
	 */
	private function decode_html_value( $value ) {
		return html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
	}

	/**
	 * Get a page's source-language translation ID.
	 *
	 * @param int $page_id Page ID.
	 * @return int
	 */
	private function get_source_page_id( $page_id ) {
		$default_language = apply_filters( 'wpml_default_language', null );
		$translations     = WPML_Helper::get_element_translations( $page_id, 'page' );

		if ( ! empty( $default_language ) && ! empty( $translations[ $default_language ]->element_id ) ) {
			return (int) $translations[ $default_language ]->element_id;
		}

		return (int) $page_id;
	}

	/**
	 * Tell WPML existing page jobs need refreshing after hidden UI meta changes.
	 *
	 * @param int $source_page_id Source/default-language page ID.
	 * @return void
	 */
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

	/**
	 * Check whether a page is the source/default-language item.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_source_page( $post_id ) {
		if ( ! $this->is_wpml_active() ) {
			return true;
		}

		$language_info = WPML_Helper::get_language_info( $post_id, 'page' );
		if ( empty( $language_info ) || ! is_object( $language_info ) ) {
			return true;
		}

		return empty( $language_info->source_language_code );
	}

	/**
	 * Resolve a directory type ID from shortcode/block attrs.
	 *
	 * @param array $attrs Shortcode/block attributes.
	 * @return int
	 */
	private function get_directory_id_from_attrs( $attrs ) {
		foreach ( [ 'directory_type', 'default_directory_type', 'listing_type', 'default_type', 'type' ] as $attr_key ) {
			if ( empty( $attrs[ $attr_key ] ) ) {
				continue;
			}

			$directory_id = $this->resolve_directory_id( $attrs[ $attr_key ] );
			if ( $directory_id > 0 ) {
				return $directory_id;
			}
		}

		if ( function_exists( 'directorist_get_default_directory' ) ) {
			return (int) directorist_get_default_directory();
		}

		return 0;
	}

	/**
	 * Resolve a directory type ID from ID, slug, or comma-separated values.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private function resolve_directory_id( $value ) {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}

		if ( false !== strpos( $value, ',' ) ) {
			$parts = array_filter( array_map( 'trim', explode( ',', $value ) ) );
			$value = reset( $parts );
		}

		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		$term = get_term_by( 'slug', sanitize_title( $value ), $this->get_directory_taxonomy() );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		return 0;
	}

	/**
	 * Get an unfiltered Directorist option value.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function get_raw_directorist_option( $key, $default = '' ) {
		$options = get_option( 'atbdp_option', [] );

		if ( is_array( $options ) && array_key_exists( $key, $options ) ) {
			return $options[ $key ];
		}

		return $default;
	}

	/**
	 * Get raw term meta without metadata translation filters.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $meta_key Meta key.
	 * @return mixed
	 */
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

	/**
	 * Native account-page strings that are not configurable Directorist labels.
	 *
	 * Email subjects and bodies are intentionally excluded; they belong to the
	 * separate native email-template translation surface.
	 *
	 * @return array
	 */
	private function get_account_template_strings() {
		return [
			'logged_out_only'                    => 'The account page is only accessible to logged-out users.',
			'go_to_dashboard'                    => 'Go to Dashboard',
			'registration_complete_login'        => 'Registration completed. Please check your email for confirmation. Or login here.',
			'verify_email_intro'                  => 'Thank you for signing up! To complete the registration, please verify your email address by clicking on the link we have sent to your email.',
			'verify_email_missing'                => "If you didn't find the verification email, please check your spam folder. If you still can't find it, click on the",
			'resend_confirmation_email'           => 'Resend confirmation email',
			'verify_email_resend_suffix'          => 'to have a new email sent to you.',
			'new_verification_email'              => 'Thank you for requesting a new verification email. Please check your inbox and verify to complete the registration.',
			'verification_help'                   => "If you still can't find it, please check your spam folder. And please contact if you are still having trouble.",
			'user_not_found'                      => 'Sorry! user not found',
			'passwords_empty'                     => 'Passwords cannot be empty.',
			'passwords_mismatch'                  => 'Passwords do not match!',
			'password_changed'                    => 'Password changed successfully. Please',
			'email_verification_success'          => 'Email verification successful. Please',
			'click_here_to_login'                 => 'click here to login',
			'email_empty'                         => 'Email address cannot be empty.',
			'invalid_email'                       => 'Invalid e-mail address.',
			'email_not_registered'                => 'There is no user registered with that email address.',
			'reset_email_sent'                    => 'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox.',
			'reset_email_failed'                  => 'Something went wrong, unable to send the password reset email. If the issue persists please contact with the site administrator.',
			'error_label'                         => 'ERROR:',
			'get_password_from_email'             => 'Go to your inbox or spam/junk and get your password.',
			'registration_complete'               => 'Registration completed. Please check your email for confirmation.',
			'password_did_not_match'              => 'Password did not match',
			'enter_new_password'                  => 'Enter a new password below.',
			'new_password'                        => 'New password',
			'reenter_new_password'                => 'Re-enter new password',
			'save'                                => 'Save',
		];
	}

	/**
	 * Native dashboard strings rendered by Directorist core.
	 *
	 * Extension-owned tabs such as bookings, chat, wallet, and pricing-plan
	 * order history are deliberately excluded from the native page package.
	 *
	 * @return array
	 */
	private function get_dashboard_template_strings() {
		return [
			'preferences'                  => 'Preferences',
			'submit_listing'               => 'Submit Listing',
			'become_author_confirmation'   => 'Are you sure you want to become an author?',
			'become_author_approval'       => '(It is subject to approval by the admin)',
			'sign_out'                     => 'Sign Out',
			'login_required'               => 'You need to be logged in to view the content of this page. You can login',
			'here'                         => 'Here',
			'no_account'                   => "Don't have an account?",
			'sign_up'                      => 'Sign Up',
			'all_listings'                 => 'All Listings',
			'published'                    => 'Published',
			'pending'                      => 'Pending',
			'draft'                        => 'Draft',
			'expired'                      => 'Expired',
			'rejected'                     => 'Rejected',
			'search_listings'              => 'Search listings',
			'listings'                     => 'Listings',
			'type'                         => 'Type',
			'expiration_date'              => 'Expiration Date',
			'status'                       => 'Status',
			'never_expires'                => 'Never Expires',
			'unknown'                      => 'Unknown',
			'no_rejection_reason'          => 'No reason was provided. Please contact the administrator.',
			'see_why'                      => 'See why',
			'rejection_reason_title'       => 'Why this listing was rejected',
			'untitled'                     => 'Untitled',
			'edit'                         => 'Edit',
			'more'                         => 'More',
			'no_items_found'               => 'No items found',
			'remove'                       => 'Remove',
			'nothing_found'                => 'Nothing found!',
			'renew'                        => 'Renew',
			'promote'                      => 'Promote',
			'unfeature'                    => 'Unfeature',
			'delete_listing'               => 'Delete Listing',
			'invalid_link'                 => 'Link appears to be invalid.',
			'renewed'                      => 'Renewed successfully.',
			'select'                       => 'Select',
			'change'                       => 'Change',
			'max_total_file_size'          => 'Max limit for total file size is __DT__',
			'min_file_items'               => 'Min __DT__ file is required',
			'max_file_items'               => 'Max limit for total file is __DT__',
			'allowed_file_size'            => 'Maximum allowed file size is __DT__',
			'minimum_file_items'           => 'Minimum __DT__ file is required',
			'my_profile'                   => 'My Profile',
			'display_name'                 => 'Display Name',
			'display_name_placeholder'     => 'Enter your display name',
			'user_name'                    => 'User Name',
			'username_readonly'            => '(username can not be changed)',
			'first_name'                   => 'First Name',
			'last_name'                    => 'Last Name',
			'email_required'               => 'Email (required)',
			'phone'                        => 'Phone',
			'phone_placeholder'            => 'Enter your phone number',
			'website'                      => 'Website',
			'address'                      => 'Address',
			'new_password'                 => 'New Password',
			'new_password_placeholder'     => 'Enter a new password',
			'confirm_new_password'         => 'Confirm New Password',
			'confirm_password_placeholder' => 'Confirm your new password',
			'about_author'                 => 'About Author',
			'social_profiles'              => 'Social Profiles',
			'facebook'                     => 'Facebook',
			'facebook_placeholder'         => 'Enter your facebook url',
			'empty_to_hide'                => 'Leave it empty to hide',
			'x'                            => 'X',
			'x_placeholder'                => 'Enter your x url',
			'linkedin'                     => 'LinkedIn',
			'linkedin_placeholder'         => 'Enter linkedIn url',
			'youtube'                      => 'Youtube',
			'youtube_placeholder'          => 'Enter youtube url',
			'save_changes'                 => 'Save Changes',
			'hide_contact_form'            => 'Hide contact form in my listings',
			'display_author_email'         => 'Display Email on Author Page',
			'display_everyone'             => 'Display to Everyone',
			'display_logged_in'            => 'Display to Logged in Users Only',
			'dont_display'                 => 'Don’t Display',
			'contact_form_recipient'       => 'Contact Listing Owner Form Recipient',
			'author_email'                 => 'Author Email',
			'listing_email'                => "Listing's Email",
		];
	}

	/**
	 * Static sorting labels used by Directorist listing archives.
	 *
	 * @return array
	 */
	private function get_sorting_strings() {
		return [
			'title-asc'  => 'A to Z (title)',
			'title-desc' => 'Z to A (title)',
			'date-desc'  => 'Latest listings',
			'date-asc'   => 'Oldest listings',
			'views-desc' => 'Popular listings',
			'price-asc'  => 'Price (low to high)',
			'price-desc' => 'Price (high to low)',
			'rand'       => 'Random listings',
		];
	}

	/**
	 * Static view switcher labels used by Directorist listing archives.
	 *
	 * @return array
	 */
	private function get_view_strings() {
		return [
			'grid' => 'Grid',
			'list' => 'List',
			'map'  => 'Map',
		];
	}

	/**
	 * Add-listing template strings not stored in builder meta.
	 *
	 * @return array
	 */
	private function get_add_listing_template_strings() {
		return [
			'add_listing_wizard_finish'              => 'Finish',
			'add_listing_wizard_save_next'           => 'Save & Next',
			'add_listing_wizard_go_to_next'          => 'Go to Next',
			'add_listing_publish_title'              => 'You are about to publish',
			'add_listing_publish_subtitle'           => 'Are you sure you want to publish this listing?',
			'add_listing_add_social'                 => 'Add Social',
			'add_listing_map_drag_info'              => 'You can drag pinpoint to place the correct address manually.',
			'add_listing_latitude'                   => 'Latitude',
			'add_listing_longitude'                  => 'Longitude',
			'add_listing_latitude_placeholder'       => 'Enter Latitude eg. 24.89904',
			'add_listing_longitude_placeholder'      => 'Enter Longitude eg. 91.87198',
			'add_listing_generate_on_map'            => 'Generate on Map',
			'add_listing_hide_map'                   => 'Hide Map',
			'add_listing_upload_drop_here'           => 'Drop Here',
			'add_listing_upload_preview'             => 'Preview',
			'add_listing_upload_drag_image'          => 'Drag and drop an image',
			'add_listing_upload_or'                  => 'or',
			'add_listing_upload_or_drag_here'        => 'or drag and drop image here',
			'add_listing_upload_add_more'            => 'Add More',
			'add_listing_upload_max_file_size_alert' => 'Maximum limit for a file is  __DT__',
			'add_listing_upload_max_total_size_alert' => 'Maximum limit for total file size is __DT__',
			'add_listing_upload_min_file_items_alert' => 'Minimum __DT__ file is required',
			'add_listing_upload_max_file_items_alert' => 'Maximum limit for total file is __DT__',
			'add_listing_upload_max_file_size_info'  => 'Maximum allowed size per file is __DT__',
			'add_listing_upload_max_total_size_info' => 'Maximum total allowed file size is __DT__',
			'add_listing_upload_unlimited_images'    => 'Unlimited images with this plan!',
			'add_listing_upload_max_files_allowed'   => 'Maximum __DT__ files are allowed',
			'add_listing_upload_max_file_allowed'    => 'Maximum __DT__ file is allowed',
		];
	}

	/**
	 * Native checkout strings rendered by Directorist core.
	 *
	 * @return array
	 */
	private function get_checkout_template_strings() {
		return [
			'order_details'         => 'Your order details are given below. Please review it and click on Proceed to Payment to complete this order.',
			'order_summary'         => 'Order Summary',
			'subtotal'              => 'Subtotal',
			'total_amount'          => 'Total amount',
			'total_amount_currency' => 'Total amount [%s]',
			'choose_payment_method' => 'Choose a payment method',
			'complete_submission'   => 'Complete Submission',
			'pay_now'               => 'Pay Now',
			'not_now'               => 'Not Now',
			'cancel'                => 'Cancel',
			'processing'            => 'Processing...',
			'featured'              => 'Featured',
			'featured_listing'      => 'Featured Listing',
			'featured_listing_item' => 'Featured Listing: %s',
			'monetization_inactive' => 'Monetization is not active on this site. if you are an admin, you can enable it from the settings panel.',
			'invalid_checkout_type' => 'Invalid checkout type.',
			'listing_id_missing'    => 'Sorry, Something went wrong. Listing ID is missing. Please try again.',
			'nothing_to_buy'        => 'Sorry, Nothing is available to buy. Please try again.',
			'listing_id_required'   => 'Listing ID is required.',
			'invalid_listing_id'    => 'Invalid listing id.',
		];
	}

	/**
	 * Native payment receipt strings rendered by Directorist core.
	 *
	 * @return array
	 */
	private function get_payment_receipt_template_strings() {
		return [
			'thanks'              => 'Thank you for your order!',
			'payment_instruction' => 'Payment Instruction',
			'order_summary'       => 'Order Summary',
			'order_id'            => 'Order ID',
			'date'                => 'Date',
			'transaction_id'      => 'Transaction ID',
			'payment_method'      => 'Payment Method',
			'free_listing'        => 'Free Listing',
			'payment_status'      => 'Payment Status',
			'amount'              => 'Amount',
			'item_summary'        => 'Item summary',
			'subtotal'            => 'Subtotal',
			'discount'            => 'Discount',
			'discount_percent'    => 'Discount ( %d%% )',
			'tax'                 => 'Tax',
			'tax_percent'         => 'Tax ( %d%% )',
			'total_amount'        => 'Total amount',
			'view_listings'       => 'View your listings',
			'retry_payment'       => 'Retry Payment',
			'no_order_id'         => 'Sorry! No order id has been provided.',
			'order_not_found'     => 'Order not found',
			'order_not_found_old' => 'Sorry! order not found.',
			'pending'             => 'Pending',
			'paid'                => 'Paid',
			'failed'              => 'Failed',
			'cancelled'           => 'Cancelled',
			'refunded'            => 'Refunded',
			'unpaid'              => 'Unpaid',
			'expired'             => 'Expired',
			'invalid'             => 'Invalid',
			'featured_listing'    => 'Featured Listing',
			'featured_item'       => 'Featured Listing: %s',
		];
	}

	/**
	 * Check if WPML APIs are available.
	 *
	 * @return bool
	 */
	private function is_wpml_active() {
		return defined( 'ICL_SITEPRESS_VERSION' )
			&& function_exists( 'apply_filters' )
			&& has_filter( 'wpml_current_language' )
			&& has_filter( 'wpml_element_language_details' );
	}

	/**
	 * Directorist directory taxonomy key.
	 *
	 * @return string
	 */
	private function get_directory_taxonomy() {
		return defined( 'ATBDP_DIRECTORY_TYPE' ) ? ATBDP_DIRECTORY_TYPE : 'atbdp_listing_types';
	}

	/**
	 * Sanitize a value for key usage.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function safe_slug( $value ) {
		$slug = sanitize_key( sanitize_title( (string) $value ) );

		return '' !== $slug ? str_replace( '-', '_', $slug ) : 'item';
	}
}
