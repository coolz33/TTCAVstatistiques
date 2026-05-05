<?php
/**
 * Interface avec l'API officielle de la FFTT
 * Copie adaptée pour le plugin WordPress TTCAV (chemin de cache relatif au plugin)
 */
class FFTTApi {
    private $id;
    private $password;
    private $baseUrl  = 'http://www.fftt.com/mobile/pxml/';
    private $cacheDir;

    public function __construct( $id, $password ) {
        $this->id       = $id;
        $this->password = md5( $password );
        // Cache dans le répertoire uploads de WordPress
        $upload         = wp_upload_dir();
        $this->cacheDir = $upload['basedir'] . '/ttcav-cache/';
        if ( ! is_dir( $this->cacheDir ) ) {
            wp_mkdir_p( $this->cacheDir );
        }
    }

    public function request( $script, $params = [], $cacheTime = 3600 ) {
        $cacheKey  = $script . '_' . md5( json_encode( $params ) );
        $cacheFile = $this->cacheDir . $cacheKey . '.xml';

        if ( $cacheTime > 0 && file_exists( $cacheFile ) && ( time() - filemtime( $cacheFile ) < $cacheTime ) ) {
            return $this->parseXml( file_get_contents( $cacheFile ) );
        }

        return $this->requestRaw( $script, $params );
    }

    private function requestRaw( $script, $params = [] ) {
        $time = round( microtime( true ) * 1000 );
        $tmc  = hash_hmac( 'sha1', $time, $this->password );

        $url = $this->baseUrl . $script . '?serie=' . $this->id . '&tm=' . $time . '&tmc=' . $tmc . '&id=' . $this->id;
        foreach ( $params as $key => $value ) {
            $url .= '&' . $key . '=' . urlencode( $value );
        }

        $response = wp_remote_get( $url, [ 'timeout' => 15, 'sslverify' => false ] );
        if ( is_wp_error( $response ) ) return null;

        $body = wp_remote_retrieve_body( $response );
        if ( $body ) {
            $cacheKey  = $script . '_' . md5( json_encode( $params ) );
            $cacheFile = $this->cacheDir . $cacheKey . '.xml';
            file_put_contents( $cacheFile, $body );
        }

        return $this->parseXml( $body );
    }

    private function parseXml( $xmlString ) {
        if ( ! $xmlString || trim( $xmlString ) === '' ) return null;
        $encoding = mb_detect_encoding( $xmlString, [ 'UTF-8', 'ISO-8859-1' ], true );
        if ( $encoding !== 'UTF-8' ) {
            $xmlString = mb_convert_encoding( $xmlString, 'UTF-8', $encoding );
        }
        $xmlString = preg_replace( '/encoding="[^"]+"/', 'encoding="UTF-8"', $xmlString );
        try {
            return @simplexml_load_string( $xmlString );
        } catch ( Exception $e ) {
            return null;
        }
    }

    public static function cleanName( $name ) {
        if ( ! $name ) return '';
        $name = (string) $name;
        $name = str_replace( ',', '', $name );
        $name = preg_replace( '/\s+/', ' ', $name );
        $name = trim( $name );
        while ( strpos( $name, 'Ã' ) !== false && mb_check_encoding( $name, 'UTF-8' ) ) {
            $test = @mb_convert_encoding( $name, 'ISO-8859-1', 'UTF-8' );
            if ( $test === false || $test === $name ) break;
            $name = $test;
        }
        if ( ! mb_check_encoding( $name, 'UTF-8' ) ) {
            $name = mb_convert_encoding( $name, 'UTF-8', 'ISO-8859-1' );
        }
        return $name;
    }

    public function getClubPlayers( $clubNumber ) { return $this->request( 'xml_liste_joueur_o.php', [ 'club' => $clubNumber ] ); }
    public function getPlayerDetails( $licence )  { return $this->request( 'xml_licence_b.php',      [ 'licence' => $licence ] ); }
    public function getPlayerRanking( $licence )  { return $this->request( 'xml_joueur.php',         [ 'licence' => $licence ] ); }
    public function getPlayerMatches( $licence )  { return $this->request( 'xml_partie_mysql.php',   [ 'licence' => $licence ] ); }
    public function getPlayerHistory( $licence )  { return $this->request( 'xml_histo_classement.php', [ 'numlic' => $licence ] ); }
}
