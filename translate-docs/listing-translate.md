# Flow: English listing ke French-e translate kore listing page-e show korano

# 1. Confirm active plugins:
# - Directorist
# - Directorist WPML Integration fixed version
# - WPML Multilingual CMS
# - WPML String Translation only UI text er jonno, listing show er jonno mandatory na

# 2. WPML settings check:
# WP Admin > WPML > Settings
# Post Types Translation:
# - Listings / at_biz_dir = Translatable
# Taxonomies Translation:
# - Directory Types = Translatable
# - Categories = Translatable
# - Locations = Translatable
# - Tags = Translatable

# 3. Listing translate:
# WP Admin > Directory Listings > All Listings
# English listing edit/open koro
# Right side Language box theke French er "+" icon click koro
# Title, description, price/custom fields translate/copy koro
# Same/translated Category, Location, Directory Type assign ache kina check koro
# Publish/Update koro
# Translation complete hole "+" icon pencil icon hoye jabe

# 4. French all-listings page check:
# WP Admin > Pages
# English "All Listings" page er French translation thakte hobe
# Page content e shortcode/block thakte hobe:
# [directorist_all_listing]
# French page publish koro

# 5. Frontend verify:
# Correct local URL:
# http://directorist-wpml.host/fr/index.php/all-listings-fr/

# 6. Expected:
# Jodi listing translation published hoy, translated taxonomy/directory relation thake,
# and fixed integration plugin active thake, tahole French page-e listing show korbe.

# Important:
# String Translation complete na thakleo listing show korar kotha.
# String Translation sudhu button/label/form text er jonno:
# "Sign in", "Post your ad", field labels, custom UI text etc.


# Flow: Full Directorist page translation test kora

# Part 1. Precheck:
# English listing translation code change kora hoy nai.
# First-e check kora hoy:
# - Active language: en, fr
# - English Directorist system pages ache kina
# - French page translation ache kina
# - Directorist shortcodes copied ache kina
# - Listing translation already frontend-e show kortese kina

# Part 2. Backup:
# Page translation test korar age local DB backup neya hoy:
# C:\laragon\www\Directorist-wpml\wp-content\backups\before-full-page-translation-20260507.sql

# Part 3. Page translation create/update:
# WPML-safe WordPress API diye English page er French translation create/update kora hoy.
# Direct SQL diye translation relation manually insert kora hoy nai.
# WPML hook use kora hoy:
# do_action( 'wpml_set_element_language_details', ... )

# Part 4. Je page gulo translate kora hoy:
# Search Home -> Recherche
# Search Result -> Resultats de recherche
# Add Listing -> Ajouter une annonce
# All Listings -> Toutes les annonces
# Single Category -> Categorie
# Single Location -> Emplacement
# Single Tag -> Etiquette
# Author Profile -> Profil de l auteur
# Dashboard -> Tableau de bord
# Sign In -> Connexion inscription
# Booking Confirmation -> Confirmation de reservation

# Part 5. Shortcode copy:
# English page-er same Directorist shortcode French page-e rakha hoy:
# [directorist_search_listing]
# [directorist_search_result]
# [directorist_add_listing]
# [directorist_all_listing]
# [directorist_category]
# [directorist_location]
# [directorist_tag]
# [directorist_author_profile]
# [directorist_user_dashboard]
# [directorist_signin_signup]
# [directorist_booking_confirmation]

# Part 6. Frontend URL format:
# Ei local site-e permalink structure index.php based.
# Tai correct French URL format:
# http://directorist-wpml.host/fr/index.php/toutes-les-annonces/
# http://directorist-wpml.host/fr/index.php/ajouter-une-annonce/
# http://directorist-wpml.host/fr/index.php/resultats-de-recherche/
# Wrong format avoid korte hobe:
# http://directorist-wpml.host/index.php/fr/...

# Part 7. Fault jeita dhora pore:
# Page render test-e Search page/search form e warning aschilo:
# - wp-includes/meta.php:667
# - directorist/templates/archive/basic-search-form.php line 18
# Root cause:
# Search_Form_Field_Translation.php get_term_metadata filter-e associative array direct return korto.
# WordPress single meta request-e array return pele $check[0] expect kore.
# Associative array-e index 0 na thakay warning/null result hoto.

# Part 8. Integration fix:
# File:
# app/Controller/Hook/Search_Form_Field_Translation.php
# Fix:
# - Invalid/empty raw meta hole original $value return kora hoy.
# - Translated associative meta array [ translated_array ] wrapper diye return kora hoy.
# Eta WordPress metadata filter-er expected shape maintain kore.

