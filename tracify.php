<?php
/*
 * Plugin Name:         Tracify
 * Description:         Our patent-pending AI technology lets you track every customer interaction without invading their privacy. Cross-device, cross-domain, cross-session.
 * Author:              Tracify
 * Author URI:          https://www.tracify.ai/
 * Text Domain:         tracify
 * Domain Path:         /languages
 * License:             GPLv3
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.html
 * Version:             3.0.2
 * Requires at least:   5.0
 * Requires PHP:        7.2
 */

define('TRACIFY_PLUGIN_VER', '3.0.2');
define('TRACIFY_DIR_PATH', plugin_dir_path(__FILE__));
define('TRACIFY_DIR_URL', plugin_dir_url(__FILE__));

require_once TRACIFY_DIR_PATH . 'classes/tracify_admin.php';
require_once TRACIFY_DIR_PATH . 'classes/tracify_backend.php';

// This part of the code initializes and/or
// updates the settings when updating the plugin.
$options = get_option('tracify_options');
$defaults = array(
  'csid' => '',
  'tracify-token' => '',
  'fingerprinting' => '1',
  'development-mode' => '0',
  'beta-mode' => '0',
  'orders-when-processing' => '1',
  'orders-when-on-hold' => '1',
  'orders-when-completed' => '1',
);

// Initial load of the plugin, where
// it was never pre-installed.
if (!$options) {
  add_option('tracify_options', $defaults);
// it was pre-installed, but new options might be missing.
} else if (is_array($options)) {
  // check for correct defaults
  $changed = false;
  foreach ($defaults as $key => $value) {
    if (!array_key_exists($key, $options)) {
      $options[$key] = $value;
      $changed = true;
    }
  }
  if ($changed) {
    update_option('tracify_options', $options);
  }
}

if (is_admin()) {
  $tracify_admin = new TracifyAdmin();
  $tracify_backend = new TracifyBackend();
  // We need the backend hooks in case the customer
  // changes order statuses in the admin.
  $tracify_backend->install_backend_hooks();
} else {
  $tracify_backend = new TracifyBackend();
  # Tracking scripts etc, or thankyou page integration.
  $tracify_backend->install_frontend_hooks();
  # Order processing done through the storefront checkout.
  $tracify_backend->install_backend_hooks();
}


