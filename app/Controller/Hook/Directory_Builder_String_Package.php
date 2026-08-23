<?php
/**
 * Directorist Directory Builder string package integration.
 *
 * Exposes Directorist Directory Builder labels/placeholders as WPML string
 * packages so they can be sent through Translation Dashboard/ATE instead of
 * requiring users to manually edit each translated directory type.
 *
 * @package Directorist_WPML_Integration
 */

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Directory_Builder_String_Package {

    const PACKAGE_KIND         = 'Directorist Directory Builder';
    const PACKAGE_KIND_SLUG    = 'directorist-directory-builder';
    const DIRECTORY_NAME_FIELD = 'directory_name';
    const PACKAGE_INVENTORY_HASH_OPTION_PREFIX = 'directorist_wpml_directory_builder_package_hash_';

    /**
     * Active package service.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Directory type term meta keys that store builder-controlled UI text.
     *
     * @var array
     */
    private $builder_meta_keys = [
        'submission_form_fields',
        'search_form_fields',
        'single_listing_header',
        'single_listings_contents',
        'listings_card_grid_view',
        'listings_card_list_view',
        'submit_button_label',
    ];

    /**
     * Directory type scalar term meta keys that store user-facing option text.
     *
     * These values are not part of the nested builder layouts, but Directorist
     * and Directorist core reads them from the active directory type at runtime.
     * Keep this list explicit so slugs, IDs, status/config values, booleans,
     * shortcodes, URLs and other behavior-driving values are never translated.
     *
     * Extension-owned labels/emails are intentionally excluded from this native
     * Directorist package so extension strings do not block core ATE jobs.
     *
     * @var array
     */
    private $top_level_meta_string_keys = [
        'pending_confirmation_msg'                 => 'Listing Submission: Pending confirmation message',
        'publish_confirmation_msg'                 => 'Listing Submission: Publish confirmation message',
        'submit_button_label_old'                  => 'Listing Submission: Legacy submit button label',
    ];

    /**
     * Add-listing template strings that are not stored in builder meta.
     *
     * @var array
     */
    private $template_ui_strings = [
        'add_listing_wizard_finish'     => [
            'value' => 'Finish',
            'title' => 'Add Listing Wizard: Finish button',
        ],
        'add_listing_wizard_save_next'  => [
            'value' => 'Save & Next',
            'title' => 'Add Listing Wizard: Save and next button',
        ],
        'add_listing_wizard_go_to_next' => [
            'value' => 'Go to Next',
            'title' => 'Add Listing Wizard: Next aria label',
        ],
        'add_listing_publish_title'     => [
            'value' => 'You are about to publish',
            'title' => 'Add Listing Publish Step: Title',
        ],
        'add_listing_publish_subtitle'  => [
            'value' => 'Are you sure you want to publish this listing?',
            'title' => 'Add Listing Publish Step: Subtitle',
        ],
        'add_listing_add_social'        => [
            'value' => 'Add Social',
            'title' => 'Add Listing Social Field: Add social button',
        ],
        'add_listing_map_drag_info'     => [
            'value' => 'You can drag pinpoint to place the correct address manually.',
            'title' => 'Add Listing Map Field: Drag info',
        ],
        'add_listing_latitude'          => [
            'value' => 'Latitude',
            'title' => 'Add Listing Map Field: Latitude label',
        ],
        'add_listing_longitude'         => [
            'value' => 'Longitude',
            'title' => 'Add Listing Map Field: Longitude label',
        ],
        'add_listing_latitude_placeholder' => [
            'value' => 'Enter Latitude eg. 24.89904',
            'title' => 'Add Listing Map Field: Latitude placeholder',
        ],
        'add_listing_longitude_placeholder' => [
            'value' => 'Enter Longitude eg. 91.87198',
            'title' => 'Add Listing Map Field: Longitude placeholder',
        ],
        'add_listing_generate_on_map'   => [
            'value' => 'Generate on Map',
            'title' => 'Add Listing Map Field: Generate button',
        ],
        'add_listing_hide_map'          => [
            'value' => 'Hide Map',
            'title' => 'Add Listing Map Field: Hide map label',
        ],
        'add_listing_upload_drop_here'  => [
            'value' => 'Drop Here',
            'title' => 'Add Listing Image Upload: Drop here label',
        ],
        'add_listing_upload_preview'    => [
            'value' => 'Preview',
            'title' => 'Add Listing Image Upload: Preview label',
        ],
        'add_listing_upload_drag_image' => [
            'value' => 'Drag and drop an image',
            'title' => 'Add Listing Image Upload: Drag image label',
        ],
        'add_listing_upload_or'         => [
            'value' => 'or',
            'title' => 'Add Listing Image Upload: Or separator',
        ],
        'add_listing_upload_or_drag_here' => [
            'value' => 'or drag and drop image here',
            'title' => 'Add Listing Image Upload: Drag here helper',
        ],
        'add_listing_upload_add_more'   => [
            'value' => 'Add More',
            'title' => 'Add Listing Image Upload: Add more label',
        ],
        'add_listing_upload_max_file_size_alert' => [
            'value' => 'Maximum limit for a file is  __DT__',
            'title' => 'Add Listing Image Upload: Max file size alert',
        ],
        'add_listing_upload_max_total_size_alert' => [
            'value' => 'Maximum limit for total file size is __DT__',
            'title' => 'Add Listing Image Upload: Max total file size alert',
        ],
        'add_listing_upload_min_file_items_alert' => [
            'value' => 'Minimum __DT__ file is required',
            'title' => 'Add Listing Image Upload: Minimum file item alert',
        ],
        'add_listing_upload_max_file_items_alert' => [
            'value' => 'Maximum limit for total file is __DT__',
            'title' => 'Add Listing Image Upload: Maximum file item alert',
        ],
        'add_listing_upload_max_file_size_info' => [
            'value' => 'Maximum allowed size per file is __DT__',
            'title' => 'Add Listing Image Upload: Max file size info',
        ],
        'add_listing_upload_max_total_size_info' => [
            'value' => 'Maximum total allowed file size is __DT__',
            'title' => 'Add Listing Image Upload: Max total file size info',
        ],
        'add_listing_upload_unlimited_images' => [
            'value' => 'Unlimited images with this plan!',
            'title' => 'Add Listing Image Upload: Unlimited images info',
        ],
        'add_listing_upload_max_files_allowed' => [
            'value' => 'Maximum __DT__ files are allowed',
            'title' => 'Add Listing Image Upload: Max files allowed info',
        ],
        'add_listing_upload_max_file_allowed' => [
            'value' => 'Maximum __DT__ file is allowed',
            'title' => 'Add Listing Image Upload: Max file allowed info',
        ],
    ];

    /**
     * Directorist runtime strings that can appear outside saved builder meta.
     *
     * They are exposed in the builder package so ATE can translate them, then
     * mirrored into WPML String Translation rows via WPML APIs on admin/package
     * sync. This avoids public output replacement and keeps frontend paths fast.
     *
     * @var array
     */
    private $runtime_ui_strings = [
        'runtime_directorist_back' => [
            'value'    => 'Back',
            'title'    => 'Directorist Runtime: Back label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_skip_preview_submit' => [
            'value'    => 'Skip preview and submit listing',
            'title'    => 'Directorist Runtime: Skip preview submit label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_quick_login' => [
            'value'    => 'Quick Login',
            'title'    => 'Directorist Runtime: Quick login title',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_forgot_password' => [
            'value'    => 'Forgot your password?',
            'title'    => 'Directorist Runtime: Forgot password link',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_login' => [
            'value'    => 'Login',
            'title'    => 'Directorist Runtime: Login label',
            'contexts' => [ 'directorist', 'admin_texts_atbdp_option' ],
        ],
        'runtime_directorist_here' => [
            'value'    => 'Here',
            'title'    => 'Directorist Runtime: Login link text',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_sign_up' => [
            'value'    => 'Sign Up',
            'title'    => 'Directorist Runtime: Sign up link text',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_login_required_simple' => [
            'value'    => 'You need to be logged in to view the content of this page',
            'title'    => 'Directorist Runtime: Restricted page login required message',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_login_required_login_signup' => [
            'value'    => 'You need to be logged in to view the content of this page. You can login/sign up %s',
            'title'    => 'Directorist Runtime: Restricted page login or signup message',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_login_required_login_signup_period' => [
            'value'    => 'You need to be logged in to view the content of this page. You can login/sign up %s.',
            'title'    => 'Directorist Runtime: Restricted page login or signup message with period',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_login_required_anchor_signup' => [
            'value'    => "You need to be logged in to view the content of this page. You can login <a href='%s'>Here</a>. Don't have an account? <a href='%s'>Sign Up</a>",
            'title'    => 'Directorist Runtime: Restricted page login and signup anchors',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_continue' => [
            'value'    => 'Continue',
            'title'    => 'Directorist Runtime: Continue button',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_miles' => [
            'value'    => 'Miles',
            'title'    => 'Directorist Runtime: Miles radius unit',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_popular_badge' => [
            'value'    => 'Popular',
            'title'    => 'Directorist Runtime: Popular badge label',
            'contexts' => [ 'directorist', 'admin_texts_atbdp_option' ],
        ],
        'runtime_directorist_featured_badge' => [
            'value'    => 'Featured',
            'title'    => 'Directorist Runtime: Featured badge label',
            'contexts' => [ 'directorist', 'admin_texts_atbdp_option' ],
        ],
        'runtime_directorist_new_badge' => [
            'value'    => 'New',
            'title'    => 'Directorist Runtime: New badge label',
            'contexts' => [ 'directorist', 'admin_texts_atbdp_option' ],
        ],
        'runtime_directorist_edit_action' => [
            'value'    => 'Edit',
            'title'    => 'Directorist Runtime: Edit action label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_edit_listing_action' => [
            'value'    => 'Edit Listing',
            'title'    => 'Directorist Runtime: Edit listing action label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_preview_changes' => [
            'value'    => 'Preview Changes',
            'title'    => 'Directorist Runtime: Preview changes button',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_review_count_parenthesized_singular' => [
            'value'    => '(%s Review)',
            'title'    => 'Directorist Runtime: Parenthesized singular review count',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_review_count_parenthesized_plural' => [
            'value'    => '(%s Reviews)',
            'title'    => 'Directorist Runtime: Parenthesized plural review count',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_review_count_singular' => [
            'value'    => '%s Review',
            'title'    => 'Directorist Runtime: Singular review count',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_review_count_plural' => [
            'value'    => '%s Reviews',
            'title'    => 'Directorist Runtime: Plural review count',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_select2_search_label' => [
            'value'    => 'Search',
            'title'    => 'Directorist Runtime: Select2 search accessibility label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_select2_no_results' => [
            'value'         => 'No results found',
            'title'         => 'Directorist Runtime: Select2 no results message',
            'contexts'      => [ 'directorist-wpml-integration' ],
            'context_names' => [ 'directorist-wpml-integration' => 'select2_no_results' ],
        ],
        'runtime_directorist_select2_searching' => [
            'value'         => 'Searching…',
            'title'         => 'Directorist Runtime: Select2 searching message',
            'contexts'      => [ 'directorist-wpml-integration' ],
            'context_names' => [ 'directorist-wpml-integration' => 'select2_searching' ],
        ],
        'runtime_directorist_select2_loading_more' => [
            'value'         => 'Loading more results…',
            'title'         => 'Directorist Runtime: Select2 loading more message',
            'contexts'      => [ 'directorist-wpml-integration' ],
            'context_names' => [ 'directorist-wpml-integration' => 'select2_loading_more' ],
        ],
        'runtime_directorist_select2_input_too_short' => [
            'value'         => 'Please enter {count} or more characters',
            'title'         => 'Directorist Runtime: Select2 input too short message',
            'contexts'      => [ 'directorist-wpml-integration' ],
            'context_names' => [ 'directorist-wpml-integration' => 'select2_input_too_short' ],
        ],
        'runtime_directorist_select2_input_too_long' => [
            'value'         => 'Please delete {count} character',
            'title'         => 'Directorist Runtime: Select2 input too long singular message',
            'contexts'      => [ 'directorist-wpml-integration' ],
            'context_names' => [ 'directorist-wpml-integration' => 'select2_input_too_long' ],
        ],
        'runtime_directorist_select2_input_too_long_plural' => [
            'value'         => 'Please delete {count} characters',
            'title'         => 'Directorist Runtime: Select2 input too long plural message',
            'contexts'      => [ 'directorist-wpml-integration' ],
            'context_names' => [ 'directorist-wpml-integration' => 'select2_input_too_long_pl' ],
        ],
        'runtime_directorist_select2_maximum_selected' => [
            'value'         => 'You can only select {count} item',
            'title'         => 'Directorist Runtime: Select2 maximum selected singular message',
            'contexts'      => [ 'directorist-wpml-integration' ],
            'context_names' => [ 'directorist-wpml-integration' => 'select2_maximum_selected' ],
        ],
        'runtime_directorist_select2_maximum_selected_plural' => [
            'value'         => 'You can only select {count} items',
            'title'         => 'Directorist Runtime: Select2 maximum selected plural message',
            'contexts'      => [ 'directorist-wpml-integration' ],
            'context_names' => [ 'directorist-wpml-integration' => 'select2_maximum_selected_pl' ],
        ],
        'runtime_directorist_select2_error_loading' => [
            'value'         => 'The results could not be loaded.',
            'title'         => 'Directorist Runtime: Select2 error loading message',
            'contexts'      => [ 'directorist-wpml-integration' ],
            'context_names' => [ 'directorist-wpml-integration' => 'select2_error_loading' ],
        ],
        'runtime_directorist_select2_remove_all_items' => [
            'value'         => 'Remove all items',
            'title'         => 'Directorist Runtime: Select2 remove all items message',
            'contexts'      => [ 'directorist-wpml-integration' ],
            'context_names' => [ 'directorist-wpml-integration' => 'select2_remove_all_items' ],
        ],
        'runtime_directorist_sort_by' => [
            'value'    => 'Sort By',
            'title'    => 'Directorist Runtime: Sort label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_save_preview' => [
            'value'    => 'Save & Preview',
            'title'    => 'Directorist Runtime: Save and preview button',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_go_back' => [
            'value'    => 'Go Back',
            'title'    => 'Directorist Runtime: Single listing back link',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_report' => [
            'value'    => 'Report',
            'title'    => 'Directorist Runtime: Report link',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_report_abuse' => [
            'value'    => 'Report Abuse',
            'title'    => 'Directorist Runtime: Report abuse modal title',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_your_complaint' => [
            'value'    => 'Your Complaint',
            'title'    => 'Directorist Runtime: Report complaint field label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_submit' => [
            'value'    => 'Submit',
            'title'    => 'Directorist Runtime: Submit button',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_submit_listing' => [
            'value'    => 'Submit Listing',
            'title'    => 'Directorist Runtime: Submit listing label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_review' => [
            'value'    => 'Review',
            'title'    => 'Directorist Runtime: Review label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_reviews' => [
            'value'    => 'Reviews',
            'title'    => 'Directorist Runtime: Reviews label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_lowercase_review' => [
            'value'    => 'review',
            'title'    => 'Directorist Runtime: Lowercase review label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_comment' => [
            'value'    => 'Comment',
            'title'    => 'Directorist Runtime: Comment label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_lowercase_comment' => [
            'value'    => 'comment',
            'title'    => 'Directorist Runtime: Lowercase comment label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_read_more' => [
            'value'    => 'Read More',
            'title'    => 'Directorist Runtime: Read more link',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_show_less' => [
            'value'    => 'Show Less',
            'title'    => 'Directorist Runtime: Show less link',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_show' => [
            'value'    => 'Show',
            'title'    => 'Directorist Runtime: Show label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_save' => [
            'value'    => 'Save',
            'title'    => 'Directorist Runtime: Save button',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_cancel' => [
            'value'    => 'Cancel',
            'title'    => 'Directorist Runtime: Cancel button',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_keep_it' => [
            'value'    => 'Keep It',
            'title'    => 'Directorist Runtime: Keep confirmation button',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_no' => [
            'value'    => 'No',
            'title'    => 'Directorist Runtime: No confirmation button',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_filter' => [
            'value'    => 'Filter',
            'title'    => 'Directorist Runtime: Filter label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_uncategorized' => [
            'value'    => 'Uncategorized',
            'title'    => 'Directorist Runtime: Uncategorized label',
            'contexts' => [ 'directorist' ],
        ],
        'runtime_directorist_no_reviews_yet' => [
            'value'    => 'There are no reviews yet.',
            'title'    => 'Directorist Runtime: Empty reviews message',
            'contexts' => [ 'directorist' ],
        ],
    ];

    /**
     * Native Directorist strings found in frontend templates/localized data.
     *
     * Keep this as an explicit source inventory. These are registered into the
     * directory builder package for ATE, then synced back to their native
     * Directorist string context only after an ATE package save.
     *
     * @var array
     */
    private $native_runtime_ui_string_values = [
        'runtime_directorist_native_username_or_email_address' => 'Username or Email Address',
        'runtime_directorist_native_password' => 'Password',
        'runtime_directorist_native_remember_me' => 'Remember Me',
        'runtime_directorist_native_log_in' => 'Log In',
        'runtime_directorist_native_lost_your_password_please_enter_your_email_address_you_will_receive_a' => 'Lost your password? Please enter your email address. You will receive a link to create a new password via email.',
        'runtime_directorist_native_e_mail' => 'E-mail:',
        'runtime_directorist_native_get_new_password' => 'Get New Password',
        'runtime_directorist_native_username' => 'Username',
        'runtime_directorist_native_first_name' => 'First Name',
        'runtime_directorist_native_last_name' => 'Last Name',
        'runtime_directorist_native_about_bio' => 'About/bio',
        'runtime_directorist_native_already_have_an_account_please_login' => 'Already have an account? Please login',
        'runtime_directorist_native_terms_conditions' => 'terms & conditions',
        'runtime_directorist_native_privacy_policy' => 'Privacy & Policy',
        'runtime_directorist_native_i_am_an_author' => 'I am an author',
        'runtime_directorist_native_i_am_a_user' => 'I am a user',
        'runtime_directorist_native_email' => 'Email ',
        'runtime_directorist_native_already_have_an_account_please_sign_in' => 'Already have an account? Please Sign in',
        'runtime_directorist_native_forgot_password' => 'Forgot Password?',
        'runtime_directorist_native_your_review_has_been_received_it_requires_admin_approval_to_publish' => 'Your review has been received. It requires admin approval to publish.',
        'runtime_directorist_native_sorry_you_need_to_login_first' => 'Sorry, you need to login first.',
        'runtime_directorist_native_warning' => 'WARNING!',
        'runtime_directorist_native_success' => 'SUCCESS!',
        'runtime_directorist_native_you_can_not_add_more_than_one_review_refresh_the_page_to_edit_or_delet' => 'You can not add more than one review. Refresh the page to edit or delete your review!,',
        'runtime_directorist_native_sorry_your_review_already_in_process' => 'Sorry! your review already in process.',
        'runtime_directorist_native_reviews_saved_successfully' => 'Reviews Saved Successfully!',
        'runtime_directorist_native_something_went_wrong_check_the_form_and_try_again' => 'Something went wrong. Check the form and try again!!!',
        'runtime_directorist_native_reviews_loaded' => 'Reviews Loaded!',
        'runtime_directorist_native_no_more_reviews_available' => 'NO MORE REVIEWS AVAILABLE!,',
        'runtime_directorist_native_you_do_not_have_any_review_to_delete_refresh_the_page_to_submit_new_re' => 'You do not have any review to delete. Refresh the page to submit new review!!!,',
        'runtime_directorist_native_are_you_sure' => 'Are you sure?',
        'runtime_directorist_native_do_you_really_want_to_remove_this_review' => 'Do you really want to remove this review!',
        'runtime_directorist_native_yes_delete_it' => 'Yes, Delete it!',
        'runtime_directorist_native_something_went_wrong_try_again' => 'Something went wrong!, Try again',
        'runtime_directorist_native_do_you_really_want_to_delete_this_item' => 'Do you really want to delete this item?!',
        'runtime_directorist_native_deleted' => 'Deleted!!',
        'runtime_directorist_native_error' => 'ERROR!!',
        'runtime_directorist_native_something_went_wrong_try_again_2' => 'Something went wrong!!!, Try again',
        'runtime_directorist_native_select_or_upload_a_profile_picture' => 'Select or Upload a profile picture',
        'runtime_directorist_native_use_this_image' => 'Use this Image',
        'runtime_directorist_native_sending_the_message_please_wait' => 'Sending the message, please wait...',
        'runtime_directorist_native_loading_more' => 'Loading more...',
        'runtime_directorist_native_see_more' => 'See More',
        'runtime_directorist_native_see_less' => 'See Less',
        'runtime_directorist_native_are_you_sure_2' => 'Are you sure',
        'runtime_directorist_native_do_you_really_want_to_remove_this_social_link' => 'Do you really want to remove this Social Link!',
        'runtime_directorist_native_do_you_really_want_to_remove_this_faq' => 'Do you really want to remove this FAQ!',
        'runtime_directorist_native_deleted_2' => 'Deleted!',
        'runtime_directorist_native_you_can_only_use_s' => 'You can only use %s',
        'runtime_directorist_native_please_wait_your_submission_is_being_processed' => 'Please wait, your submission is being processed.',
        'runtime_directorist_native_please_wait_your_selected_images_being_uploaded' => 'Please wait, your selected images being uploaded.',
        'runtime_directorist_native_listing_gallery_has_invalid_files' => 'Listing gallery has invalid files',
        'runtime_directorist_native_sorry_you_have_crossed_the_maximum_image_limit' => 'Sorry! You have crossed the maximum image limit',
        'runtime_directorist_native_select_an_icon' => 'Select an icon',
        'runtime_directorist_native_select_or_upload_slider_image' => 'Select or Upload Slider Image',
        'runtime_directorist_native_select_category_image' => 'Select Category Image',
        'runtime_directorist_native_select_preview_image' => 'Select Preview Image',
        'runtime_directorist_native_insert_preview_image' => 'Insert Preview Image',
        'runtime_directorist_native_select_or_upload_image' => 'Select or upload image',
        'runtime_directorist_native_click_to_select_icon' => 'Click to select icon',
        'runtime_directorist_native_filter_by_name' => 'Filter By Name',
        'runtime_directorist_native_filter_by_icon_pack' => 'Filter By Icon Pack',
        'runtime_directorist_native_kilometers' => ' Kilometers',
        'runtime_directorist_native_miles' => ' Miles',
        'runtime_directorist_native_show_more' => 'Show More',
        'runtime_directorist_native_added_to_favorite' => 'Added to favorite',
        'runtime_directorist_native_please_login_first' => 'Please login first',
        'runtime_directorist_native_sending_user_info_please_wait' => 'Sending user info, please wait...',
        'runtime_directorist_native_wrong_username_or_password' => 'Wrong username or password.',
        'runtime_directorist_native_enquiries' => 'Enquiries',
        'runtime_directorist_native_my_enquiries' => 'My Enquiries',
        'runtime_directorist_native_track_and_manage_all_your_incoming_messages' => 'Track and manage all your incoming messages',
        'runtime_directorist_native_total_enquiries' => 'Total Enquiries',
        'runtime_directorist_native_new_messages' => 'New Messages',
        'runtime_directorist_native_this_week' => 'This Week',
        'runtime_directorist_native_total_resolved' => 'Total Resolved',
        'runtime_directorist_native_enquiry' => 'Enquiry',
        'runtime_directorist_native_listing' => 'Listing',
        'runtime_directorist_native_sender' => 'Sender',
        'runtime_directorist_native_status' => 'Status',
        'runtime_directorist_native_search_enquiries' => 'Search enquiries...',
        'runtime_directorist_native_view' => 'View',
        'runtime_directorist_native_mark_as_read' => 'Mark as read',
        'runtime_directorist_native_delete' => 'Delete',
        'runtime_directorist_native_are_you_sure_to_delete_this_item' => 'Are you sure to delete this item?',
        'runtime_directorist_native_are_you_sure_to_delete_d_items' => 'Are you sure to delete %d items?',
        'runtime_directorist_native_this_action_cannot_be_undone' => 'This action cannot be undone.',
        'runtime_directorist_native_passwords_cannot_be_empty' => 'Passwords cannot be empty.',
        'runtime_directorist_native_passwords_do_not_match' => 'Passwords do not match!',
        'runtime_directorist_native_password_changed_successfully_please_click_here_to_login' => 'Password changed successfully. Please <a href="%s">click here to login</a>.',
        'runtime_directorist_native_email_verification_successful_please_click_here_to_login' => 'Email verification successful. Please <a href="%s">click here to login</a>.',
        'runtime_directorist_native_email_address_cannot_be_empty' => 'Email address cannot be empty.',
        'runtime_directorist_native_invalid_e_mail_address' => 'Invalid e-mail address.',
        'runtime_directorist_native_there_is_no_user_registered_with_that_email_address' => 'There is no user registered with that email address.',
        'runtime_directorist_native_s_reset_your_password' => '[%s] Reset Your Password',
        'runtime_directorist_native_password_reset_request' => 'Password Reset Request',
        'runtime_directorist_native_a_password_reset_email_has_been_sent_to_the_email_address_on_file_for' => 'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox.',
        'runtime_directorist_native_something_went_wrong_unable_to_send_the_password_reset_email_if_the_is' => 'Something went wrong, unable to send the password reset email. If the issue persists please contact with the site administrator.',
        'runtime_directorist_native_error_s' => '<strong>ERROR: </strong> %s',
        'runtime_directorist_native_click_s_to_login' => 'Click %s to login.',
        'runtime_directorist_native_or_click_s_to_login' => 'Or click %s to login.',
        'runtime_directorist_native_clear_all' => 'Clear All',
        'runtime_directorist_native_nothing_to_show' => 'Nothing to show!',
        'runtime_directorist_native_total_amount' => 'Total amount',
        'runtime_directorist_native_complete_submission' => 'Complete Submission',
        'runtime_directorist_native_pay_now' => 'Pay Now',
        'runtime_directorist_native_discount' => 'Discount',
        'runtime_directorist_native_discount_d' => 'Discount ( %d%% )',
        'runtime_directorist_native_tax' => 'Tax',
        'runtime_directorist_native_tax_d' => 'Tax ( %d%% )',
        'runtime_directorist_native_view_your_listings' => 'View your listings',
        'runtime_directorist_native_button_text' => 'Button Text',
        'runtime_directorist_native_website_url' => 'Website URL',
        'runtime_directorist_native_maximum_file_size_s' => 'Maximum file size: %s',
        'runtime_directorist_native_allowed_files' => 'Allowed Files',
        'runtime_directorist_native_allowed_files_2' => 'Allowed files',
        'runtime_directorist_native_file_size_error_you_tried_to_upload_a_file_over_s' => 'File size error : You tried to upload a file over %s',
        'runtime_directorist_native_file_type_error_allowed_file_types_s' => 'File type error. Allowed file types: %s',
        'runtime_directorist_native_you_have_reached_your_upload_limit_of_s_files' => 'You have reached your upload limit of %s files.',
        'runtime_directorist_native_you_may_only_upload_s_files_with_this_package_please_try_again' => 'You may only upload %s files with this package, please try again.',
        'runtime_directorist_native_set' => 'Set',
        'runtime_directorist_native_promote_your_listing_to_the_top_of_search_results_and_listings_pages_f' => 'Promote your listing to the top of search results and listings pages for a specific duration, with an additional payment.',
        'runtime_directorist_native_ultra_high_s' => 'Ultra High (%s)',
        'runtime_directorist_native_moderate_s' => 'Moderate (%s)',
        'runtime_directorist_native_economy_s' => 'Economy (%s)',
        'runtime_directorist_native_cheap_s' => 'Cheap (%s)',
        'runtime_directorist_native_total_amount_s' => 'Total amount [%s]',
        'runtime_directorist_native_select_location' => 'Select Location',
        'runtime_directorist_native_enter_your_name' => 'Enter your name',
        'runtime_directorist_native_enter_your_email' => 'Enter your email',
        'runtime_directorist_native_enter_your_website' => 'Enter your website',
        'runtime_directorist_native_share_your_experience_and_help_others_make_better_choices' => 'Share your experience and help others make better choices',
        'runtime_directorist_native_cancel_reply' => 'Cancel Reply',
        'runtime_directorist_native_leave_a_review' => 'Leave a Review',
        'runtime_directorist_native_submit_your_review' => 'Submit Your Review',
        'runtime_directorist_native_send_email' => 'Send Email',
        'runtime_directorist_native_member_since_s_ago' => 'Member since %s ago',
        'runtime_directorist_native_listing_2' => 'listing',
        'runtime_directorist_native_don_t_have_an_account_sign_up' => 'Don\'t have an account? <a href=\'%s\'>Sign up</a>',
    ];

    /**
     * Prevent recursive term meta lookups.
     *
     * @var bool
     */
    private static $resolving_term_meta = false;

    /**
     * Track packages registered during a request.
     *
     * @var array
     */
    private static $registered_packages = [];

    /**
     * Existing package strings keyed by package and string name.
     *
     * @var array
     */
    private static $package_string_cache = [];

    /**
     * Current package string names collected during WPML package registration.
     *
     * @var array
     */
    private static $package_string_names = [];

    /**
     * Current package string values collected during WPML package registration.
     *
     * @var array
     */
    private static $package_string_data = [];

    /**
     * Completed package translations resolved by source value.
     *
     * @var array
     */
    private static $package_value_translation_cache = [];

    /**
     * Whether a translated directory term is being created from an ATE package save.
     *
     * @var bool
     */
    private static $creating_translated_directory_type = false;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        self::$instance = $this;

        add_filter( 'wpml_active_string_package_kinds', [ $this, 'register_package_kind' ] );
        add_action( 'wpml_register_string_packages', [ $this, 'register_directory_builder_packages' ] );
        add_action( 'wpml_register_string_packages', [ $this, 'cleanup_unused_directory_builder_package_strings' ], 100 );
        add_action( 'wpml_register_string_packages', [ $this, 'mark_changed_directory_builder_packages_need_update' ], 120 );
        add_action( 'admin_init', [ $this, 'cleanup_obsolete_directory_builder_packages' ], 100 );
        add_action( 'admin_init', [ $this, 'cleanup_legacy_add_listing_strings' ], 100 );
        add_action( 'admin_init', [ $this, 'sync_completed_directory_builder_packages' ], 120 );

        add_action( 'created_' . $this->get_directory_taxonomy(), [ $this, 'register_term_package_on_save' ], 30, 2 );
        add_action( 'edited_' . $this->get_directory_taxonomy(), [ $this, 'register_term_package_on_save' ], 30, 2 );
        add_action( 'directorist_after_update_directory_type', [ $this, 'refresh_directory_builder_package_on_save' ], 30, 1 );
        add_action( 'wpml_save_external', [ $this, 'sync_builder_meta_after_package_translation' ], 20, 3 );

        add_filter( 'wpml_get_translatable_item', [ $this, 'prepare_package_for_translation_management' ], 20, 3 );
        add_filter( 'get_term_metadata', [ $this, 'translate_builder_term_meta' ], 30, 4 );
        add_filter( 'atbdp_add_listing_page_template', [ $this, 'translate_add_listing_template_ui' ], 20, 2 );
    }

    /**
     * Get the active package service.
     *
     * @return self|null
     */
    public static function instance() {
        return self::$instance;
    }

    /**
     * Expose the existing safe builder-string walker to listing ATE payloads.
     *
     * @param string $meta_key Builder meta key.
     * @param mixed  $data     Builder meta value.
     * @return array
     */
    public function get_translatable_meta_string_map( $meta_key, $data ) {
        $strings = [];

        if ( ! is_array( $data ) ) {
            return $strings;
        }

        foreach ( $this->extract_strings( $data, $meta_key ) as $string ) {
            if ( ! empty( $string['name'] ) && isset( $string['value'] ) ) {
                $strings[ $string['name'] ] = $string['value'];
            }
        }

        return $strings;
    }

    /**
     * Apply an ATE string map through the existing safe builder merger.
     *
     * @param string $meta_key     Builder meta key.
     * @param array  $source       Source builder data.
     * @param array  $translations Translations keyed by package string name.
     * @param array  $existing     Existing target builder data.
     * @param string $language     Target language.
     * @return array
     */
    public function apply_translatable_meta_string_map( $meta_key, array $source, array $translations, array $existing = [], $language = '' ) {
        return $this->apply_named_translations_to_meta_value(
            $source,
            $meta_key,
            $translations,
            $existing,
            [],
            [],
            $language
        );
    }

    /**
     * Register package kind for WPML Translation Dashboard.
     *
     * @param array $kinds Package kind definitions.
     * @return array
     */
    public function register_package_kind( $kinds ) {
        $kinds[ self::PACKAGE_KIND_SLUG ] = [
            'title'  => self::PACKAGE_KIND,
            'slug'   => self::PACKAGE_KIND_SLUG,
            'plural' => 'Directorist Directory Builders',
        ];

        return $kinds;
    }

    /**
     * Register all default-language directory builder packages.
     *
     * @return void
     */
    public function register_directory_builder_packages() {
        if ( ! $this->is_wpml_active() ) {
            return;
        }

        $default_language = apply_filters( 'wpml_default_language', null );
        $current_language = apply_filters( 'wpml_current_language', null );

        if ( $default_language && $current_language && $default_language !== $current_language ) {
            return;
        }

        if ( ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() ) {
            return;
        }

        foreach ( $this->get_source_directory_ids() as $directory_id ) {
            $this->register_directory_builder_package( $directory_id );
        }
    }

    /**
     * Register package after a directory term is created/edited.
     *
     * @param int $term_id Term ID.
     * @param int $tt_id   Term taxonomy ID.
     * @return void
     */
    public function register_term_package_on_save( $term_id, $tt_id = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        if ( self::$creating_translated_directory_type ) {
            return;
        }

        $this->refresh_directory_builder_package_on_save( $term_id );
    }

    /**
     * Refresh package strings after Directorist saves directory builder data.
     *
     * @param int $directory_id Directory type term ID.
     * @return void
     */
    public function refresh_directory_builder_package_on_save( $directory_id ) {
        if ( ! $this->is_wpml_active() ) {
            return;
        }

        $source_directory_id = $this->get_source_directory_id( (int) $directory_id );
        if ( $source_directory_id <= 0 ) {
            return;
        }

        $package = $this->get_package( $source_directory_id );
        if ( ! empty( $package['name'] ) ) {
            unset( self::$registered_packages[ $package['name'] ] );
        }

        $this->register_directory_builder_package( $source_directory_id );
    }

    /**
     * Make Directorist builder packages fully compatible with WPML TM/ATE.
     *
     * WPML's legacy md5/status calculation only uses package string data when
     * the translatable item is explicitly marked as external.
     *
     * @param mixed      $item    Translatable item resolved by WPML.
     * @param int|object $package Package identifier passed by WPML.
     * @param string     $type    Translation element type/prefix.
     * @return mixed
     */
    public function prepare_package_for_translation_management( $item, $package, $type = 'package' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        if ( ! $this->is_directory_builder_package_item( $item ) ) {
            return $item;
        }

        $item->external_type = true;

        if ( method_exists( $item, 'update_strings_data' ) ) {
            $item->update_strings_data();
        }

        return $item;
    }

    /**
     * Persist completed ATE package translations into translated builder term meta.
     *
     * Directorist's builder admin loads the whole term meta set at once, so it
     * bypasses the single-meta translation filter used on frontend reads.
     *
     * @param string   $element_type_prefix External element type prefix.
     * @param object   $job                 WPML translation job.
     * @param callable $decoder             WPML field decoder.
     * @return void
     */
    public function sync_builder_meta_after_package_translation( $element_type_prefix, $job, $decoder ) {
        if ( 'package' !== $element_type_prefix || ! $this->is_wpml_active() || empty( $job->language_code ) || empty( $job->elements ) || ! is_array( $job->elements ) ) {
            return;
        }

        $package_context = apply_filters( 'wpml_get_package_type_prefix', $element_type_prefix, $job->original_doc_id );
        $source_id       = $this->get_source_directory_id_from_package_context( $package_context );

        if ( $source_id <= 0 ) {
            return;
        }

        $translations = $this->get_job_translations_by_string_name( $job, $decoder, $package_context );
        if ( empty( $translations ) ) {
            return;
        }

        $target_id = $this->get_translated_directory_id( $source_id, $job->language_code );

        if ( $target_id <= 0 ) {
            $target_id = $this->create_translated_directory_type( $source_id, $job->language_code, $translations );
        }

        if ( $target_id <= 0 || $target_id === $source_id ) {
            return;
        }

        $updated = false;

        $package = $this->get_package( $source_id );

        foreach ( $this->builder_meta_keys as $meta_key ) {
            $source_value = $this->get_raw_term_meta( $source_id, $meta_key, true );
            $target_value = $this->get_raw_term_meta( $target_id, $meta_key, true );

            if ( is_array( $source_value ) ) {
                $translated_value = $this->apply_named_translations_to_meta_value(
                    $source_value,
                    $meta_key,
                    $translations,
                    is_array( $target_value ) ? $target_value : [],
                    [],
                    $package,
                    $job->language_code
                );

                if ( 'submission_form_fields' === $meta_key ) {
                    $translated_value = $this->apply_add_listing_compatibility_translations( $source_value, $translated_value, $source_id, $translations, $job->language_code );
                }
            } elseif ( $this->is_translatable_string( $meta_key, $source_value, [ $meta_key ] ) ) {
                $string           = $this->build_string_data( $meta_key, [ $meta_key ], $source_value );
                $candidate_value  = ! empty( $translations[ $string['name'] ] )
                    ? $translations[ $string['name'] ]
                    : '';

                if ( $this->is_usable_translation( $source_value, $candidate_value, $job->language_code ) ) {
                    $translated_value = $candidate_value;
                } elseif ( $this->has_existing_translation_value( $target_value, $job->language_code ) ) {
                    $translated_value = $target_value;
                } else {
                    $translated_value = $source_value;
                }
            } else {
                continue;
            }

            if ( $translated_value === $target_value ) {
                continue;
            }

            update_term_meta( $target_id, $meta_key, $translated_value );
            $updated = true;
        }

        if ( $this->sync_top_level_meta_strings( $source_id, $target_id, $translations, $job->language_code ) ) {
            $updated = true;
        }

        $this->sync_runtime_string_translations( $source_id, $job->language_code, $translations );

        if ( $updated ) {
            clean_term_cache( $target_id, $this->get_directory_taxonomy() );
        }
    }

    /**
     * Sync already-completed package translations on bounded admin/WP-CLI flows.
     *
     * This handles the case where integration code starts exposing new package
     * strings after an ATE job was already completed. It is intentionally not
     * hooked to public requests.
     *
     * @return void
     */
    public function sync_completed_directory_builder_packages() {
        if ( ! $this->is_wpml_active() || ! $this->should_sync_completed_package_translations() ) {
            return;
        }

        foreach ( $this->get_source_directory_ids() as $source_id ) {
            $translations_by_language = $this->get_completed_package_translations_by_language( $this->get_package( $source_id ) );

            foreach ( $translations_by_language as $language_code => $translations ) {
                $target_id = $this->get_translated_directory_id( $source_id, $language_code );

                if ( $target_id > 0 && $target_id !== $source_id && $this->sync_top_level_meta_strings( $source_id, $target_id, $translations, $language_code ) ) {
                    clean_term_cache( $target_id, $this->get_directory_taxonomy() );
                }

                $this->sync_runtime_string_translations( $source_id, $language_code, $translations );
            }
        }
    }

    /**
     * Register all translatable builder strings for one directory type.
     *
     * @param int $directory_id Directory type term ID.
     * @return void
     */
    public function register_directory_builder_package( $directory_id ) {
        if ( ! $this->is_wpml_active() ) {
            return;
        }

        $source_directory_id = $this->get_source_directory_id( (int) $directory_id );
        if ( $source_directory_id <= 0 ) {
            return;
        }

        $package = $this->get_package( $source_directory_id );
        if ( empty( $package['name'] ) || isset( self::$registered_packages[ $package['name'] ] ) ) {
            return;
        }

        self::$registered_packages[ $package['name'] ] = true;

        $source_term = get_term( $source_directory_id, $this->get_directory_taxonomy() );

        if ( $source_term && ! is_wp_error( $source_term ) && '' !== trim( $source_term->name ) ) {
            $this->register_package_string(
                $package,
                [
                    'name'  => self::DIRECTORY_NAME_FIELD,
                    'value' => $source_term->name,
                    'title' => 'Directory Name',
                    'type'  => 'LINE',
                ]
            );
        }

        foreach ( $this->get_add_listing_compatibility_strings( $source_directory_id, $source_term ) as $string ) {
            $this->register_package_string( $package, $string );
        }

        foreach ( $this->builder_meta_keys as $meta_key ) {
            $meta_value = $this->get_raw_term_meta( $source_directory_id, $meta_key, true );

            if ( is_array( $meta_value ) ) {
                foreach ( $this->extract_strings( $meta_value, $meta_key ) as $string ) {
                    $this->register_package_string( $package, $string );
                }

                continue;
            }

            if ( $this->is_translatable_string( $meta_key, $meta_value, [ $meta_key ] ) ) {
                $this->register_package_string(
                    $package,
                    $this->build_string_data( $meta_key, [ $meta_key ], $meta_value )
                );
            }
        }

        foreach ( $this->get_top_level_meta_strings( $source_directory_id ) as $string ) {
            $this->register_package_string( $package, $string );
        }

        foreach ( $this->template_ui_strings as $string_name => $string_data ) {
            if ( empty( $string_data['value'] ) || ! is_string( $string_data['value'] ) ) {
                continue;
            }

            $this->register_package_string(
                $package,
                [
                    'name'  => $string_name,
                    'title' => $string_data['title'],
                    'type'  => 'LINE',
                    'value' => $string_data['value'],
                ]
            );
        }

        foreach ( $this->get_runtime_ui_strings() as $string_name => $string_data ) {
            if ( empty( $string_data['value'] ) || ! is_string( $string_data['value'] ) ) {
                continue;
            }

            $this->register_package_string(
                $package,
                [
                    'name'  => $string_name,
                    'title' => $string_data['title'],
                    'type'  => strlen( wp_strip_all_tags( $string_data['value'] ) ) > 120 ? 'AREA' : 'LINE',
                    'value' => $string_data['value'],
                ]
            );
        }

    }

    /**
     * Track a current string from either half of the shared builder package.
     *
     * @param array  $package     Package data.
     * @param string $string_name String name.
     * @param array  $string      Optional string data.
     * @return void
     */
    public static function track_package_string( array $package, $string_name, array $string = [] ) {
        if ( empty( $package['kind_slug'] ) || empty( $package['name'] ) || '' === (string) $string_name ) {
            return;
        }

        $package_key = $package['kind_slug'] . ':' . $package['name'];
        $string_name = (string) $string_name;

        self::$package_string_names[ $package_key ][ $string_name ] = true;

        if ( ! empty( $string['value'] ) && is_string( $string['value'] ) ) {
            self::$package_string_data[ $package_key ][ $string_name ] = [
                'value' => $string['value'],
                'type'  => ! empty( $string['type'] ) ? (string) $string['type'] : 'LINE',
                'title' => ! empty( $string['title'] ) ? (string) $string['title'] : $string_name,
            ];
        }
    }

    /**
     * Get all runtime strings exposed through the package.
     *
     * @return array
     */
    private function get_runtime_ui_strings() {
        $strings = $this->runtime_ui_strings;

        foreach ( $this->native_runtime_ui_string_values as $string_name => $value ) {
            if ( isset( $strings[ $string_name ] ) || ! is_string( $value ) || '' === trim( $value ) ) {
                continue;
            }

            $strings[ $string_name ] = [
                'value'    => $value,
                'title'    => 'Directorist Native Runtime: ' . $this->build_runtime_string_title( $value ),
                'contexts' => [ 'directorist' ],
            ];
        }

        return $strings;
    }

    /**
     * Build a readable package field title for native runtime strings.
     *
     * @param string $value Source string value.
     * @return string
     */
    private function build_runtime_string_title( $value ) {
        $title = trim( wp_strip_all_tags( (string) $value ) );
        $title = preg_replace( '/\s+/', ' ', $title );

        if ( '' === $title ) {
            return 'Runtime string';
        }

        if ( strlen( $title ) > 80 ) {
            $title = substr( $title, 0, 77 ) . '...';
        }

        return $title;
    }

    /**
     * Mark existing package translations stale when the emitted field inventory changes.
     *
     * WPML can keep opening an already-completed ATE job payload after new package
     * strings are registered. Comparing the emitted field-name inventory gives
     * WPML a bounded admin-side signal to rebuild the package job through its
     * normal Translation Dashboard/ATE flow. This never runs on public requests.
     *
     * @return void
     */
    public function mark_changed_directory_builder_packages_need_update() {
        if ( ! $this->is_wpml_active() || ! $this->should_mark_package_inventory_changes() || empty( self::$package_string_names ) ) {
            return;
        }

        foreach ( self::$package_string_names as $package_key => $current_names ) {
            list( $kind_slug, $package_name ) = array_pad( explode( ':', $package_key, 2 ), 2, '' );

            if ( self::PACKAGE_KIND_SLUG !== $kind_slug || '' === $package_name || empty( $current_names ) ) {
                continue;
            }

            $package = [
                'kind_slug' => $kind_slug,
                'name'      => $package_name,
            ];

            $package_id = $this->get_package_id( $package );
            if ( $package_id <= 0 ) {
                continue;
            }

            $string_names = array_keys( $current_names );
            sort( $string_names, SORT_STRING );

            $inventory_hash = $this->get_package_inventory_hash( $string_names );
            if ( '' === $inventory_hash ) {
                continue;
            }

            $option_name   = $this->get_package_inventory_hash_option_name( $package_name );
            $previous_hash = (string) get_option( $option_name, '' );

            $job_missing_current_fields = $this->latest_package_jobs_are_missing_fields( $package_id, $string_names );

            if ( $inventory_hash !== $previous_hash ) {
                update_option( $option_name, $inventory_hash, false );

                if ( $job_missing_current_fields ) {
                    $this->mark_package_translations_need_update( $package_id );
                }

                continue;
            }

            if ( $job_missing_current_fields ) {
                $this->mark_package_translations_need_update( $package_id );
            }
        }
    }

    /**
     * Build package strings for whitelisted scalar directory term meta.
     *
     * @param int $source_directory_id Source directory type term ID.
     * @return array
     */
    private function get_top_level_meta_strings( $source_directory_id ) {
        $strings = [];

        foreach ( $this->top_level_meta_string_keys as $meta_key => $title ) {
            $meta_value = $this->get_raw_term_meta( $source_directory_id, $meta_key, true );

            if ( ! $this->is_translatable_top_level_meta_value( $meta_key, $meta_value ) ) {
                continue;
            }

            $strings[] = $this->build_top_level_meta_string_data( $meta_key, $title, $meta_value );
        }

        return $strings;
    }

    /**
     * Build WPML package string metadata for one top-level term meta key.
     *
     * @param string $meta_key Meta key.
     * @param string $title    Human-readable title for ATE.
     * @param string $value    Source value.
     * @return array
     */
    private function build_top_level_meta_string_data( $meta_key, $title, $value ) {
        return [
            'name'  => 'top_meta__' . $this->safe_slug( $meta_key ),
            'title' => $title,
            'type'  => strlen( wp_strip_all_tags( $value ) ) > 120 ? 'AREA' : 'LINE',
            'value' => $value,
        ];
    }

    /**
     * Check whether a whitelisted top-level term meta value is safe to expose.
     *
     * @param string $meta_key Meta key.
     * @param mixed  $value    Meta value.
     * @return bool
     */
    private function is_translatable_top_level_meta_value( $meta_key, $value ) {
        if ( empty( $this->top_level_meta_string_keys[ $meta_key ] ) || ! is_string( $value ) || '' === trim( $value ) ) {
            return false;
        }

        $trimmed_value = trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
        $lower_value   = strtolower( $trimmed_value );

        if ( '' === $trimmed_value || is_numeric( $trimmed_value ) || in_array( $lower_value, [ 'true', 'false', 'yes', 'no', 'on', 'off' ], true ) ) {
            return false;
        }

        if ( filter_var( $trimmed_value, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        if ( preg_match( '/^\[[^\]]+\]$/', $trimmed_value ) ) {
            return false;
        }

        return true;
    }

    /**
     * Sync top-level scalar term meta from completed package translations.
     *
     * @param int   $source_id     Source directory type term ID.
     * @param int   $target_id     Translated directory type term ID.
     * @param array  $translations  Translated strings keyed by package string name.
     * @param string $language_code Target language.
     * @return bool
     */
    private function sync_top_level_meta_strings( $source_id, $target_id, array $translations, $language_code = '' ) {
        $updated = false;
        $package = $this->get_package( $source_id );

        foreach ( $this->top_level_meta_string_keys as $meta_key => $title ) {
            $source_value = $this->get_raw_term_meta( $source_id, $meta_key, true );

            if ( ! $this->is_translatable_top_level_meta_value( $meta_key, $source_value ) ) {
                continue;
            }

            $string_data      = $this->build_top_level_meta_string_data( $meta_key, $title, $source_value );
            $translated_value = ! empty( $translations[ $string_data['name'] ] )
                ? $translations[ $string_data['name'] ]
                : $this->get_completed_package_translation_for_value( $package, $source_value, $language_code );

            if ( ! $this->is_usable_translation( $source_value, $translated_value, $language_code ) ) {
                continue;
            }

            $target_value = $this->get_raw_term_meta( $target_id, $meta_key, true );
            if ( ! $this->should_replace_translated_scalar_value( $source_value, $target_value, $language_code ) ) {
                continue;
            }

            update_term_meta( $target_id, $meta_key, $translated_value );
            $updated = true;
        }

        return $updated;
    }

    /**
     * Mirror completed package translations into WPML String Translation rows.
     *
     * @param int    $source_id      Source directory type term ID.
     * @param string $language_code  Target language.
     * @param array  $translations   Translated strings keyed by package string name.
     * @return void
     */
    private function sync_runtime_string_translations( $source_id, $language_code, array $translations ) {
        if ( empty( $language_code ) || ! function_exists( 'icl_add_string_translation' ) ) {
            return;
        }

        $package            = $this->get_package( $source_id );
        $updated_string_ids = [];

        foreach ( $this->get_runtime_ui_strings() as $string_name => $string_data ) {
            if ( empty( $string_data['value'] ) || ! is_string( $string_data['value'] ) || empty( $string_data['contexts'] ) || ! is_array( $string_data['contexts'] ) ) {
                continue;
            }

            $source_value     = $string_data['value'];
            $translated_value = ! empty( $translations[ $string_name ] )
                ? $translations[ $string_name ]
                : $this->get_completed_package_translation_for_value( $package, $source_value, $language_code );

            if ( ! $this->is_usable_translation( $source_value, $translated_value, $language_code ) ) {
                continue;
            }

            foreach ( $string_data['contexts'] as $context ) {
                $registration_name = ! empty( $string_data['context_names'][ $context ] ) && is_string( $string_data['context_names'][ $context ] )
                    ? $string_data['context_names'][ $context ]
                    : '';

                foreach ( $this->get_or_register_runtime_string_ids( $context, $source_value, $registration_name ) as $string_id ) {
                    if ( ! $this->should_update_wpml_string_translation( $string_id, $language_code, $source_value ) ) {
                        continue;
                    }

                    icl_add_string_translation( $string_id, $language_code, $translated_value, defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10 );
                    $updated_string_ids[] = (int) $string_id;
                }
            }
        }

        if ( ! empty( $updated_string_ids ) && function_exists( 'wpml_st_flush_string_cache_for_ids' ) ) {
            wpml_st_flush_string_cache_for_ids( array_values( array_unique( $updated_string_ids ) ) );
        }
    }

    /**
     * Resolve existing runtime String Translation rows, registering gettext-like
     * Directorist rows when needed.
     *
     * @param string $context WPML string context.
     * @param string $value   Source value.
     * @param string $name    Optional stable WPML string name for non-gettext rows.
     * @return array
     */
    private function get_or_register_runtime_string_ids( $context, $value, $name = '' ) {
        global $wpdb;

        if ( '' !== $name ) {
            $ids = wp_parse_id_list(
                $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}icl_strings WHERE context = %s AND name = %s",
                        $context,
                        $name
                    )
                )
            );
        } else {
            $ids = wp_parse_id_list(
                $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}icl_strings WHERE context = %s AND value = %s",
                        $context,
                        $value
                    )
                )
            );
        }

        if ( empty( $ids ) && in_array( $context, [ 'directorist', 'directorist-wpml-integration' ], true ) && function_exists( 'icl_register_string' ) ) {
            $string_id = icl_register_string( $context, $name, $value );

            if ( $string_id ) {
                $ids[] = (int) $string_id;
            }
        }

        return $ids;
    }

    /**
     * Check whether a WPML String Translation row should receive package value.
     *
     * @param int    $string_id     WPML string ID.
     * @param string $language_code Target language.
     * @param string $source_value  Source value.
     * @return bool
     */
    private function should_update_wpml_string_translation( $string_id, $language_code, $source_value ) {
        global $wpdb;

        $translation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT value, status FROM {$wpdb->prefix}icl_string_translations WHERE string_id = %d AND language = %s LIMIT 1",
                $string_id,
                $language_code
            )
        );

        if ( ! $translation ) {
            return true;
        }

        if ( empty( $translation->value ) || (int) $translation->status !== ( defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10 ) ) {
            return true;
        }

        return $this->strings_match_after_decoding( $source_value, $translation->value )
            || $this->is_language_prefixed_placeholder( $translation->value, $language_code );
    }

    /**
     * Read completed package translations grouped by language.
     *
     * @param array $package WPML package definition.
     * @return array
     */
    private function get_completed_package_translations_by_language( array $package ) {
        global $wpdb;

        $package_id = $this->get_package_id( $package );
        if ( $package_id <= 0 ) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.name, s.value AS source_value, st.language, st.value
                FROM {$wpdb->prefix}icl_strings s
                INNER JOIN {$wpdb->prefix}icl_string_translations st
                    ON st.string_id = s.id
                WHERE s.string_package_id = %d
                    AND st.status = %d
                    AND st.value <> ''",
                $package_id,
                defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10
            ),
            ARRAY_A
        );

        $translations = [];
        foreach ( $rows as $row ) {
            if ( empty( $row['name'] ) || empty( $row['language'] ) || ! $this->is_usable_translation( $row['source_value'], $row['value'], $row['language'] ) ) {
                continue;
            }

            $translations[ $row['language'] ][ $row['name'] ] = $row['value'];
        }

        return $translations;
    }

    /**
     * Resolve a completed package translation by source value.
     *
     * @param array  $package WPML package definition.
     * @param string $value         Source value.
     * @param string $language_code Target language.
     * @return string
     */
    private function get_completed_package_translation_for_value( array $package, $value, $language_code = '' ) {
        global $wpdb;

        if ( ! is_string( $value ) || '' === trim( $value ) ) {
            return '';
        }

        if ( empty( $language_code ) ) {
            $language_code = apply_filters( 'wpml_current_language', null );
        }

        if ( empty( $language_code ) ) {
            return '';
        }

        $package_id = $this->get_package_id( $package );
        if ( $package_id <= 0 ) {
            return '';
        }

        $cache_key = $package_id . ':' . $language_code . ':' . md5( $value );
        if ( array_key_exists( $cache_key, self::$package_value_translation_cache ) ) {
            return self::$package_value_translation_cache[ $cache_key ];
        }

        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT st.value
                FROM {$wpdb->prefix}icl_strings s
                INNER JOIN {$wpdb->prefix}icl_string_translations st
                    ON st.string_id = s.id
                WHERE s.string_package_id = %d
                    AND s.value = %s
                    AND st.language = %s
                    AND st.status = %d
                    AND st.value <> ''
                ORDER BY s.id ASC",
                $package_id,
                $value,
                $language_code,
                defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10
            )
        );

        foreach ( $rows as $translated_value ) {
            if ( $this->is_usable_translation( $value, $translated_value, $language_code ) ) {
                self::$package_value_translation_cache[ $cache_key ] = (string) $translated_value;

                return self::$package_value_translation_cache[ $cache_key ];
            }
        }

        self::$package_value_translation_cache[ $cache_key ] = '';

        return self::$package_value_translation_cache[ $cache_key ];
    }

    /**
     * Get the DB package ID for a WPML package definition.
     *
     * @param array $package WPML package definition.
     * @return int
     */
    private function get_package_id( array $package ) {
        global $wpdb;

        if ( empty( $package['kind_slug'] ) || empty( $package['name'] ) ) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->prefix}icl_string_packages WHERE kind_slug = %s AND name = %s LIMIT 1",
                $package['kind_slug'],
                $package['name']
            )
        );
    }

    /**
     * Build a stable inventory hash from the current package field names.
     *
     * Field values are intentionally not included here: WPML already marks
     * package translations stale when a registered string value changes. The
     * stale ATE bug is specifically caused by field additions/removals.
     *
     * @param array $string_names Current package string names.
     * @return string
     */
    private function get_package_inventory_hash( array $string_names ) {
        $string_names = array_values( array_filter( array_map( 'strval', $string_names ) ) );

        if ( empty( $string_names ) ) {
            return '';
        }

        sort( $string_names, SORT_STRING );

        return md5( wp_json_encode( $string_names ) );
    }

    /**
     * Get the option name used for one package inventory hash.
     *
     * @param string $package_name Package name.
     * @return string
     */
    private function get_package_inventory_hash_option_name( $package_name ) {
        return self::PACKAGE_INVENTORY_HASH_OPTION_PREFIX . md5( (string) $package_name );
    }

    /**
     * Check whether a latest package job is missing current fields.
     *
     * This catches already-stale jobs from older plugin versions even when the
     * local inventory hash option was created after the package strings existed.
     *
     * @param int   $package_id   WPML string package ID.
     * @param array $string_names Current package string names.
     * @return bool
     */
    private function latest_package_jobs_are_missing_fields( $package_id, array $string_names ) {
        global $wpdb;

        $package_id = (int) $package_id;
        if ( $package_id <= 0 || empty( $string_names ) ) {
            return false;
        }

        $element_type = 'package_' . self::PACKAGE_KIND_SLUG;
        $trid         = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = %s LIMIT 1",
                $package_id,
                $element_type
            )
        );

        if ( $trid <= 0 ) {
            return false;
        }

        $jobs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ts.rid, ts.status, ts.needs_update, MAX(tj.job_id) AS job_id
                FROM {$wpdb->prefix}icl_translations tr
                INNER JOIN {$wpdb->prefix}icl_translation_status ts
                    ON ts.translation_id = tr.translation_id
                LEFT JOIN {$wpdb->prefix}icl_translate_job tj
                    ON tj.rid = ts.rid
                WHERE tr.trid = %d
                    AND tr.source_language_code IS NOT NULL
                GROUP BY ts.rid, ts.status, ts.needs_update",
                $trid
            )
        );

        if ( empty( $jobs ) ) {
            return false;
        }

        foreach ( $jobs as $job ) {
            if ( (int) $job->needs_update || empty( $job->job_id ) ) {
                continue;
            }

            $job_fields = array_flip(
                $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT field_type FROM {$wpdb->prefix}icl_translate WHERE job_id = %d AND field_translate = 1",
                        (int) $job->job_id
                    )
                )
            );

            foreach ( $string_names as $string_name ) {
                if ( ! isset( $job_fields[ $string_name ] ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Mark existing target-language package translations as needing update.
     *
     * This changes only WPML job/status metadata, not translated content. The
     * next Translation Dashboard/ATE send can then create a fresh job with the
     * full current package payload.
     *
     * @param int $package_id WPML string package ID.
     * @return void
     */
    private function mark_package_translations_need_update( $package_id ) {
        global $wpdb;

        $package_id = (int) $package_id;
        if ( $package_id <= 0 ) {
            return;
        }

        $element_type = 'package_' . self::PACKAGE_KIND_SLUG;
        $trid         = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = %s LIMIT 1",
                $package_id,
                $element_type
            )
        );

        if ( $trid <= 0 ) {
            return;
        }

        $statuses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT tr.translation_id, tr.language_code, ts.rid, ts.needs_update
                FROM {$wpdb->prefix}icl_translations tr
                INNER JOIN {$wpdb->prefix}icl_translation_status ts
                    ON ts.translation_id = tr.translation_id
                WHERE tr.trid = %d
                    AND tr.source_language_code IS NOT NULL",
                $trid
            )
        );

        foreach ( $statuses as $status ) {
            $translation_id = ! empty( $status->translation_id ) ? absint( $status->translation_id ) : 0;
            if ( $translation_id <= 0 || (int) $status->needs_update ) {
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

            if ( ! empty( $status->rid ) ) {
                $wpdb->update(
                    $wpdb->prefix . 'icl_translate_job',
                    [ 'translated' => 0 ],
                    [ 'rid' => (int) $status->rid ],
                    [ '%d' ],
                    [ '%d' ]
                );
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
                    'post_id' => $package_id,
                    'type'    => 'needs_update',
                    'value'   => 1,
                ]
            );
        }
    }

    /**
     * Limit package inventory stale checks to bounded admin/WP-CLI flows.
     *
     * @return bool
     */
    private function should_mark_package_inventory_changes() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return true;
        }

        if ( wp_doing_cron() || ( ! is_admin() && ! wp_doing_ajax() ) ) {
            return false;
        }

        if ( wp_doing_ajax() ) {
            $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

            return false !== strpos( $action, 'wpml' ) || false !== strpos( $action, 'icl_' );
        }

        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        return in_array(
            $page,
            [
                'tm/menu/main.php',
                'tm/menu/translations-queue.php',
                'wpml-package-management',
                'wpml-string-translation/menu/string-translation.php',
            ],
            true
        );
    }

    /**
     * Check if a translated scalar may be replaced.
     *
     * @param string $source_value  Source value.
     * @param mixed  $target_value  Current target value.
     * @param string $language_code Target language code.
     * @return bool
     */
    private function should_replace_translated_scalar_value( $source_value, $target_value, $language_code = '' ) {
        if ( ! is_string( $target_value ) || '' === trim( $target_value ) ) {
            return true;
        }

        return $this->strings_match_after_decoding( $source_value, $target_value )
            || $this->is_language_prefixed_placeholder( $target_value, $language_code );
    }

    /**
     * Check whether a translation is usable.
     *
     * @param string $source_value     Source value.
     * @param mixed  $translated_value Translated value.
     * @param string $language_code    Target language code.
     * @return bool
     */
    private function is_usable_translation( $source_value, $translated_value, $language_code = '' ) {
        return is_string( $translated_value )
            && '' !== trim( $translated_value )
            && ! $this->strings_match_after_decoding( $source_value, $translated_value )
            && ! $this->is_language_prefixed_placeholder( $translated_value, $language_code );
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

        $charset         = get_bloginfo( 'charset' );
        $value           = trim( html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, $charset ) );
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
        $charset = get_bloginfo( 'charset' );

        $left  = trim( html_entity_decode( (string) $left, ENT_QUOTES | ENT_HTML5, $charset ) );
        $right = trim( html_entity_decode( (string) $right, ENT_QUOTES | ENT_HTML5, $charset ) );

        return $left === $right;
    }

    /**
     * Limit completed-package repair to WP-CLI and relevant admin screens.
     *
     * @return bool
     */
    private function should_sync_completed_package_translations() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return true;
        }

        if ( ! is_admin() ) {
            return false;
        }

        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        return in_array(
            $page,
            [
                'atbdp-directory-types',
                'tm/menu/main.php',
                'tm/menu/translations-queue.php',
                'wpml-package-management',
                'wpml-string-translation/menu/string-translation.php',
            ],
            true
        );
    }

    /**
     * Delete strings that are no longer emitted by either builder package source.
     *
     * Runs only during WPML's package refresh request, never on frontend or
     * normal builder rendering.
     *
     * @return void
     */
    public function cleanup_unused_directory_builder_package_strings() {
        global $wpdb;

        if ( ! $this->is_wpml_active() ) {
            return;
        }

        if ( ! $this->should_cleanup_unused_package_strings() ) {
            return;
        }

        if ( ! function_exists( 'icl_unregister_string' ) ) {
            return;
        }

        foreach ( self::$package_string_names as $package_key => $current_names ) {
            list( $kind_slug, $package_name ) = array_pad( explode( ':', $package_key, 2 ), 2, '' );

            if ( self::PACKAGE_KIND_SLUG !== $kind_slug || '' === $package_name || empty( $current_names ) ) {
                continue;
            }

            $package_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->prefix}icl_string_packages WHERE kind_slug = %s AND name = %s LIMIT 1",
                    $kind_slug,
                    $package_name
                )
            );

            if ( $package_id <= 0 ) {
                continue;
            }

            $context = $kind_slug . '-' . $package_name;

            if ( $this->repair_or_cleanup_orphaned_package_context_strings( $package_id, $context, $current_names, isset( self::$package_string_data[ $package_key ] ) ? self::$package_string_data[ $package_key ] : [] ) ) {
                unset( self::$package_string_cache[ $package_key ] );
            }

            $registered_names = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT name FROM {$wpdb->prefix}icl_strings WHERE string_package_id = %d",
                    $package_id
                )
            );

            if ( empty( $registered_names ) ) {
                continue;
            }

            foreach ( array_diff( $registered_names, array_keys( $current_names ) ) as $unused_name ) {
                icl_unregister_string( $context, $unused_name );
            }
        }
    }

    /**
     * Relink current strings or delete stale rows sharing the package context.
     *
     * WPML can retain old rows in the same context with no string_package_id.
     * Those rows inflate package-context counts but are not part of the current
     * package job payload. Reconcile them during the existing admin refresh.
     *
     * @param int    $package_id      WPML string package ID.
     * @param string $context         WPML package string context.
     * @param array  $current_names   Current package names keyed by string name.
     * @param array  $current_strings Current package string data keyed by name.
     * @return bool Whether any row was changed.
     */
    private function repair_or_cleanup_orphaned_package_context_strings( $package_id, $context, array $current_names, array $current_strings ) {
        global $wpdb;

        $package_id = (int) $package_id;

        if ( $package_id <= 0 || '' === $context ) {
            return false;
        }

        $orphaned_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name FROM {$wpdb->prefix}icl_strings WHERE context = %s AND (string_package_id IS NULL OR string_package_id <> %d)",
                $context,
                $package_id
            )
        );

        if ( empty( $orphaned_rows ) ) {
            return false;
        }

        $linked_names = array_fill_keys(
            (array) $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT name FROM {$wpdb->prefix}icl_strings WHERE string_package_id = %d",
                    $package_id
                )
            ),
            true
        );
        $delete_ids    = [];
        $changed       = false;

        foreach ( $orphaned_rows as $row ) {
            $string_id   = isset( $row->id ) ? absint( $row->id ) : 0;
            $string_name = isset( $row->name ) ? (string) $row->name : '';

            if ( $string_id <= 0 || '' === $string_name ) {
                continue;
            }

            if ( isset( $current_names[ $string_name ] ) && ! isset( $linked_names[ $string_name ] ) ) {
                $update = [ 'string_package_id' => $package_id ];
                $format = [ '%d' ];

                if ( isset( $current_strings[ $string_name ] ) && is_array( $current_strings[ $string_name ] ) ) {
                    if ( isset( $current_strings[ $string_name ]['value'] ) && is_string( $current_strings[ $string_name ]['value'] ) ) {
                        $update['value'] = $current_strings[ $string_name ]['value'];
                        $format[]        = '%s';
                    }

                    if ( isset( $current_strings[ $string_name ]['type'] ) && is_string( $current_strings[ $string_name ]['type'] ) ) {
                        $update['type'] = $current_strings[ $string_name ]['type'];
                        $format[]       = '%s';
                    }

                    if ( isset( $current_strings[ $string_name ]['title'] ) && is_string( $current_strings[ $string_name ]['title'] ) ) {
                        $update['title'] = $current_strings[ $string_name ]['title'];
                        $format[]        = '%s';
                    }
                }

                $wpdb->update(
                    "{$wpdb->prefix}icl_strings",
                    $update,
                    [ 'id' => $string_id ],
                    $format,
                    [ '%d' ]
                );

                $linked_names[ $string_name ] = true;
                $changed                      = true;
                continue;
            }

            $delete_ids[] = $string_id;
        }

        if ( ! empty( $delete_ids ) ) {
            $this->delete_wpml_strings_by_id( $delete_ids );
            $changed = true;
        }

        return $changed;
    }

    /**
     * Delete WPML strings and their translations by exact string IDs.
     *
     * @param array $string_ids String IDs.
     * @return void
     */
    private function delete_wpml_strings_by_id( array $string_ids ) {
        global $wpdb;

        $string_ids = array_values( array_unique( array_filter( array_map( 'absint', $string_ids ) ) ) );

        if ( empty( $string_ids ) ) {
            return;
        }

        if ( function_exists( 'wpml_unregister_string_multi' ) ) {
            wpml_unregister_string_multi( $string_ids );
            return;
        }

        foreach ( $string_ids as $string_id ) {
            $wpdb->delete( "{$wpdb->prefix}icl_string_translations", [ 'string_id' => $string_id ], [ '%d' ] );
            $wpdb->delete( "{$wpdb->prefix}icl_string_positions", [ 'string_id' => $string_id ], [ '%d' ] );
            $wpdb->delete( "{$wpdb->prefix}icl_strings", [ 'id' => $string_id ], [ '%d' ] );
        }
    }

    /**
     * Limit package-string cleanup to WPML package refresh screens.
     *
     * Directory Builder UI strings are intentionally registered only while
     * WPML refreshes package content. Running cleanup from a Directorist admin
     * screen would see only the saved builder-meta half of the package and
     * incorrectly remove the static UI half.
     *
     * @return bool
     */
    private function should_cleanup_unused_package_strings() {
        if ( wp_doing_ajax() ) {
            $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

            return false !== strpos( $action, 'wpml' ) || false !== strpos( $action, 'icl_' );
        }

        if ( ! is_admin() ) {
            return false;
        }

        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        if ( '' === $page ) {
            return false;
        }

        foreach ( [ 'tm/', 'wpml', 'sitepress', 'icl_', 'translation', 'string-translation', 'packages' ] as $wpml_page_marker ) {
            if ( false !== strpos( $page, $wpml_page_marker ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete packages that no longer belong to a default-language directory.
     *
     * A translated directory can briefly exist as an unlinked term while WPML
     * creates it. If package registration runs during that window, WPML keeps
     * the translated term as a separate source package after the term is linked.
     *
     * @return void
     */
    public function cleanup_obsolete_directory_builder_packages() {
        global $wpdb;

        if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
            return;
        }

        $page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

        if ( ! in_array( $page, [ 'tm/menu/main.php', 'wpml-package-management' ], true ) || ! $this->is_wpml_active() || ! has_action( 'wpml_delete_package' ) ) {
            return;
        }

        $active_package_names = [];

        foreach ( $this->get_source_directory_ids() as $directory_id ) {
            $package = $this->get_package( $directory_id );

            if ( ! empty( $package['name'] ) ) {
                $active_package_names[ $package['name'] ] = true;
            }
        }

        if ( empty( $active_package_names ) ) {
            return;
        }

        $packages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT name, kind FROM {$wpdb->prefix}icl_string_packages WHERE kind_slug = %s AND post_id IS NULL",
                self::PACKAGE_KIND_SLUG
            ),
            ARRAY_A
        );

        foreach ( $packages as $package ) {
            if ( empty( $package['name'] ) || isset( $active_package_names[ $package['name'] ] ) ) {
                continue;
            }

            do_action( 'wpml_delete_package', $package['name'], $package['kind'] );
        }
    }

    /**
     * Remove legacy Add Listing strings now included in the builder ATE package.
     *
     * Cleanup is limited to WPML translation screens so it never runs on the
     * frontend or during normal Directorist admin requests.
     *
     * @return void
     */
    public function cleanup_legacy_add_listing_strings() {
        global $wpdb;

        if ( ! is_admin() || ! isset( $_GET['page'] ) || ! function_exists( 'icl_unregister_string' ) ) {
            return;
        }

        $page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

        if ( ! in_array( $page, [ 'tm/menu/main.php', 'wpml-string-translation/menu/string-translation.php' ], true ) ) {
            return;
        }

        $legacy_names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}icl_strings WHERE context = %s AND name LIKE %s",
                'directorist-wpml-integration',
                $wpdb->esc_like( 'add_listing_dir_' ) . '%'
            )
        );

        foreach ( $legacy_names as $legacy_name ) {
            icl_unregister_string( 'directorist-wpml-integration', $legacy_name );
        }
    }

    /**
     * Translate Directorist builder term meta before Directorist renders it.
     *
     * @param mixed  $value     Existing metadata value.
     * @param int    $object_id Term ID.
     * @param string $meta_key  Meta key.
     * @param bool   $single    Whether a single value was requested.
     * @return mixed
     */
    public function translate_builder_term_meta( $value, $object_id, $meta_key, $single ) {
        if ( self::$resolving_term_meta || ! $single || ! in_array( $meta_key, $this->builder_meta_keys, true ) || ! $this->is_wpml_active() ) {
            return $value;
        }

        $object_id = (int) $object_id;
        if ( $object_id <= 0 || ! $this->is_directory_type_term( $object_id ) ) {
            return $value;
        }

        $source_directory_id = $this->get_source_directory_id( $object_id );
        if ( $source_directory_id <= 0 ) {
            return $value;
        }

        $source_meta_value = $this->get_raw_term_meta( $source_directory_id, $meta_key, true );

        if ( '' === $source_meta_value || [] === $source_meta_value ) {
            $source_meta_value = $this->normalize_filtered_meta_value( $value );
        }

        if ( ! is_array( $source_meta_value ) && ! $this->is_translatable_string( $meta_key, $source_meta_value, [ $meta_key ] ) ) {
            return $value;
        }

        if ( is_array( $source_meta_value ) ) {
            $translated_value = $this->translate_meta_value( $source_meta_value, $source_directory_id, $meta_key );

            return [ $translated_value ];
        }

        $string = $this->build_string_data( $meta_key, [ $meta_key ], $source_meta_value );

        return $this->translate_package_string( $source_meta_value, $string['name'], $this->get_package( $source_directory_id ) );
    }

    /**
     * Translate Add Listing wizard strings that are not stored in term meta.
     *
     * @param string $template_output Rendered Add Listing template output.
     * @param array  $args            Template arguments.
     * @return string
     */
    public function translate_add_listing_template_ui( $template_output, $args ) {
        if ( empty( $template_output ) || ! is_string( $template_output ) || ! $this->is_wpml_active() ) {
            return $template_output;
        }

        $directory_id = $this->get_directory_id_from_template_args( $args );
        if ( $directory_id <= 0 ) {
            return $template_output;
        }

        $source_directory_id = $this->get_source_directory_id( $directory_id );
        if ( $source_directory_id <= 0 ) {
            return $template_output;
        }

        $package = $this->get_package( $source_directory_id );

        foreach ( $this->template_ui_strings as $string_name => $string_data ) {
            $translated = $this->translate_package_string( $string_data['value'], $string_name, $package );

            if ( empty( $translated ) || $translated === $string_data['value'] ) {
                continue;
            }

            $template_output = $this->replace_template_string( $template_output, $string_data['value'], $translated );
        }

        return $template_output;
    }

    /**
     * Translate all translatable strings in a builder meta array.
     *
     * @param array  $data                Builder meta value.
     * @param int    $source_directory_id Source directory type term ID.
     * @param string $meta_key            Meta key.
     * @param array  $path                Current nested path.
     * @return array
     */
    private function translate_meta_value( $data, $source_directory_id, $meta_key, $path = [] ) {
        foreach ( $data as $key => $value ) {
            $current_path = array_merge( $path, [ (string) $key ] );

            if ( is_array( $value ) ) {
                $data[ $key ] = $this->translate_meta_value( $value, $source_directory_id, $meta_key, $current_path );
                continue;
            }

            if ( ! $this->is_translatable_string( $key, $value, $current_path ) ) {
                continue;
            }

            $string = $this->build_string_data( $meta_key, $current_path, $value );
            $data[ $key ] = $this->translate_package_string( $value, $string['name'], $this->get_package( $source_directory_id ) );
        }

        return $data;
    }

    /**
     * Apply translated package strings to a builder meta array by string name.
     *
     * @param array  $data         Builder meta value.
     * @param string $meta_key     Meta key.
     * @param array  $translations Translated strings keyed by WPML string name.
     * @param array  $existing     Existing translated builder value.
     * @param array  $path         Current nested path.
     * @param array  $package      WPML package definition.
     * @param string $language_code Target language code.
     * @return array
     */
    private function apply_named_translations_to_meta_value( $data, $meta_key, $translations, $existing = [], $path = [], $package = [], $language_code = '' ) {
        foreach ( $data as $key => $value ) {
            $current_path = array_merge( $path, [ (string) $key ] );
            $existing_value = is_array( $existing ) && array_key_exists( $key, $existing ) ? $existing[ $key ] : null;

            if ( is_array( $value ) ) {
                $data[ $key ] = $this->apply_named_translations_to_meta_value(
                    $value,
                    $meta_key,
                    $translations,
                    is_array( $existing_value ) ? $existing_value : [],
                    $current_path,
                    $package,
                    $language_code
                );
                continue;
            }

            if ( ! $this->is_translatable_string( $key, $value, $current_path ) ) {
                continue;
            }

            $string = $this->build_string_data( $meta_key, $current_path, $value );
            if ( ! empty( $translations[ $string['name'] ] ) ) {
                $candidate_value = $translations[ $string['name'] ];

                if ( $this->is_usable_translation( $value, $candidate_value, $language_code ) ) {
                    $data[ $key ] = $candidate_value;
                } elseif ( $this->should_replace_translated_scalar_value( $value, $existing_value, $language_code ) ) {
                    $data[ $key ] = $value;
                } elseif ( $this->has_existing_translation_value( $existing_value, $language_code ) ) {
                    $data[ $key ] = $existing_value;
                }
                continue;
            }

            $translated_by_value = ! empty( $package ) && ! empty( $language_code )
                ? $this->get_completed_package_translation_for_value( $package, $value, $language_code )
                : '';

            if ( $this->is_usable_translation( $value, $translated_by_value, $language_code ) && $this->should_replace_translated_scalar_value( $value, $existing_value, $language_code ) ) {
                $data[ $key ] = $translated_by_value;
            } elseif ( $this->has_existing_translation_value( $existing_value, $language_code ) ) {
                $data[ $key ] = $existing_value;
            }
        }

        return $data;
    }

    /**
     * Check whether a translated scalar is safe to preserve as an ATE fallback.
     *
     * @param mixed $value Existing translated value.
     * @return bool
     */
    private function has_existing_translation_value( $value, $language_code = '' ) {
        return is_string( $value )
            && '' !== trim( $value )
            && ! $this->is_language_prefixed_placeholder( $value, $language_code );
    }

    /**
     * Translate a package string while preserving the source value as fallback.
     *
     * @param string $value       Source value.
     * @param string $string_name WPML string name.
     * @param array  $package     WPML package definition.
     * @return string
     */
    private function translate_package_string( $value, $string_name, $package ) {
        if ( ! is_string( $value ) || '' === trim( $value ) ) {
            return $value;
        }

        $error_level = error_reporting();
        error_reporting( $error_level & ~E_WARNING & ~E_DEPRECATED );

        try {
            $translated = apply_filters( 'wpml_translate_string', $value, $string_name, $package );
        } finally {
            error_reporting( $error_level );
        }

        $language_code = apply_filters( 'wpml_current_language', null );

        if ( ! is_string( $translated ) || '' === trim( $translated ) || $this->is_language_prefixed_placeholder( $translated, $language_code ) ) {
            return $value;
        }

        return $translated;
    }

    /**
     * Replace a translated template string in text nodes and exact attributes.
     *
     * @param string $template_output Rendered template output.
     * @param string $source          Source string.
     * @param string $translated      Translated string.
     * @return string
     */
    private function replace_template_string( $template_output, $source, $translated ) {
        if ( ! is_string( $template_output ) || ! is_string( $source ) || ! is_string( $translated ) || $source === $translated ) {
            return $template_output;
        }

        $text_variants      = array_unique( [ $source, esc_html( $source ) ] );
        $attribute_variants = array_unique( [ $source, esc_attr( $source ) ] );

        foreach ( $text_variants as $variant ) {
            if ( '' === $variant ) {
                continue;
            }

            $template_output = preg_replace_callback(
                '/>(\s*)' . preg_quote( $variant, '/' ) . '(\s*)</u',
                function ( $matches ) use ( $translated ) {
                    return '>' . $matches[1] . esc_html( $translated ) . $matches[2] . '<';
                },
                $template_output
            );
        }

        foreach ( $attribute_variants as $variant ) {
            if ( '' === $variant ) {
                continue;
            }

            $template_output = preg_replace_callback(
                '/(["\'])' . preg_quote( $variant, '/' ) . '\1/u',
                function ( $matches ) use ( $translated ) {
                    return $matches[1] . esc_attr( $translated ) . $matches[1];
                },
                $template_output
            );
        }

        return $template_output;
    }

    /**
     * Register a single builder string in a WPML package.
     *
     * @param array $package WPML package definition.
     * @param array $string  String data.
     * @return void
     */
    private function register_package_string( $package, $string ) {
        if ( empty( $string['value'] ) || ! is_string( $string['value'] ) ) {
            return;
        }

        self::track_package_string( $package, $string['name'], $string );

        if ( $this->is_registered_package_string_unchanged( $package, $string ) ) {
            return;
        }

        $error_level = error_reporting();
        error_reporting( $error_level & ~E_WARNING & ~E_DEPRECATED );

        try {
            do_action(
                'wpml_register_string',
                $string['value'],
                $string['name'],
                $package,
                $string['title'],
                $string['type']
            );
        } finally {
            error_reporting( $error_level );
        }

        $package_key = $package['kind_slug'] . ':' . $package['name'];
        self::$package_string_cache[ $package_key ][ $string['name'] ] = [
            'value' => $string['value'],
            'type'  => $string['type'],
        ];
    }

    /**
     * Check an existing package string without invoking WPML's write path.
     *
     * WPML marks the whole external package as needing an update when an
     * unchanged duplicate-value field is re-registered. Loading the package in
     * one query also avoids hundreds of unnecessary writes on dashboard loads.
     *
     * @param array $package WPML package definition.
     * @param array $string  String data.
     * @return bool
     */
    private function is_registered_package_string_unchanged( array $package, array $string ) {
        global $wpdb;

        if ( empty( $package['kind_slug'] ) || empty( $package['name'] ) || empty( $string['name'] ) ) {
            return false;
        }

        $package_key = $package['kind_slug'] . ':' . $package['name'];

        if ( ! array_key_exists( $package_key, self::$package_string_cache ) ) {
            $package_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->prefix}icl_string_packages WHERE kind_slug = %s AND name = %s LIMIT 1",
                    $package['kind_slug'],
                    $package['name']
                )
            );

            self::$package_string_cache[ $package_key ] = [];

            if ( $package_id > 0 ) {
                $existing_strings = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT name, value, type FROM {$wpdb->prefix}icl_strings WHERE string_package_id = %d",
                        $package_id
                    ),
                    ARRAY_A
                );

                foreach ( $existing_strings as $existing_string ) {
                    self::$package_string_cache[ $package_key ][ $existing_string['name'] ] = [
                        'value' => $existing_string['value'],
                        'type'  => $existing_string['type'],
                    ];
                }
            }
        }

        if ( empty( self::$package_string_cache[ $package_key ][ $string['name'] ] ) ) {
            return false;
        }

        $existing_string = self::$package_string_cache[ $package_key ][ $string['name'] ];

        return $existing_string['value'] === $string['value']
            && $existing_string['type'] === $string['type'];
    }

    /**
     * Build compatibility package strings for old Add Listing / directory names.
     *
     * @param int       $source_directory_id Source directory type term ID.
     * @param \WP_Term  $source_term         Source directory type term.
     * @return array
     */
    private function get_add_listing_compatibility_strings( $source_directory_id, $source_term ) {
        $strings    = [];
        $context_id = $this->get_directory_context_id( $source_directory_id );

        if ( $source_term && ! is_wp_error( $source_term ) && '' !== trim( $source_term->name ) ) {
            $this->add_compatibility_string(
                $strings,
                sprintf( 'directory_type_%d_name', (int) $source_directory_id ),
                'Directory Type Name',
                $source_term->name
            );
        }

        if ( $context_id <= 0 ) {
            return array_values( $strings );
        }

        $submission_form_fields = $this->get_raw_term_meta( $source_directory_id, 'submission_form_fields', true );

        if ( ! is_array( $submission_form_fields ) ) {
            return array_values( $strings );
        }

        if ( ! empty( $submission_form_fields['fields'] ) && is_array( $submission_form_fields['fields'] ) ) {
            foreach ( $submission_form_fields['fields'] as $field_key => $field_data ) {
                if ( ! is_array( $field_data ) ) {
                    continue;
                }

                $field_slug = $this->get_add_listing_field_slug( $field_key, $field_data );

                foreach ( [ 'label', 'placeholder', 'description' ] as $property_key ) {
                    if ( empty( $field_data[ $property_key ] ) || ! is_string( $field_data[ $property_key ] ) ) {
                        continue;
                    }

                    $this->add_compatibility_string(
                        $strings,
                        sprintf( 'add_listing_dir_%d_field_%s_%s', $context_id, $field_slug, $property_key ),
                        sprintf( 'Add Listing Field: %s %s', $this->humanize_path_part( $field_key ), $property_key ),
                        $field_data[ $property_key ]
                    );
                }

                foreach ( $this->get_add_listing_custom_property_keys( $field_data ) as $property_key ) {
                    $this->add_compatibility_string(
                        $strings,
                        sprintf( 'add_listing_dir_%d_field_%s_%s', $context_id, $field_slug, $this->safe_slug( $property_key ) ),
                        sprintf( 'Add Listing Field: %s %s', $this->humanize_path_part( $field_key ), $this->humanize_path_part( $property_key ) ),
                        $field_data[ $property_key ]
                    );
                }

                if ( empty( $field_data['options'] ) || ! is_array( $field_data['options'] ) ) {
                    continue;
                }

                foreach ( $field_data['options'] as $option_key => $option ) {
                    if ( is_array( $option ) && ! empty( $option['option_label'] ) && is_string( $option['option_label'] ) ) {
                        $option_value = ! empty( $option['option_value'] ) ? $option['option_value'] : $option_key;

                        $this->add_compatibility_string(
                            $strings,
                            sprintf( 'add_listing_dir_%d_field_%s_option_%s', $context_id, $field_slug, $this->safe_slug( $option_value ) ),
                            sprintf( 'Add Listing Field: %s option', $this->humanize_path_part( $field_key ) ),
                            $option['option_label']
                        );
                    } elseif ( is_string( $option ) && '' !== trim( $option ) ) {
                        $this->add_compatibility_string(
                            $strings,
                            sprintf( 'add_listing_dir_%d_field_%s_option_%s', $context_id, $field_slug, $this->safe_slug( $option ) ),
                            sprintf( 'Add Listing Field: %s option', $this->humanize_path_part( $field_key ) ),
                            $option
                        );
                    }
                }
            }
        }

        if ( ! empty( $submission_form_fields['groups'] ) && is_array( $submission_form_fields['groups'] ) ) {
            foreach ( $submission_form_fields['groups'] as $group ) {
                if ( empty( $group['label'] ) || ! is_string( $group['label'] ) ) {
                    continue;
                }

                $section_slug = ! empty( $group['key'] ) ? $this->safe_slug( $group['key'] ) : $this->safe_slug( $group['label'] );

                $this->add_compatibility_string(
                    $strings,
                    sprintf( 'add_listing_dir_%d_section_%s_label', $context_id, $section_slug ),
                    'Add Listing Section: ' . $group['label'],
                    $group['label']
                );
            }
        }

        return array_values( $strings );
    }

    /**
     * Apply completed ATE translations for Add Listing compatibility names.
     *
     * @param array $source_data         Source submission form data.
     * @param array $translated_data     Builder-name translated submission form data.
     * @param int   $source_directory_id Source directory type term ID.
     * @param array $translations        ATE translations keyed by package field name.
     * @return array
     */
    private function apply_add_listing_compatibility_translations( array $source_data, array $translated_data, $source_directory_id, array $translations, $language_code = '' ) {
        $context_id = $this->get_directory_context_id( $source_directory_id );

        if ( $context_id <= 0 ) {
            return $translated_data;
        }

        if ( ! empty( $source_data['fields'] ) && is_array( $source_data['fields'] ) ) {
            foreach ( $source_data['fields'] as $field_key => $field_data ) {
                if ( ! is_array( $field_data ) || empty( $translated_data['fields'][ $field_key ] ) || ! is_array( $translated_data['fields'][ $field_key ] ) ) {
                    continue;
                }

                $field_slug = $this->get_add_listing_field_slug( $field_key, $field_data );

                foreach ( [ 'label', 'placeholder', 'description' ] as $property_key ) {
                    $this->apply_add_listing_compatibility_scalar(
                        $field_data,
                        $translated_data['fields'][ $field_key ],
                        $property_key,
                        sprintf( 'add_listing_dir_%d_field_%s_%s', $context_id, $field_slug, $property_key ),
                        [ 'fields', (string) $field_key, $property_key ],
                        $translations,
                        $language_code
                    );
                }

                foreach ( $this->get_add_listing_custom_property_keys( $field_data ) as $property_key ) {
                    $this->apply_add_listing_compatibility_scalar(
                        $field_data,
                        $translated_data['fields'][ $field_key ],
                        $property_key,
                        sprintf( 'add_listing_dir_%d_field_%s_%s', $context_id, $field_slug, $this->safe_slug( $property_key ) ),
                        [ 'fields', (string) $field_key, $property_key ],
                        $translations,
                        $language_code
                    );
                }

                if ( empty( $field_data['options'] ) || ! is_array( $field_data['options'] ) || empty( $translated_data['fields'][ $field_key ]['options'] ) || ! is_array( $translated_data['fields'][ $field_key ]['options'] ) ) {
                    continue;
                }

                foreach ( $field_data['options'] as $option_key => $option ) {
                    if ( is_array( $option ) && ! empty( $option['option_label'] ) && is_string( $option['option_label'] ) && isset( $translated_data['fields'][ $field_key ]['options'][ $option_key ] ) && is_array( $translated_data['fields'][ $field_key ]['options'][ $option_key ] ) ) {
                        $option_value = ! empty( $option['option_value'] ) ? $option['option_value'] : $option_key;

                        $this->apply_add_listing_compatibility_scalar(
                            $option,
                            $translated_data['fields'][ $field_key ]['options'][ $option_key ],
                            'option_label',
                            sprintf( 'add_listing_dir_%d_field_%s_option_%s', $context_id, $field_slug, $this->safe_slug( $option_value ) ),
                            [ 'fields', (string) $field_key, 'options', (string) $option_key, 'option_label' ],
                            $translations,
                            $language_code
                        );
                    } elseif ( is_string( $option ) && isset( $translated_data['fields'][ $field_key ]['options'][ $option_key ] ) ) {
                        $builder_string      = $this->build_string_data( 'submission_form_fields', [ 'fields', (string) $field_key, 'options', (string) $option_key ], $option );
                        $builder_translation = ! empty( $translations[ $builder_string['name'] ] ) ? $translations[ $builder_string['name'] ] : '';

                        $compatibility_name  = sprintf( 'add_listing_dir_%d_field_%s_option_%s', $context_id, $field_slug, $this->safe_slug( $option ) );
                        $compatibility_value = ! empty( $translations[ $compatibility_name ] ) ? $translations[ $compatibility_name ] : '';

                        if ( ! $this->is_usable_translation( $option, $builder_translation, $language_code ) && $this->is_usable_translation( $option, $compatibility_value, $language_code ) ) {
                            $translated_data['fields'][ $field_key ]['options'][ $option_key ] = $compatibility_value;
                        }
                    }
                }
            }
        }

        if ( ! empty( $source_data['groups'] ) && is_array( $source_data['groups'] ) ) {
            foreach ( $source_data['groups'] as $group_index => $group ) {
                if ( ! is_array( $group ) || empty( $translated_data['groups'][ $group_index ] ) || ! is_array( $translated_data['groups'][ $group_index ] ) ) {
                    continue;
                }

                $section_slug = ! empty( $group['key'] ) ? $this->safe_slug( $group['key'] ) : ( ! empty( $group['label'] ) ? $this->safe_slug( $group['label'] ) : '' );

                if ( '' === $section_slug ) {
                    continue;
                }

                $this->apply_add_listing_compatibility_scalar(
                    $group,
                    $translated_data['groups'][ $group_index ],
                    'label',
                    sprintf( 'add_listing_dir_%d_section_%s_label', $context_id, $section_slug ),
                    [ 'groups', (string) $group_index, 'label' ],
                    $translations,
                    $language_code
                );
            }
        }

        return $translated_data;
    }

    /**
     * Add one compatibility string to a keyed list.
     *
     * @param array  $strings String list keyed by name.
     * @param string $name    String name.
     * @param string $title   String title.
     * @param string $value   String value.
     * @return void
     */
    private function add_compatibility_string( array &$strings, $name, $title, $value ) {
        if ( ! is_string( $value ) || '' === trim( $value ) || isset( $strings[ $name ] ) ) {
            return;
        }

        $strings[ $name ] = [
            'name'  => $name,
            'title' => $title,
            'type'  => strlen( wp_strip_all_tags( $value ) ) > 120 ? 'AREA' : 'LINE',
            'value' => $value,
        ];
    }

    /**
     * Apply one compatibility translation from a completed ATE job.
     *
     * @param array  $source_parent Source parent array.
     * @param array  $target_parent Target parent array.
     * @param string $property_key  Property key.
     * @param string $string_name   Compatibility string name.
     * @param array  $builder_path  Builder meta path.
     * @param array  $translations  ATE translations keyed by package field name.
     * @return void
     */
    private function apply_add_listing_compatibility_scalar( array $source_parent, array &$target_parent, $property_key, $string_name, array $builder_path, array $translations, $language_code = '' ) {
        if ( empty( $source_parent[ $property_key ] ) || ! is_string( $source_parent[ $property_key ] ) ) {
            return;
        }

        $builder_string = $this->build_string_data( 'submission_form_fields', $builder_path, $source_parent[ $property_key ] );

        $builder_translation = ! empty( $translations[ $builder_string['name'] ] ) ? $translations[ $builder_string['name'] ] : '';

        if ( $this->is_usable_translation( $source_parent[ $property_key ], $builder_translation, $language_code ) || empty( $translations[ $string_name ] ) ) {
            return;
        }

        if ( ! $this->is_usable_translation( $source_parent[ $property_key ], $translations[ $string_name ], $language_code ) ) {
            return;
        }

        $target_parent[ $property_key ] = $translations[ $string_name ];
    }

    /**
     * Get the Add Listing string slug for a builder field.
     *
     * @param string $field_key  Builder field key.
     * @param array  $field_data Builder field data.
     * @return string
     */
    private function get_add_listing_field_slug( $field_key, array $field_data ) {
        $runtime_field_key = ! empty( $field_data['field_key'] ) && is_string( $field_data['field_key'] ) ? $field_data['field_key'] : $field_key;

        return $this->safe_slug( $runtime_field_key );
    }

    /**
     * Get custom field properties used by the Directorist Add Listing form.
     *
     * @param array $field_data Builder field data.
     * @return array
     */
    private function get_add_listing_custom_property_keys( array $field_data ) {
        $keys       = [];
        $skip_keys  = [ 'label', 'placeholder', 'description', 'options', 'field_key', 'widget_name', 'widget_group', 'form', 'value' ];
        $known_keys = [
            'price_unit_field_label',
            'price_range_label',
            'price_range_placeholder',
            'price_unit_field_placeholder',
        ];

        foreach ( $field_data as $property_key => $property_value ) {
            if ( in_array( $property_key, $skip_keys, true ) || ! is_string( $property_value ) || '' === trim( $property_value ) ) {
                continue;
            }

            if ( in_array( $property_key, $known_keys, true ) ) {
                $keys[] = $property_key;
                continue;
            }

            foreach ( [ '_label', '_placeholder', '_text', '_title', '_description' ] as $pattern ) {
                if ( false !== strpos( $property_key, $pattern ) ) {
                    $keys[] = $property_key;
                    break;
                }
            }
        }

        return $keys;
    }

    /**
     * Get a stable Add Listing compatibility context ID for a directory type.
     *
     * @param int $source_directory_id Source directory type term ID.
     * @return int
     */
    private function get_directory_context_id( $source_directory_id ) {
        $trid = WPML_Helper::get_element_trid( (int) $source_directory_id, $this->get_directory_taxonomy() );

        return $trid > 0 ? $trid : (int) $source_directory_id;
    }

    /**
     * Resolve the current directory ID from Add Listing template args.
     *
     * @param array $args Template args.
     * @return int
     */
    private function get_directory_id_from_template_args( $args ) {
        if ( ! empty( $args['single_directory'] ) ) {
            return (int) $args['single_directory'];
        }

        if ( ! empty( $args['listing_form'] ) && is_object( $args['listing_form'] ) && method_exists( $args['listing_form'], 'get_current_listing_type' ) ) {
            return (int) $args['listing_form']->get_current_listing_type();
        }

        if ( ! empty( $_REQUEST['directory_type'] ) ) {
            $directory_type = sanitize_text_field( wp_unslash( $_REQUEST['directory_type'] ) );

            if ( is_numeric( $directory_type ) ) {
                return (int) $directory_type;
            }

            $term = get_term_by( 'slug', $directory_type, $this->get_directory_taxonomy() );
            if ( $term && ! is_wp_error( $term ) ) {
                return (int) $term->term_id;
            }
        }

        return function_exists( 'directorist_get_default_directory' ) ? (int) directorist_get_default_directory() : 0;
    }

    /**
     * Extract translatable strings from a builder meta array.
     *
     * @param array  $data     Builder meta value.
     * @param string $meta_key Meta key.
     * @param array  $path     Current nested path.
     * @return array
     */
    private function extract_strings( $data, $meta_key, $path = [] ) {
        $strings = [];

        foreach ( $data as $key => $value ) {
            $current_path = array_merge( $path, [ (string) $key ] );

            if ( is_array( $value ) ) {
                $strings = array_merge( $strings, $this->extract_strings( $value, $meta_key, $current_path ) );
                continue;
            }

            if ( ! $this->is_translatable_string( $key, $value, $current_path ) ) {
                continue;
            }

            $strings[] = $this->build_string_data( $meta_key, $current_path, $value );
        }

        return $strings;
    }

    /**
     * Build WPML string metadata.
     *
     * @param string $meta_key Meta key.
     * @param array  $path     Nested array path.
     * @param string $value    Original string value.
     * @return array
     */
    private function build_string_data( $meta_key, $path, $value ) {
        $path_key = implode( '__', array_map( [ $this, 'safe_slug' ], $path ) );
        $name     = 'builder_' . $this->safe_slug( $meta_key ) . '__' . $path_key;

        if ( strlen( $name ) > 150 ) {
            $name = 'builder_' . $this->safe_slug( $meta_key ) . '__' . md5( implode( '/', $path ) );
        }

        $title = $this->humanize_meta_key( $meta_key ) . ': ' . implode( ' > ', array_map( [ $this, 'humanize_path_part' ], $path ) );
        $type  = strlen( wp_strip_all_tags( $value ) ) > 120 ? 'AREA' : 'LINE';

        return [
            'name'  => $name,
            'title' => $title,
            'type'  => $type,
            'value' => $value,
        ];
    }

    /**
     * Check whether a scalar value should be exposed for translation.
     *
     * @param string|int $key   Array key.
     * @param mixed      $value Value.
     * @return bool
     */
    private function is_translatable_string( $key, $value, $path = [] ) {
        if ( ! is_string( $value ) || '' === trim( $value ) ) {
            return false;
        }

        $trimmed_value = trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );

        if ( '' === $trimmed_value || is_numeric( $trimmed_value ) || in_array( strtolower( $trimmed_value ), [ 'true', 'false', 'yes', 'no', 'on', 'off' ], true ) ) {
            return false;
        }

        if ( filter_var( $trimmed_value, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        $normalize_key = static function ( $item ) {
            return str_replace( '-', '_', sanitize_key( (string) $item ) );
        };
        $key  = $normalize_key( $key );
        $path = array_map( $normalize_key, (array) $path );

        if ( array_intersect( [ 'conditional_logic', 'conditions', 'show_if' ], $path ) ) {
            return false;
        }

        if ( 'value' === $key && $this->is_visual_builder_option_value_path( $path ) ) {
            return true;
        }

        $blocked_keys = [
            'active_template',
            'align',
            'can_move',
            'compare',
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
            'footer_thumbail',
            'footer_thumbnail',
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
            'where',
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
            'content',
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
     * Check whether a generic "value" key belongs to a visual builder option.
     *
     * Most value keys are behavior-driving data and must not be translated.
     * Directorist builder option controls, however, store visible labels as:
     * options > fields > label/title/etc > value.
     *
     * @param array $path Sanitized nested path.
     * @return bool
     */
    private function is_visual_builder_option_value_path( array $path ) {
        if ( ! in_array( 'options', $path, true ) ) {
            return false;
        }

        $fields_index = array_search( 'fields', $path, true );
        if ( false === $fields_index ) {
            return false;
        }

        $field_name = isset( $path[ $fields_index + 1 ] ) ? $path[ $fields_index + 1 ] : '';

        return in_array(
            $field_name,
            [
                'button_label',
                'cancel_button_label',
                'confirmation_text',
                'confirm_button_label',
                'description',
                'heading',
                'label',
                'model_header_text',
                'placeholder',
                'search_button_label',
                'search_button_text',
                'section_title',
                'select_files_label',
                'show_readmore_text',
                'submit_button_label',
                'text',
                'title',
            ],
            true
        );
    }

    /**
     * Get source directory IDs in the default language.
     *
     * @return array
     */
    private function get_source_directory_ids() {
        global $wpdb;

        $taxonomy         = $this->get_directory_taxonomy();
        $element_type     = WPML_Helper::get_wpml_element_type( $taxonomy );
        $default_language = apply_filters( 'wpml_default_language', null );

        if ( empty( $taxonomy ) || empty( $element_type ) || empty( $default_language ) ) {
            return [];
        }

        return wp_parse_id_list(
            $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT tt.term_id
                    FROM {$wpdb->term_taxonomy} tt
                    LEFT JOIN {$wpdb->prefix}icl_translations tr
                        ON tr.element_id = tt.term_taxonomy_id
                        AND tr.element_type = %s
                    WHERE tt.taxonomy = %s
                        AND ( tr.language_code = %s OR tr.language_code IS NULL )",
                    $element_type,
                    $taxonomy,
                    $default_language
                )
            )
        );
    }

    /**
     * Get the default-language source directory type for a term.
     *
     * @param int $directory_id Directory type term ID.
     * @return int
     */
    private function get_source_directory_id( $directory_id ) {
        $directory_id = (int) $directory_id;

        if ( $directory_id <= 0 ) {
            return 0;
        }

        $default_language = apply_filters( 'wpml_default_language', null );
        $translations     = WPML_Helper::get_element_translations( $directory_id, $this->get_directory_taxonomy() );

        if ( ! empty( $default_language ) && ! empty( $translations[ $default_language ]->term_id ) ) {
            return (int) $translations[ $default_language ]->term_id;
        }

        return $directory_id;
    }

    /**
     * Resolve a source directory type from a WPML string package context.
     *
     * @param string $package_context WPML string context.
     * @return int
     */
    private function get_source_directory_id_from_package_context( $package_context ) {
        if ( ! is_string( $package_context ) || 0 !== strpos( $package_context, self::PACKAGE_KIND_SLUG . '-' ) ) {
            return 0;
        }

        $package_name = substr( $package_context, strlen( self::PACKAGE_KIND_SLUG ) + 1 );

        foreach ( $this->get_source_directory_ids() as $directory_id ) {
            $package = $this->get_package( $directory_id );

            if ( ! empty( $package['name'] ) && $package_name === $package['name'] ) {
                return (int) $directory_id;
            }
        }

        return 0;
    }

    /**
     * Get the translated directory type ID for a language.
     *
     * @param int    $source_directory_id Source directory type term ID.
     * @param string $language_code       WPML language code.
     * @return int
     */
    private function get_translated_directory_id( $source_directory_id, $language_code ) {
        $translations = WPML_Helper::get_element_translations( $source_directory_id, $this->get_directory_taxonomy() );

        if ( empty( $translations[ $language_code ]->term_id ) ) {
            return 0;
        }

        return (int) $translations[ $language_code ]->term_id;
    }

    /**
     * Resolve the translated directory name from current and compatibility fields.
     *
     * @param \WP_Term $source_term         Source directory term.
     * @param int      $source_directory_id Source directory type term ID.
     * @param array    $translations        ATE translations keyed by package field name.
     * @return string
     */
    private function get_translated_directory_name( $source_term, $source_directory_id, array $translations, $language_code = '' ) {
        $name_fields = [
            self::DIRECTORY_NAME_FIELD,
            sprintf( 'directory_type_%d_name', (int) $source_directory_id ),
        ];

        foreach ( $name_fields as $name_field ) {
            if ( empty( $translations[ $name_field ] ) || ! is_string( $translations[ $name_field ] ) ) {
                continue;
            }

            $translated_name = trim( wp_strip_all_tags( $translations[ $name_field ] ) );

            if ( $this->is_usable_translation( $source_term->name, $translated_name, $language_code ) ) {
                return $translated_name;
            }
        }

        return $source_term->name;
    }

    /**
     * Create and link a translated directory type when the ATE package is saved first.
     *
     * @param int    $source_directory_id Source directory type term ID.
     * @param string $language_code       Target language code.
     * @param array  $translations        ATE translations keyed by package field name.
     * @return int
     */
    private function create_translated_directory_type( $source_directory_id, $language_code, array $translations ) {
        $source_term = get_term( (int) $source_directory_id, $this->get_directory_taxonomy() );

        if ( ! $source_term || is_wp_error( $source_term ) || empty( $language_code ) ) {
            return 0;
        }

        $trid = WPML_Helper::get_element_trid( $source_directory_id, $this->get_directory_taxonomy() );
        if ( $trid <= 0 ) {
            return 0;
        }

        $source_language = apply_filters( 'wpml_default_language', null );
        $source_info     = WPML_Helper::get_element_language_info( $source_directory_id, $this->get_directory_taxonomy() );

        if ( ! empty( $source_info->language_code ) ) {
            $source_language = $source_info->language_code;
        }

        if ( empty( $source_language ) ) {
            return 0;
        }

        $translated_name = $this->get_translated_directory_name( $source_term, $source_directory_id, $translations, $language_code );

        $target_id = $this->get_reusable_translated_directory_term_id( $translated_name, $language_code, $trid );

        if ( $target_id <= 0 ) {
            self::$creating_translated_directory_type = true;

            try {
                $inserted = $this->insert_translated_directory_term( $translated_name, $language_code );

                if ( is_wp_error( $inserted ) && 'term_exists' === $inserted->get_error_code() ) {
                    $inserted = $this->insert_translated_directory_term( $translated_name . ' - ' . strtoupper( $language_code ), $language_code );
                }
            } finally {
                self::$creating_translated_directory_type = false;
            }

            if ( is_wp_error( $inserted ) || empty( $inserted['term_id'] ) ) {
                return 0;
            }

            $target_id = (int) $inserted['term_id'];
        }

        $this->copy_directory_type_meta( $source_directory_id, $target_id );

        WPML_Helper::assign_language(
            $target_id,
            $this->get_directory_taxonomy(),
            $language_code,
            $trid,
            $source_language
        );

        clean_term_cache( [ $source_directory_id, $target_id ], $this->get_directory_taxonomy() );

        return $target_id;
    }

    /**
     * Insert a translated directory term using a language-scoped fallback slug.
     *
     * @param string $term_name     Term name.
     * @param string $language_code Target language code.
     * @return array|\WP_Error
     */
    private function insert_translated_directory_term( $term_name, $language_code ) {
        return wp_insert_term(
            $term_name,
            $this->get_directory_taxonomy(),
            [
                'slug' => $this->build_translated_directory_slug( $term_name, $language_code ),
            ]
        );
    }

    /**
     * Reuse an existing unlinked translated term instead of creating another orphan.
     *
     * @param string $term_name     Term name.
     * @param string $language_code Target language code.
     * @param int    $trid          Source translation group ID.
     * @return int
     */
    private function get_reusable_translated_directory_term_id( $term_name, $language_code, $trid ) {
        $term_id = $this->get_directory_term_id_by_slug( $this->build_translated_directory_slug( $term_name, $language_code ) );

        if ( $term_id <= 0 ) {
            return 0;
        }

        $language_info = WPML_Helper::get_element_language_info( $term_id, $this->get_directory_taxonomy() );

        if ( empty( $language_info ) ) {
            return $term_id;
        }

        if ( (int) $language_info->trid === (int) $trid && ! empty( $language_info->language_code ) && $language_code === $language_info->language_code ) {
            return $term_id;
        }

        return 0;
    }

    /**
     * Build the language-scoped slug used for translated directory terms.
     *
     * @param string $term_name     Term name.
     * @param string $language_code Target language code.
     * @return string
     */
    private function build_translated_directory_slug( $term_name, $language_code ) {
        $slug = sanitize_title( $term_name );

        if ( '' === $slug ) {
            $slug = 'directory';
        }

        return $slug . '-' . sanitize_key( $language_code );
    }

    /**
     * Resolve a directory term by slug without language-filtered term APIs.
     *
     * @param string $slug Term slug.
     * @return int
     */
    private function get_directory_term_id_by_slug( $slug ) {
        global $wpdb;

        if ( '' === $slug || empty( $wpdb->terms ) || empty( $wpdb->term_taxonomy ) ) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE t.slug = %s AND tt.taxonomy = %s LIMIT 1",
                $slug,
                $this->get_directory_taxonomy()
            )
        );
    }

    /**
     * Copy missing source directory type meta before applying ATE translations.
     *
     * @param int $source_directory_id Source term ID.
     * @param int $target_directory_id Target term ID.
     * @return void
     */
    private function copy_directory_type_meta( $source_directory_id, $target_directory_id ) {
        $meta = get_term_meta( (int) $source_directory_id );

        if ( empty( $meta ) || ! is_array( $meta ) ) {
            return;
        }

        foreach ( $meta as $meta_key => $values ) {
            if ( ! is_array( $values ) || empty( $values ) ) {
                continue;
            }

            if ( ! empty( $this->get_raw_term_meta( $target_directory_id, $meta_key, false ) ) ) {
                continue;
            }

            foreach ( $values as $value ) {
                add_term_meta( (int) $target_directory_id, $meta_key, maybe_unserialize( $value ) );
            }
        }
    }

    /**
     * Get completed package job translations keyed by string name.
     *
     * @param object   $job             WPML translation job.
     * @param callable $decoder         WPML field decoder.
     * @param string   $package_context Expected package string context.
     * @return array
     */
    private function get_job_translations_by_string_name( $job, $decoder, $package_context ) {
        $translations = [];

        foreach ( $job->elements as $field ) {
            if ( empty( $field->field_translate ) || empty( $field->field_type ) ) {
                continue;
            }

            $field_context = apply_filters( 'wpml_save_external_package_field_context', $package_context, $field, $job );
            if ( $field_context !== $package_context ) {
                continue;
            }

            $format     = isset( $field->field_format ) ? $field->field_format : '';
            $field_data = isset( $field->field_data_translated ) ? $field->field_data_translated : '';
            $translated = is_callable( $decoder ) ? $decoder( $field_data, $format ) : $field_data;

            if ( is_string( $translated ) && '' !== trim( $translated ) ) {
                $translations[ $field->field_type ] = $translated;
            }
        }

        return $translations;
    }

    /**
     * Build a WPML package definition for a source directory type.
     *
     * @param int $source_directory_id Source directory type term ID.
     * @return array
     */
    private function get_package( $source_directory_id ) {
        $source_directory_id = (int) $source_directory_id;
        $term                = get_term( $source_directory_id, $this->get_directory_taxonomy() );
        $trid                = WPML_Helper::get_element_trid( $source_directory_id, $this->get_directory_taxonomy() );

        $name = $trid > 0 ? 'directory_builder_' . $trid : 'directory_builder_' . $source_directory_id;

        return [
            'kind'      => self::PACKAGE_KIND,
            'kind_slug' => self::PACKAGE_KIND_SLUG,
            'name'      => $name,
            'title'     => sprintf(
                'Directorist Directory Builder: %s',
                ( $term && ! is_wp_error( $term ) && ! empty( $term->name ) ) ? $term->name : $source_directory_id
            ),
            'edit_link' => admin_url( 'edit.php?post_type=at_biz_dir&page=atbdp-directory-types&listing_type_id=' . $source_directory_id . '&action=edit' ),
            'view_link' => home_url( '/' ),
        ];
    }

    /**
     * Get raw term meta without triggering metadata filters.
     *
     * @param int    $term_id  Term ID.
     * @param string $meta_key Meta key.
     * @param bool   $single   Whether to return a single value.
     * @return mixed
     */
    private function get_raw_term_meta( $term_id, $meta_key, $single = true ) {
        global $wpdb;

        $values = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = %s ORDER BY meta_id ASC",
                (int) $term_id,
                $meta_key
            )
        );

        if ( empty( $values ) ) {
            return $single ? '' : [];
        }

        $values = array_map( 'maybe_unserialize', $values );

        return $single ? $values[0] : $values;
    }

    /**
     * Normalize a value coming from the get_term_metadata filter.
     *
     * @param mixed $value Filtered metadata value.
     * @return mixed|null
     */
    private function normalize_filtered_meta_value( $value ) {
        if ( null === $value ) {
            return null;
        }

        if ( is_array( $value ) && array_key_exists( 0, $value ) ) {
            return $value[0];
        }

        return $value;
    }

    /**
     * Check if a term is a Directorist directory type.
     *
     * @param int $term_id Term ID.
     * @return bool
     */
    private function is_directory_type_term( $term_id ) {
        $term = get_term( (int) $term_id );

        return $term && ! is_wp_error( $term ) && $this->get_directory_taxonomy() === $term->taxonomy;
    }

    /**
     * Check whether a WPML translatable item is our builder package.
     *
     * @param mixed $item Translatable item.
     * @return bool
     */
    private function is_directory_builder_package_item( $item ) {
        return is_object( $item )
            && class_exists( '\WPML_Package' )
            && is_a( $item, '\WPML_Package' )
            && ! empty( $item->kind_slug )
            && self::PACKAGE_KIND_SLUG === $item->kind_slug;
    }

    /**
     * Check if WPML string package APIs are available.
     *
     * @return bool
     */
    private function is_wpml_active() {
        return defined( 'ICL_SITEPRESS_VERSION' )
            && has_action( 'wpml_register_string' )
            && has_filter( 'wpml_translate_string' )
            && has_filter( 'wpml_object_id' );
    }

    /**
     * Get Directorist directory type taxonomy key.
     *
     * @return string
     */
    private function get_directory_taxonomy() {
        return defined( 'ATBDP_DIRECTORY_TYPE' ) ? ATBDP_DIRECTORY_TYPE : 'atbdp_listing_types';
    }

    /**
     * Sanitize a string for identifier use.
     *
     * @param string $value Raw value.
     * @return string
     */
    private function safe_slug( $value ) {
        $slug = sanitize_key( sanitize_title( (string) $value ) );

        return '' !== $slug ? $slug : 'item';
    }

    /**
     * Make meta key readable.
     *
     * @param string $meta_key Meta key.
     * @return string
     */
    private function humanize_meta_key( $meta_key ) {
        return ucwords( str_replace( '_', ' ', (string) $meta_key ) );
    }

    /**
     * Make a nested path item readable in ATE.
     *
     * @param string $path_part Raw path item.
     * @return string
     */
    private function humanize_path_part( $path_part ) {
        if ( is_numeric( $path_part ) ) {
            return '#' . (string) $path_part;
        }

        return ucwords( str_replace( [ '_', '-' ], ' ', (string) $path_part ) );
    }
}
