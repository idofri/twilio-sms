<?php
namespace Twilio\Verify;

class Settings {
    
    public function __construct() {
        add_action( 'init',       [ $this, 'loadTextDomain' ] );
        add_action( 'admin_menu', [ $this, 'addSettingsPage' ] );
        add_action( 'admin_init', [ $this, 'registerSettings' ] );
    }
    
    public function loadTextDomain() {
        load_plugin_textdomain( 'twilio-sms', false, dirname( plugin_basename( __DIR__ ) ) . '/languages' );
    }
    
    public function registerSettings() {
        register_setting( 'twilio', 'twilio_api_key' );
    }

    public function addSettingsPage() {
        add_submenu_page(
            'options-general.php',
            __( 'Twilio', 'twilio-sms' ),
            __( 'Twilio', 'twilio-sms' ),
            'manage_options',
            'twilio',
            [ $this, 'renderSettingsPage' ]
        );
    }
    
    public function renderSettingsPage() {
        ?><div class="wrap">
            <h1><?php _e( 'Twilio Settings', 'twilio-sms' ) ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'twilio' ) ?>
                <?php do_settings_sections( 'twilio' ) ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e( 'Verify Application API Key', 'twilio-sms' ) ?></th>
                        <td>
                            <input type="password"
                                   class="regular-text ltr textright"
                                   name="twilio_api_key"
                                   value="<?php echo esc_attr( get_option( 'twilio_api_key' ) ) ?>"
                            />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div><?php
    }
    
}