# Part 9. WPML config fix:
# File:
# wpml-config.xml
# Missing Directorist page option add kora hoy:
# <key name="signin_signup_page" type="post-ids" sub-type="page"/>
# Reason:
# Current Directorist sign-in/sign-up page option holo signin_signup_page.
# Old config-e custom_registration/user_login chilo, but signin_signup_page chilo na.

# Part 10. Retest result:
# FR All Listings page:
# http://directorist-wpml.host/fr/index.php/toutes-les-annonces/
# - 200 OK
# - html lang fr-FR
# - translated listing show kore
# - no frontend fatal/warning/notice

# FR Search Result page:
# http://directorist-wpml.host/fr/index.php/resultats-de-recherche/
# - translated listing show kore
# - no frontend fatal/warning/notice

# FR Category/Location/Tag pages:
# http://directorist-wpml.host/fr/index.php/categorie/
# http://directorist-wpml.host/fr/index.php/emplacement/
# http://directorist-wpml.host/fr/index.php/etiquette/
# - listing render kore
# - no frontend fatal/warning/notice

# FR Add Listing page:
# http://directorist-wpml.host/fr/index.php/ajouter-une-annonce/
# Logged-in shortcode test:
# - form=yes
# - fatal=no

# FR Dashboard:
# http://directorist-wpml.host/fr/index.php/tableau-de-bord/
# Logged-out state-e translated Sign In page-e redirect kore:
# http://directorist-wpml.host/fr/index.php/connexion-inscription/

# Part 11. Final note:
# Page translation er jonno listing query fix remove kora hoy nai.
# Listing show korar jonno still needed:
# - translated listing
# - translated directory type/category/location/tag
# - translated Directorist page
# - fixed integration plugin
# String Translation UI text-er jonno needed, listing visibility-er jonno mandatory na.


# Flow: Directorist Directory Builder text ATE/Translation Dashboard diye translate kora

# Problem:
# Page title/menu translate korlei Add Listing/All Listings page-er sob text translate hoy na.
# Karon onek text page content na, Directorist Directory Builder term meta theke ashe.

# Example builder text:
# General Information
# Contact Information
# Map
# Images & Video
# Title
# Enter a title
# Long Details
# Save & Preview
# Finish
# Save & Next
# Read More
# Related Listings

# WPML compatibility concern:
# Recommended plugin hote hole manual-only directory builder translation enough na.
# Builder strings WPML Translation Dashboard / ATE / automatic translation flow-e expose korte hobe.

# Integration implementation:
# New file:
# app/Controller/Hook/Directory_Builder_String_Package.php

# Hook registered in:
# app/Controller/Hook/Init.php

# WPML package kind:
# Directorist Directory Builder
# kind_slug:
# directorist-directory-builder

# Package title example:
# Directorist Directory Builder: General

# Builder meta keys registered:
# submission_form_fields
# search_form_fields
# single_listings_contents
# listings_card_grid_view
# listings_card_list_view
# submit_button_label

# Template UI strings also registered:
# Finish
# Save & Next
# Go to Next

# WPML config updated:
# wpml-config.xml
# Added custom-term-fields:
# search_form_fields
# listings_card_grid_view
# listings_card_list_view
# submit_button_label

# How it works:
# 1. Default language directory type meta theke clean strings extract hoy.
# 2. Strings WPML string package hisebe register hoy.
# 3. WPML Translation Dashboard-e package show kore:
#    Directorist Directory Builder
# 4. User package select kore ATE/automatic translation diye strings translate korte pare.
# 5. Frontend render-er age get_term_meta filter translated values apply kore.
# 6. Manual translated directory type meta missing holeo source builder meta fallback + package translation kaj kore.

# Local proof test:
# Package created:
# Directorist Directory Builder: General
# Registered strings:
# 113

# Test FR translations added locally:
# General Information -> Informations generales
# Contact Information -> Informations de contact
# Title -> Titre
# Enter a title -> Saisissez un titre
# Long Details -> Details complets
# Finish -> Terminer
# Save & Next -> Enregistrer et suivant
# Save & Preview -> Enregistrer et previsualiser

