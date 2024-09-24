<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Yper_Admin_Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu() {
        add_options_page(
            'WP Yper Settings',
            'WP Yper',
            'manage_options',
            'wp-yper',
            array($this, 'create_admin_page')
        );
    }

    public function register_settings() {
        register_setting('wp_yper_settings_group', 'wp_yper_environment'); 
        register_setting('wp_yper_settings_group', 'wp_yper_client_id');
        register_setting('wp_yper_settings_group', 'wp_yper_client_secret');
        register_setting('wp_yper_settings_group', 'wp_yper_retailpoint_id');
        register_setting('wp_yper_settings_group', 'wp_yper_pro_id');
        register_setting('wp_yper_settings_group', 'wp_yper_pro_secret_token');
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1>WP Yper Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_yper_settings_group');
                do_settings_sections('wp_yper_settings_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Environment</th>
                        <td>
                            <select name="wp_yper_environment">
                                <option value="production" <?php selected(get_option('wp_yper_environment'), 'production'); ?>>Production</option>
                                <option value="beta" <?php selected(get_option('wp_yper_environment'), 'beta'); ?>>Beta</option>
                                <option value="development" <?php selected(get_option('wp_yper_environment'), 'development'); ?>>Development</option>
                                <option value="alpha" <?php selected(get_option('wp_yper_environment'), 'alpha'); ?>>Alpha</option>
                                <option value="rc" <?php selected(get_option('wp_yper_environment'), 'rc'); ?>>RC</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Client ID</th>
                        <td><input type="text" name="wp_yper_client_id" value="<?php echo esc_attr(get_option('wp_yper_client_id')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Client Secret</th>
                        <td><input type="text" name="wp_yper_client_secret" value="<?php echo esc_attr(get_option('wp_yper_client_secret')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Retail Point ID</th>
                        <td><input type="text" name="wp_yper_retailpoint_id" value="<?php echo esc_attr(get_option('wp_yper_retailpoint_id')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Pro ID</th>
                        <td><input type="text" name="wp_yper_pro_id" value="<?php echo esc_attr(get_option('wp_yper_pro_id')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Pro Secret Token</th>
                        <td><input type="text" name="wp_yper_pro_secret_token" value="<?php echo esc_attr(get_option('wp_yper_pro_secret_token')); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
?>
