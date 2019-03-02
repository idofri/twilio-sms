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
        register_setting( 'twilio', 'twilio_auto_login' );
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
                                   value="<?= esc_attr( get_option( 'twilio_api_key' ) ) ?>"
                            />
                            <p class="description">
                                <?php
                                printf(
                                    __( 'You must create a <a href="%s">new Verify application under your Twilio account</a> and put its API key here.', 'twilio-sms' ),
                                    'https://www.twilio.com/docs/verify/api/applications'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="2">     
                            <label for="twilio_auto_login">
                                <input type="checkbox"
                                       id="twilio_auto_login"
                                       name="twilio_auto_login"  
                                       value="1"
                                       <?php checked( '1', get_option( 'twilio_auto_login' ) ); ?>
                                />
                                <?php _e( 'Auto Login after verification', 'twilio-sms' ); ?>
                            </label>
                        </th>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div><?php
    }
    
}