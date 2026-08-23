<?php
/**
 * Backward-compatible hook class name.
 *
 * @package Directorist_WPML_Integration
 */

namespace Directorist_WPML_Integration\Controller\Hook;

/**
 * The original class listened to Directorist hooks that do not exist in the
 * supported core mailer. Keep its public class name while using the native
 * settings/email package integration implemented by the parent.
 */
class Email_Translation extends Admin_Text_Translation {}
