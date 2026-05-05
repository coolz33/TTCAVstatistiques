<?php
/**
 * Plugin Name: TTCAV Classement
 * Plugin URI:  https://ttcav.fr
 * Description: Affiche le classement des compétiteurs TTCAV. Les boutons Sync et Refresh ne sont visibles que pour les administrateurs WordPress.
 * Version:     1.1.0
 * Author:      TTCAV
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TTCAV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TTCAV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TTCAV_VERSION', '1.1.4' );

require_once TTCAV_PLUGIN_DIR . 'includes/class-ttcav-db.php';
require_once TTCAV_PLUGIN_DIR . 'includes/class-ttcav-ajax.php';

class WP_TTCAV {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'ttcav_classement', [ $this, 'render_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
        add_action( 'admin_menu',  [ $this, 'add_settings_page' ] );
        add_action( 'admin_init',  [ $this, 'register_settings' ] );

        $ajax = new TTCAV_Ajax();
        $ajax->register_hooks();
    }

    /** Shortcode [ttcav_classement club_id="09691058"] */
    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'club_id' => get_option( 'ttcav_club_id', '01690023' ),
        ], $atts );

        $club_id   = sanitize_text_field( $atts['club_id'] );
        $is_admin  = current_user_can( 'manage_options' );
        $ttcav_url = ''; // Inutile : le plugin est autonome

        try {
            $db      = new TTCAV_DB();
            $players = $db->get_players( $club_id );
            $club    = $db->get_club( $club_id );
        } catch ( Exception $e ) {
            $settings_url = admin_url( 'options-general.php?page=ttcav-settings' );
            return '<div style="padding:20px;background:#fed7d7;color:#c53030;border-radius:8px;font-family:sans-serif;">
                        <strong>TTCAV :</strong> Impossible de se connecter à la base de données TTCAV.<br>
                        <a href="' . esc_url( $settings_url ) . '">→ Configurer le plugin</a>
                    </div>';
        }

        $club_name = $club ? $club['nom'] : 'Club ' . $club_id;

        // Enqueue assets now (shortcode is present)
        $this->enqueue_assets( $club_id, $ttcav_url );

        ob_start();
        include TTCAV_PLUGIN_DIR . 'templates/dashboard.tpl.php';
        return ob_get_clean();
    }

    public function maybe_enqueue_assets() {
        // Assets are enqueued on demand inside render_shortcode()
    }

    private function enqueue_assets( $club_id, $ttcav_url ) {
        wp_enqueue_style( 'bootstrap',     TTCAV_PLUGIN_URL . 'assets/css/bootstrap-scoped.css', [], TTCAV_VERSION );
        wp_enqueue_style( 'font-awesome',  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', [], '6.0.0' );
        wp_enqueue_style( 'ttcav-style',   TTCAV_PLUGIN_URL . 'assets/css/ttcav.css', [ 'bootstrap' ], TTCAV_VERSION );

        wp_enqueue_script( 'chartjs',       'https://cdn.jsdelivr.net/npm/chart.js', [], null, true );
        wp_enqueue_script( 'hammerjs',      'https://cdn.jsdelivr.net/npm/hammerjs@2.0.8', [], null, true );
        wp_enqueue_script( 'chartjs-zoom',  'https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom', [ 'chartjs', 'hammerjs' ], null, true );
        wp_enqueue_script( 'ttcav-dashboard', TTCAV_PLUGIN_URL . 'assets/js/ttcav.js', [ 'chartjs', 'chartjs-zoom' ], TTCAV_VERSION, true );

        wp_localize_script( 'ttcav-dashboard', 'ttcav', [
            'ajax_url'  => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'ttcav_nonce' ),
            'is_admin'  => current_user_can( 'manage_options' ),
            'club_id'   => $club_id,
        ] );
    }

    /* ── Réglages WP Admin ──────────────────────────────────────── */

    public function add_settings_page() {
        add_options_page(
            'TTCAV — Réglages',
            'TTCAV',
            'manage_options',
            'ttcav-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings() {
        $fields = [ 'ttcav_db_host', 'ttcav_db_name', 'ttcav_db_user', 'ttcav_db_pass', 'ttcav_club_id', 'ttcav_fftt_id', 'ttcav_fftt_key' ];
        foreach ( $fields as $f ) {
            register_setting( 'ttcav_settings_group', $f );
        }
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>⚙️ TTCAV Classement — Réglages</h1>

            <?php if ( isset( $_GET['settings-updated'] ) ) : ?>
                <div class="notice notice-success"><p>Réglages enregistrés.</p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'ttcav_settings_group' ); ?>
                <table class="form-table">
                    <tr><th scope="row">Hôte MySQL</th>
                        <td><input type="text" name="ttcav_db_host" value="<?php echo esc_attr( get_option( 'ttcav_db_host', 'localhost' ) ); ?>" class="regular-text"></td></tr>
                    <tr><th scope="row">Base de données</th>
                        <td><input type="text" name="ttcav_db_name" value="<?php echo esc_attr( get_option( 'ttcav_db_name', 'fftt_manager' ) ); ?>" class="regular-text"></td></tr>
                    <tr><th scope="row">Utilisateur MySQL</th>
                        <td><input type="text" name="ttcav_db_user" value="<?php echo esc_attr( get_option( 'ttcav_db_user', '' ) ); ?>" class="regular-text"></td></tr>
                    <tr><th scope="row">Mot de passe MySQL</th>
                        <td><input type="password" name="ttcav_db_pass" value="<?php echo esc_attr( get_option( 'ttcav_db_pass', '' ) ); ?>" class="regular-text"></td></tr>
                    <tr><th scope="row">ID du Club FFTT</th>
                        <td><input type="text" name="ttcav_club_id" value="<?php echo esc_attr( get_option( 'ttcav_club_id', '01690023' ) ); ?>" class="regular-text">
                        <p class="description">Ex : 01690023</p></td></tr>
                    <tr><th scope="row">FFTT App ID</th>
                        <td><input type="text" name="ttcav_fftt_id" value="<?php echo esc_attr( get_option( 'ttcav_fftt_id', '' ) ); ?>" class="regular-text">
                        <p class="description">Identifiant de l'application FFTT (serie)</p></td></tr>
                    <tr><th scope="row">FFTT App Key</th>
                        <td><input type="password" name="ttcav_fftt_key" value="<?php echo esc_attr( get_option( 'ttcav_fftt_key', '' ) ); ?>" class="regular-text">
                        <p class="description">Clé secrète FFTT</p></td></tr>
                </table>
                <?php submit_button( 'Enregistrer les réglages' ); ?>
            </form>

            <hr>
            <h2>📋 Utilisation</h2>
            <p>Insérez ce shortcode dans n'importe quelle page :</p>
            <code style="background:#f0f0f0;padding:6px 12px;border-radius:4px;">[ttcav_classement]</code>
            <br><br>
            <p>Avec un club spécifique :</p>
            <code style="background:#f0f0f0;padding:6px 12px;border-radius:4px;">[ttcav_classement club_id="09691058"]</code>
        </div>
        <?php
    }
}

WP_TTCAV::get_instance();
