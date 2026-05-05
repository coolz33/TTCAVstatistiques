<?php
/**
 * Plugin Name: TTCAV TEST
 * Description: Version de test pour diagnostiquer le problème d'activation.
 * Version:     1.1.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_TTCAV_Test {
    public function __construct() {
        add_shortcode( 'ttcav_test', function() { return "Le plugin TTCAV TEST est actif !"; } );
    }
}
new WP_TTCAV_Test();
