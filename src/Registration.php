<?php
namespace Twilio\Verify;

use Authy\AuthyApi;
use WP_Error;
use WP_User;

class Registration {
    
    const COUNTRY_CODE = 972;
    
    const VIA_METHOD = 'sms';

    protected static $uuid;
    
    protected static $phone;
    
    protected static $password;
    
    public function __construct() {
        add_action( 'login_form_verify',     [ $this, 'showVerificationForm' ] );
        add_action( 'register_form',         [ $this, 'alterRegistrationFields' ] );
        add_action( 'register_post',         [ $this, 'beforeRegistration' ], 10, 3 );
        add_action( 'register_new_user',     [ $this, 'afterRegistration' ] );
        add_action( 'login_enqueue_scripts', [ $this, 'enqueueRegistrationScripts' ] );
        add_filter( 'wp_login_errors',       [ $this, 'verificationSuccessMessage' ] );
        add_filter( 'wp_authenticate_user',  [ $this, 'preventUnverifiedUserLogin' ] );
        
        remove_action( 'register_new_user', 'wp_send_new_user_notifications' );
    }
    
    public function getApiKey() {
        return get_option( 'twilio_api_key' );
    }
    
    public function setUuid( $uuid ) {
        self::$uuid = $uuid;
        return $this;
    }
    
    public function getUuid() {
        return self::$uuid;
    }
    
    public function setPhone( $phone ) {
        self::$phone = $phone;
        return $this;
    }
    
    public function getPhone() {
        return self::$phone;
    }
    
    public function setPassword( $password ) {
        self::$password = $password;
        return $this;
    }
    
    public function getPassword() {
        return self::$password;
    }
    
    public function getUserByUuid( $uuid ) {
        $users = get_users( [
            'meta_key'   => 'uuid',
            'meta_value' => $uuid
        ] );
        
        if ( $users ) {
            return $users[0];
        }
        
        return false;
    }
    
    public function checkVerificationCode( $phone, $code ) {
        $authyApi = new AuthyApi( $this->getApiKey() );
        $res = $authyApi->phoneVerificationCheck( $phone, self::COUNTRY_CODE, $code );

        if ( $res->ok() ) {
            return true;
        }

        return false;
    }

    public function startPhoneVerification( WP_Error $errors ) {
        $authyApi = new AuthyApi( $this->getApiKey() );
        $res = $authyApi->phoneVerificationStart( $this->getPhone(), self::COUNTRY_CODE, self::VIA_METHOD );

        if ( $res->ok() ) {
            return $res->bodyvar('uuid');
        }
        
        $errors->add( 'authy_error',  __( sprintf( '<strong>ERROR</strong>: %s',  $res->errors()->message ), 'twilio-sms' ) );
        return false;
    }

    public function enqueueRegistrationScripts() {
        global $action;

        if ( 'register' === $action ) {
            wp_enqueue_script( 'utils' );
            wp_enqueue_script( 'user-profile' );
        }
    }
    
    public function verificationSuccessMessage( $errors ) {
        if ( isset( $_GET['registration'] ) && 'verified' == $_GET['registration'] ) {
            $errors->add( 'registered', __( 'Verification completed successfully.', 'twilio-sms' ), 'message' );
        }
        
        return $errors;
    }
    
    public function preventUnverifiedUserLogin( $userdata ) {
        if ( ! is_wp_error( $userdata ) && ! $this->isVerified( $userdata ) ) {
            $uuid = get_user_meta( $userdata->ID, 'uuid', true );
            wp_redirect( site_url( "wp-login.php?action=verify&uuid={$uuid}" ) );
            exit();
        }
        
        return $userdata;
    }

    public function isVerified( WP_User $user ) {
        return ! in_array( get_option( 'default_role' ), (array) $user->roles ) || get_user_meta( $user->ID, 'verified', true );
    }

    public function setVerified( $user_id, $verified ) {
        update_user_meta( $user_id, 'verified', $verified );
        return $this;
    }
    
    public function beforeRegistration( $sanitized_user_login, $user_email, WP_Error $errors ) {
        if ( isset( $_POST['pass1'] ) && $_POST['pass1'] != $_POST['pass2'] ) {
            $errors->add( 'password_reset_mismatch', __( 'The passwords do not match.', 'twilio-sms' ) );
        } elseif ( ! empty( $_POST['pass1'] ) ) {
            $this->setPassword( $_POST['pass1'] );
        }
        
        if ( empty( $_POST['user_phone'] ) ) {
            $errors->add( 'empty_phone', __( '<strong>ERROR</strong>: Enter a phone number.', 'twilio-sms' ) );
        } elseif ( ! $errors->has_errors() ) {
            $phone = sanitize_text_field( $_POST['user_phone'] );
            $uuid = $this->setPhone( $phone )->startPhoneVerification( $errors );
            if ( $uuid ) {
                $this->setUuid( $uuid );
            }
        }
    }

