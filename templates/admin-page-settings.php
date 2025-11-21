<?php
$readonly = array(
  'server_url' => defined( 'WP_TYPESENSE_SERVER_URL' ),
  'admin_api_key' => defined( 'WP_TYPESENSE_ADMIN_API_KEY' ),
  'search_api_key' => defined( 'WP_TYPESENSE_SEARCH_API_KEY' ),
)
?>
<div class="wrap">
	<h1>Typesense Settings</h1>
	<form method="post" action="options.php">
    <?php settings_fields( 'wp-typesense-settings' ); ?>
    <?php do_settings_sections( 'wp-typesense-settings' ); ?>
    <table class="form-table">
      <tr>
        <th scope="row">
          <label for="wp_typesense_server_url">Typesense server URL</label>
        </th>
        <td>
            <input type="url" name="wp_typesense_server_url" value="<?= esc_attr( \Websimple\WpTypesense\Settings::get_server_url() ) ?>" class="regular-text code" <?= $readonly['server_url'] ? 'readonly aria-readonly="true"' : '' ?> />
        </td>
      </tr>
      <tr>
        <th scope="row">
          <label for="wp_typesense_admin_api_key">Admin API key</label>
        </th>
        <td>
            <input type="password" name="wp_typesense_admin_api_key" value="<?= esc_attr( \Websimple\WpTypesense\Settings::get_admin_api_key() ) ?>"  class="regular-text" <?= $readonly['admin_api_key'] ? 'readonly aria-readonly="true"' : '' ?> />
        </td>
      </tr>
      <tr>
        <th scope="row">
          <label for="wp_typesense_search_api_key">Search API key</label>
        </th>
        <td>
            <input type="password" name="wp_typesense_search_api_key" value="<?= esc_attr( \Websimple\WpTypesense\Settings::get_search_api_key() ) ?>" class="regular-text" <?= $readonly['search_api_key'] ? 'readonly aria-readonly="true"' : '' ?> />
        </td>
      </tr>
      <tr>
        <th scope="row">
          <p>Server status</p>
        </th>
        <td>
          <?php
          if ( $version = \Websimple\WpTypesense\API::version() ) {
            echo "<p style='color:green;'>Connected to Typesense server (version $version)</p>";
          } else {
            echo '<p style="color:red;">Could not connect to Typesense server. Please check your settings.</p>';
          }
          ?>
        </td>
      </tr>
    </table>
    <?php submit_button(); ?>
  </form>
</div>
