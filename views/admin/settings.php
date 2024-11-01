<div class="wrap">
  <img class="tracify-settings-logo" src="<?php echo esc_attr($logo_image_url); ?>" alt="Tracify Logo">
  <form method="post" action="options.php" class="tracify-form">
    <?php
 settings_fields('tracify_option_group'); do_settings_sections('tracify-admin'); ?>
    <?php submit_button(); ?>
  </form>
</div>