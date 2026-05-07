<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper;
use Directorist_WPML_Integration\Controller\Hook\Directory_Translation;

class Init {
	
    /**
     * Constuctor
     * 
     * @return void
     */
    function __construct() {

        // Register Hooks
        $hooks = $this->get_hooks();
        Helper\Serve::register_services( $hooks );

    }

    /**
     * Get Hooks
     * 
     * @return array $hooks
     */
    protected function get_hooks() {
        return [
            REST_API::class,
            Filter_Permalinks::class,
            Directory_Builder_Actions::class,
            Listings_Actions::class,
            Directory_Type_Meta_Translation::class,
            Directory_Builder_String_Package::class,
            Directory_Type_Translation_Management_Button::class,
            Page_Setup_Translation::class,
            Category_Directory_Sync::class,
            Email_Translation::class,

            Settings_Registration::class,
            Option_Translation::class,
            Query_Filtering::class,
            Listing_Count_Filter::class,
            Search_Form_Filter::class,
            Search_Form_Field_Translation::class,
            Add_Listing_Form_Translation::class,
            Selectfield_Translation::class,

            Directory_Translation::class,
            Block_Widget_Translation::class,
            Sorting_Options_Translation::class,

        ];
    }
}