    public function afterRegistration( $user_id ) {
        $user = get_userdata( $user_id );
        
        if ( $password = $this->getPassword() ) {
            remove_action( 'after_password_reset', 'wp_password_change_notification' );
            reset_password( $user, $password );
            $this->setVerified( $user_id, false );
        }

        if ( $phone = $this->getPhone() ) {
            update_user_meta( $user_id, 'user_phone', $phone );
        }
        
        if ( $uuid = $this->getUuid() ) {
            update_user_meta( $user_id, 'uuid', $uuid );
            wp_redirect( site_url( "wp-login.php?action=verify&uuid={$uuid}" ) );
            exit();
        }
    }

    public function autoLogin( $user_id ) {
        $autoLogin = get_option( 'twilio_auto_login' );

        if ( $autoLogin ) {
            wp_clear_auth_cookie();
            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id );
        }
        
        return $this;
    }

    public function afterVerificationRedirectUrl() {
        return is_user_logged_in() ? home_url() : 'wp-login.php?registration=verified';
    }

    public function showVerificationForm() {
        $uuid = '';
        $errors = new WP_Error();
        
        if ( ! empty( $_POST['uuid'] ) && ! empty( $_POST['code'] ) ) {
            $uuid = sanitize_text_field( $_POST['uuid'] );
            $code = sanitize_text_field( $_POST['code'] );
            if ( $user = $this->getUserByUuid( $uuid ) ) {
                $phone = get_user_meta( $user->ID, 'user_phone', true );
                if ( $this->checkVerificationCode( $phone, $code ) ) {
                    $this->setVerified( $user->ID, true )->autoLogin( $user->ID );                  
                    wp_safe_redirect( $this->afterVerificationRedirectUrl() );
                    exit();
                }
                $errors->add( 'invalidcode', __( 'Verification code is incorrect.', 'twilio-sms' ) );
            }
        }
        
        $formAction = network_site_url( 'wp-login.php?action=verify', 'login_post' );
        if ( ! empty( $_GET['uuid'] ) ) {
            $uuid = sanitize_text_field( $_GET['uuid'] );
            $formAction = add_query_arg( 'uuid', $uuid, $formAction );
        }
        
        login_header( __( 'Phone Verification' ), '<p class="message">' . __( 'Please type the verification code sent to you.', 'twilio-sms' ) . '</p>', $errors );

        ?><form name="verificationform" action="<?= esc_url( $formAction ); ?>" method="post">
            <p>
                <label for="code" ><?php _e( 'Verification Code', 'twilio-sms' ); ?><br />
                <input type="text" name="code" id="code" class="input" value="" size="20" autocapitalize="off" /></label>
            </p>
            <p class="submit">
                <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Verify', 'twilio-sms' ); ?>" />
            </p>
            <?php if ( $uuid ) : ?>
            <input type="hidden" name="uuid" value="<?= esc_attr( $uuid ); ?>" />
            <?php endif; ?>
        </form><?php

        login_footer( 'code' );
        exit;
    }

    public function alterRegistrationFields() {
        $user_phone = '';
        if ( isset( $_POST['user_phone'] ) && is_string( $_POST['user_phone'] ) ) {
            $user_phone = wp_unslash( $_POST['user_phone'] );
        }
        
        ?><p>
            <label for="user_phone"><?php _e( 'Phone', 'twilio-sms' ); ?><br />
            <input type="tel" name="user_phone" id="user_phone" class="input" value="<?= esc_attr( wp_unslash( $user_phone ) ); ?>" size="20" autocapitalize="off" /></label>
        </p>
        <div class="user-pass1-wrap">
            <p>
                <label for="pass1"><?php _e( 'Password' ); ?></label>
            </p>
            <div class="wp-pwd">
                <div class="password-input-wrapper">
                    <input type="password" data-reveal="1" data-pw="<?= esc_attr( wp_generate_password( 16 ) ); ?>" name="pass1" id="pass1" class="input password-input" size="24" value="" autocomplete="off" aria-describedby="pass-strength-result" />
                    <span class="button button-secondary wp-hide-pw hide-if-no-js">
                        <span class="dashicons dashicons-hidden"></span>
                    </span>
                </div>
                <div id="pass-strength-result" class="hide-if-no-js" aria-live="polite"><?php _e( 'Strength indicator', 'twilio-sms' ); ?></div>
            </div>
            <div class="pw-weak">
                <label>
                    <input type="checkbox" name="pw_weak" class="pw-checkbox" />
                    <?php _e( 'Confirm use of weak password', 'twilio-sms' ); ?>
                </label>
            </div>
        </div>
        <p class="user-pass2-wrap">
            <label for="pass2"><?php _e( 'Confirm new password', 'twilio-sms' ); ?></label><br />
            <input type="password" name="pass2" id="pass2" class="input" size="20" value="" autocomplete="off" />
        </p><?php
    }

}