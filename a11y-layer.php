<?php
/**
 * Plugin Name: Accessibility Layer
 * Description: Site-wide accessibility fixes for Elementor sites: the main landmark, heading order, gallery and menu names, focus rings, and small-screen reflow. Lives in code, so editing pages in Elementor never undoes it.
 * Version: 1.0.0
 * License: GPL-2.0-or-later
 *
 * Settings: set a prefix in wp-config.php (define( 'A11Y_LAYER_PREFIX', 'acme' );) and change
 * anything else with the 'a11y_layer_config' filter. See README.md for every option.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/a11y-layer/class-a11y-layer.php';
require_once __DIR__ . '/a11y-layer/class-a11y-layer-html.php';

A11y_Layer::boot( __FILE__ );
