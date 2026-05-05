<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Connexion PDO directe à la base de données TTCAV (NAS ou serveur distant)
 */
class TTCAV_DB_Ranking {

    public $pdo;

    public function __construct() {
        $host = get_option( 'TTCAV_DB_Ranking_host', 'localhost' );
        $name = get_option( 'TTCAV_DB_Ranking_name', 'fftt_manager' );
        $user = get_option( 'TTCAV_DB_Ranking_user', '' );
        $pass = get_option( 'TTCAV_DB_Ranking_pass', '' );

        if ( empty( $user ) ) {
            throw new Exception( 'Credentials MySQL non configurés. Allez dans Réglages → TTCAV.' );
        }

        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $this->pdo = new PDO( $dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ] );
    }

    /** Délègue les appels PDO (prepare, query…) directement */
    public function prepare( $sql ) {
        return $this->pdo->prepare( $sql );
    }

    public function query( $sql ) {
        return $this->pdo->query( $sql );
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /** Récupère tous les joueurs d'un club, triés par points virtuels */
    public function get_players( $club_id ) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM players WHERE numclub = ? ORDER BY points_virtuel DESC"
        );
        $stmt->execute( [ $club_id ] );
        $res = $stmt->fetchAll();
        
        // Debug si vide
        if ( empty( $res ) ) {
            // Tentative sans les zéros au début au cas où (ex: 1690023 au lieu de 01690023)
            $stmt = $this->pdo->prepare( "SELECT * FROM players WHERE CAST(numclub AS UNSIGNED) = ? ORDER BY points_virtuel DESC" );
            $stmt->execute( [ (int)$club_id ] );
            $res = $stmt->fetchAll();
        }
        
        return $res;
    }

    /** Récupère les infos d'un club */
    public function get_club( $club_id ) {
        $stmt = $this->pdo->prepare( "SELECT * FROM clubs WHERE numero = ? LIMIT 1" );
        $stmt->execute( [ $club_id ] );
        return $stmt->fetch();
    }

    /** Récupère les matchs d'un joueur triés par date */
    public function get_matches( $licence ) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM matches WHERE licence = ? ORDER BY date_match DESC, idpartie ASC, id ASC"
        );
        $stmt->execute( [ $licence ] );
        return $stmt->fetchAll();
    }
}
