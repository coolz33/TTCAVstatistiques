<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once TTCAV_PLUGIN_DIR . 'lib/FFTTApi.php';
require_once TTCAV_PLUGIN_DIR . 'lib/PointsCalculator.php';
require_once TTCAV_PLUGIN_DIR . 'lib/PlayerService.php';

/**
 * Gestionnaire des actions AJAX WordPress pour le plugin TTCAV.
 * Tous les appels sont traités localement (DB NAS + API FFTT directe).
 */
class TTCAV_Ajax_Ranking {

    public function register_hooks() {
        // ── Lecture (visiteurs et connectés) ────────────────────────
        add_action( 'wp_ajax_ttcav_get_matches',         [ $this, 'get_matches' ] );
        add_action( 'wp_ajax_nopriv_ttcav_get_matches',  [ $this, 'get_matches' ] );

        add_action( 'wp_ajax_ttcav_get_history',         [ $this, 'get_history' ] );
        add_action( 'wp_ajax_nopriv_ttcav_get_history',  [ $this, 'get_history' ] );

        // ── Écriture (admins uniquement) ────────────────────────────
        add_action( 'wp_ajax_ttcav_sync_player', [ $this, 'sync_player' ] );
        add_action( 'wp_ajax_ttcav_sync_all',    [ $this, 'sync_all' ] );
    }

    // ── Sécurité ─────────────────────────────────────────────────────

    private function verify_nonce() {
        if ( ! check_ajax_referer( 'ttcav_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Nonce invalide.' ], 403 );
        }
    }

    private function require_admin() {
        $this->verify_nonce();
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Accès refusé.' ], 403 );
        }
    }

    private function make_db() {
        return new TTCAV_DB_Ranking();
    }

    private function make_api() {
        $id  = get_option( 'ttcav_fftt_id', '' );
        $key = get_option( 'ttcav_fftt_key', '' );
        return new FFTTApi( $id, $key );
    }

    // ── Handlers publics ──────────────────────────────────────────────

    /** Retourne les matchs d'un joueur depuis la DB */
    public function get_matches() {
        $this->verify_nonce();

        $licence = sanitize_text_field( $_GET['licence'] ?? '' );
        if ( ! $licence ) {
            wp_send_json_error( [ 'message' => 'Licence manquante.' ] );
        }

        try {
            $db      = $this->make_db();
            $matches = $db->get_matches( $licence );
            wp_send_json( $matches );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    /** Retourne l'historique de classement d'un joueur via API FFTT */
    public function get_history() {
        $this->verify_nonce();

        $licence = sanitize_text_field( $_GET['licence'] ?? '' );
        if ( ! $licence ) {
            wp_send_json_error( [ 'message' => 'Licence manquante.' ] );
        }

        try {
            $api = $this->make_api();
            $xml = $api->getPlayerHistory( $licence );

            $history = [];
            if ( $xml && isset( $xml->histo ) ) {
                foreach ( $xml->histo as $h ) {
                    $history[] = [
                        'saison' => (string) $h->saison,
                        'phase'  => (string) $h->phase,
                        'points' => (float)  $h->point,
                    ];
                }
            }
            wp_send_json( $history );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    // ── Handlers admin uniquement ─────────────────────────────────────

    /** Synchronise un joueur (profil + matchs + points virtuels) */
    public function sync_player() {
        $this->require_admin();

        $licence = sanitize_text_field( $_GET['licence'] ?? '' );
        if ( ! $licence ) {
            wp_send_json_error( [ 'message' => 'Licence manquante.' ] );
        }

        try {
            $db            = $this->make_db();
            $api           = $this->make_api();
            $playerService = new PlayerService( $db, $api );
            $playerService->fullSync( $licence, true );
            wp_send_json( [ 'success' => true ] );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    /** Synchronise un joueur via l'action batch (identique à sync_player) */
    public function sync_all() {
        $this->require_admin();

        $licence = sanitize_text_field( $_GET['licence'] ?? '' );
        if ( ! $licence ) {
            wp_send_json_error( [ 'message' => 'Licence manquante.' ] );
        }

        try {
            $db            = $this->make_db();
            $api           = $this->make_api();
            $playerService = new PlayerService( $db, $api );
            $playerService->fullSync( $licence, true );
            wp_send_json( [ 'success' => true ] );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }
}