# Shortcode test:
# do_shortcode('[directorist_add_listing]') in fr language as logged-in admin
# Result:
# Informations generales=yes
# Informations de contact=yes
# Titre=yes
# Saisissez un titre=yes
# Details complets=yes
# Terminer=yes
# Enregistrer et suivant=yes
# Enregistrer et previsualiser=yes
# Finish=no
# Save & Next=no
# fatal=no

# Full page retest:
# FR Search page: 200 OK, no frontend error
# FR Search Result page: listing show, no frontend error
# FR Add Listing page: 200 OK, shortcode form translated when logged in
# FR All Listings page: listing show, no frontend error
# FR Category/Location/Tag pages: listing render, no frontend error

# Important:
# Eta manual directory builder edit-er replacement.
# User chaile still manually override korte parbe, but recommended flow holo:
# WPML > Translation Dashboard > Directorist Directory Builder package > ATE/automatic translation.

# 2026-05-07 final ATE compatibility retest:
# Package save/update refresh:
# directorist_after_update_directory_type hook run korar pore package still 113 strings.

# Safe fallback:
# WPML kono package string empty/null return korle integration original source value fallback kore.
# Translated builder meta null scan:
# submission_form_fields_nulls=0
# search_form_fields_nulls=0
# single_listings_contents_nulls=0
# listings_card_grid_view_nulls=0
# listings_card_list_view_nulls=0
# submit_button_label_nulls=0

# HTTP page matrix:
# /fr/index.php/recherche/ : 200, lang=fr-FR, no PHP error in HTML
# /fr/index.php/resultats-de-recherche/ : 200, lang=fr-FR, listing visible, no PHP error in HTML
# /fr/index.php/ajouter-une-annonce/ : 200, lang=fr-FR, add listing strings translated, no PHP error in HTML
# /fr/index.php/toutes-les-annonces/ : 200, lang=fr-FR, listing visible, no PHP error in HTML
# /fr/index.php/categorie/ : 200, lang=fr-FR, listing visible, no PHP error in HTML
# /fr/index.php/emplacement/ : 200, lang=fr-FR, listing visible, no PHP error in HTML
# /fr/index.php/etiquette/ : 200, lang=fr-FR, listing visible, no PHP error in HTML
# /fr/index.php/profil-de-l-auteur/ : 200, lang=fr-FR, no PHP error in HTML
# /fr/index.php/tableau-de-bord/ : 200, lang=fr-FR, no PHP error in HTML
# /fr/index.php/connexion-inscription/ : 200, lang=fr-FR, no PHP error in HTML
# /fr/index.php/confirmation-de-reservation/ : 200, lang=fr-FR, no PHP error in HTML

# Note:
# CLI shortcode debug log e directorist-booking textdomain early-load notice and wp_kses null deprecation dekha jay.
# Same deprecation English shortcode eo hoy, so eta directorist-wpml-integration translation regression na.
# Browser/HTTP HTML output clean.

# 2026-05-07 builder dynamic coverage improvement:
# single_listing_header meta key include kora hoyeche.
# Extractor now UI-looking dynamic keys dhore:
# *label
# *placeholder
# *description
# *text
# *title
# *heading
# lat_long
# options path labels

# Internal keys still skip:
# field_key, widget_key, widget_name, widget_group, hook, icon, type,
# required, pricing_type, price_range_options, max limits, ids/classes.

# Package count:
# Before broadening: 113 strings
# After broadening: 149 strings

# Newly verified ATE package strings:
# Tagline
# Your Listing's motto or tag-line
# Select Price Range
# Price
# Or Enter Coordinates (latitude and longitude) Manually
# Select Files
# Bookmark / Share / Report from single_listing_header
# You are about to publish
# Add Social
# or drag and drop image here

# Local simulated ATE completion:
# WPML API icl_add_string_translation() diye FR translations add kore shortcode render test.
# English leftovers checked:
# Short Description/Excerpt=no
# Tagline=no
# Pricing=no
# Select Price Range=no
# Zip/Post Code=no
# Phone Number=no
# Social Information=no
# Add Social=no
# Or Enter Coordinates=no
# Hide Map=no
# Select Files=no
# or drag and drop image here=no
# You are about to publish=no
# Are you sure you want to publish this listing?=no
# Video Url=no

# Surface meta retest:
# submission_form_fields -> Slogan, map lat_long, image select file translated
# search_form_fields -> Basic/Advanced translated
# single_listings_contents -> related section translated
# single_listing_header -> Bookmark/Share/section title translated
# listings_card_grid_view -> Read More translated
# listings_card_list_view -> Read More translated
# all null scans = 0
