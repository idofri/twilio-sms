<?php
/**
 * Plugin Name: Twilio SMS Verification
 * Description: Use Twilio (and Authy) to validate new registrations using SMS verification.
 * Version:     1.0.1
 * Author:      Ido Friedlander
 * Author URI:  https://github.com/idofri
 * Text Domain: twilio-sms
 */

require __DIR__ . '/vendor/autoload.php';

class TwilioSMS {
    
    public $version = '1.0.1';
    
    public function __construct() {
        $this->initSettings();
        $this->initRegistration();
    }
    
    public function initSettings() {
        return new Twilio\Verify\Settings();
    }
    
    public function initRegistration() {
        return new Twilio\Verify\Registration();
    }
    
}

new TwilioSMS;