<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( class_exists( 'PlayerService' ) ) return;

require_once __DIR__ . '/PointsCalculator.php';

/**
 * PlayerService adapté pour WordPress.
 * Identique à l'original, avec :
 *  - FFTT_CLUB_ID remplacé par get_option('ttcav_club_id')
 *  - Les logs écrits dans error_log WP (pas de fichier local)
 */
class PlayerService {
    private $db;
    private $api;
    private static $advPointsCache = [];

    public function __construct( $db, $api ) {
        $this->db  = $db;
        $this->api = $api;
    }

    public function fullSync( $licence, $force = false ) {
        $playerXml = $this->syncPlayerData( $licence, $force );
        $this->syncOfficialMatches( $licence, $playerXml, $force );
        $this->syncLiveMatches( $licence, $playerXml, $force );
        $this->updateVirtualPoints( $licence );
    }

    public function updateVirtualPoints( $licence ) {
        $player = $this->getPlayerFromDb( $licence );
        if ( ! $player ) return;

        $stmtMatches = $this->db->prepare( "SELECT * FROM matches WHERE licence = ? AND is_validated = 0" );
        $stmtMatches->execute( [ $licence ] );
        $matches = $stmtMatches->fetchAll();

        foreach ( $matches as $m ) {
            $advPoints = $this->getLiveAdvPoints( $m['adversaire_nom'], null, (float) $m['adversaire_points'] );
            $gain      = PointsCalculator::calculateGain( $player['points_mensuel'], $advPoints, $m['victoire_defaite'] == 'V', (float) $m['coefficient'] );
            if ( abs( $gain - (float) $m['points_calcules'] ) > 0.001 || abs( $advPoints - (float) $m['adversaire_points'] ) > 0.001 ) {
                $this->db->prepare( "UPDATE matches SET adversaire_points = ?, points_calcules = ? WHERE id = ?" )
                         ->execute( [ $advPoints, $gain, $m['id'] ] );
            }
        }

        $currentMonthStart = date( 'Y-m-01' );
        $stmt = $this->db->prepare( "SELECT SUM(points_calcules) as total_gain FROM matches WHERE licence = ? AND is_validated = 0 AND date_match >= ?" );
        $stmt->execute( [ $licence, $currentMonthStart ] );
        $row        = $stmt->fetch();
        $totalGain  = $row['total_gain'] ? (float) $row['total_gain'] : 0;
        $pointsVirt = (float) $player['points_mensuel'] + $totalGain;

        $this->db->prepare( "UPDATE players SET points_virtuel = ? WHERE licence = ?" )
                 ->execute( [ $pointsVirt, $licence ] );
    }

    public function syncPlayerData( $licence, $force = false ) {
        $cacheTime  = $force ? 0 : 3600;
        $xmlBase    = $this->api->request( 'xml_licence_b.php', [ 'licence' => $licence ], $cacheTime );
        $xmlRanking = $this->api->getPlayerRanking( $licence );

        if ( ! $xmlBase || ! isset( $xmlBase->licence ) ) return false;

        $p              = $xmlBase->licence;
        $pointsOfficiel = (float) ( $p->point  ?? 0 );
        $pointsMensuel  = (float) ( $p->pointm ?? $pointsOfficiel );
        if ( $pointsMensuel == 0 ) $pointsMensuel = $pointsOfficiel;
        $pointsInitials = (float) ( $p->initm  ?? $p->apointm ?? $pointsOfficiel );
        $progMois       = (float) ( $p->mouv   ?? 0 );
        $progAnnee      = $pointsMensuel - $pointsInitials;
        $rangReg = $rangDep = $rangNat = 0;

        if ( $xmlRanking && isset( $xmlRanking->joueur ) ) {
            $rangReg = (int) ( $xmlRanking->joueur->rangreg ?? 0 );
            $rangDep = (int) ( $xmlRanking->joueur->rangdep ?? 0 );
            $rangNat = (int) ( $xmlRanking->joueur->clnat   ?? 0 );
        }

        $stmt = $this->db->prepare( "INSERT INTO players
            (licence, nom, prenom, numclub, cat, points_mensuel, points_officiel, points_initial, progression_mois, progression_annee, rang_regional, rang_departemental, rang_national, last_sync)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            nom=VALUES(nom), prenom=VALUES(prenom), numclub=VALUES(numclub), cat=VALUES(cat),
            points_mensuel=VALUES(points_mensuel), points_officiel=VALUES(points_officiel), points_initial=VALUES(points_initial),
            progression_mois=VALUES(progression_mois), progression_annee=VALUES(progression_annee),
            rang_regional=VALUES(rang_regional), rang_departemental=VALUES(rang_departemental), rang_national=VALUES(rang_national),
            last_sync=NOW()" );
        $stmt->execute( [
            $licence, (string) $p->nom, (string) $p->prenom, (string) $p->numclub, (string) $p->cat,
            $pointsMensuel, $pointsOfficiel, $pointsInitials,
            $progMois, $progAnnee, $rangReg, $rangDep, $rangNat,
        ] );
        return $xmlBase;
    }

    private function syncOfficialMatches( $licence, $playerXml, $force = false ) {
        $cacheTime  = $force ? 0 : 3600;
        $xmlMatches = $this->api->request( 'xml_partie_mysql.php', [ 'licence' => $licence ], $cacheTime );
        if ( ! $xmlMatches || ! isset( $xmlMatches->partie ) ) return 0;
        $found = 0;
        foreach ( $xmlMatches->partie as $m ) {
            $dateMatch = implode( '-', array_reverse( explode( '/', (string) $m->date ) ) );
            $advNom    = FFTTApi::cleanName( $m->advnompre );
            $epreuve   = PointsCalculator::getEpreuveName( $m->libelle, $m->epreuve, $m->codechamp );
            $idpartie  = isset( $m->idpartie ) ? (string) $m->idpartie : null;
            if ( $idpartie ) {
                $this->db->prepare( "UPDATE matches SET idpartie = ? WHERE licence = ? AND date_match = ? AND adversaire_nom = ? AND idpartie IS NULL LIMIT 1" )
                         ->execute( [ $idpartie, $licence, $dateMatch, $advNom ] );
            }
            $this->db->prepare( "INSERT INTO matches
                (licence, date_match, adversaire_nom, victoire_defaite, points_resultat, epreuve, adversaire_points, points_calcules, is_validated, coefficient, idpartie)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1.0, ?)
                ON DUPLICATE KEY UPDATE points_resultat=VALUES(points_resultat), points_calcules=VALUES(points_calcules), is_validated=1" )
                     ->execute( [ $licence, $dateMatch, $advNom, (string) $m->vd, (float) $m->pointres, $epreuve, (float) $m->advcla, (float) $m->pointres, $idpartie ] );
            $found++;
        }
        return $found;
    }

    private function syncLiveMatches( $licence, $playerXml, $force = false ) {
        $player = $this->getPlayerFromDb( $licence );
        if ( ! $player ) return 0;
        $found  = 0;
        $found += $this->scanTeamMatches( $player );
        $found += $this->scanIndividualMatches( $player, $force );
        return $found;
    }

    private function scanTeamMatches( $player ) {
        $licence   = $player['licence'];
        $found     = 0;
        $clubId    = get_option( 'ttcav_club_id', '09691058' );
        $xmlEquipes = $this->api->request( 'xml_equipe.php', [ 'numclu' => $clubId ] );
        if ( ! $xmlEquipes || ! isset( $xmlEquipes->equipe ) ) return 0;

        foreach ( $xmlEquipes->equipe as $eq ) {
            parse_str( (string) $eq->liendivision, $divParams );
            if ( ! isset( $divParams['cx_poule'] ) ) continue;
            $xmlResults = $this->api->request( 'xml_result_equ.php', [ 'cx_poule' => $divParams['cx_poule'], 'D1' => $divParams['D1'] ] );
            if ( ! $xmlResults || ! isset( $xmlResults->tour ) ) continue;

            foreach ( $xmlResults->tour as $tour ) {
                $dateTour = (string) $tour->datereelle;
                if ( ! $this->isDateRecent( $dateTour ) ) continue;
                parse_str( (string) $tour->lien, $rencParams );
                if ( ! isset( $rencParams['renc_id'] ) ) continue;
                $xmlSheet = $this->api->request( 'xml_chp_renc.php', $rencParams );
                if ( ! $xmlSheet || ! isset( $xmlSheet->partie ) ) continue;

                $resNode         = $xmlSheet->resultat ?? null;
                $clubA           = (string) ( $resNode->equa ?? '' );
                $clubB           = (string) ( $resNode->equb ?? '' );
                $isA_Home        = ( strpos( strtolower( $clubA ), 'villefranche' ) !== false || strpos( strtolower( $clubA ), 'ttcav' ) !== false );
                $opponentClubName = preg_replace( '/\s+\d+$/', '', $isA_Home ? $clubB : $clubA );

                foreach ( $xmlSheet->partie as $partie ) {
                    $res = $this->checkPlayerInMatch( $player, $partie, $opponentClubName );
                    if ( ! $res ) continue;
                    $advNom    = $res['advNom'];
                    $dateMatch = implode( '-', array_reverse( explode( '/', $dateTour ) ) );
                    $existing  = $this->findExistingMatch( $licence, $dateMatch, $advNom );
                    if ( $existing && $existing['is_validated'] ) continue;
                    $advPoints = $this->getLiveAdvPoints( $advNom, $xmlSheet, 0, $opponentClubName );
                    $gain      = PointsCalculator::calculateGain( $player['points_mensuel'], $advPoints, $res['vd'] == 'V', 1.0 );
                    if ( $existing ) {
                        $this->db->prepare( "UPDATE matches SET adversaire_points=?, points_calcules=?, score_ja=?, score_jb=?, score_detail=?, coefficient=1.0 WHERE id=?" )
                                 ->execute( [ $advPoints, $gain, (int) $partie->scorea, (int) $partie->scoreb, (string) $partie->detail, $existing['id'] ] );
                    } else {
                        $this->db->prepare( "INSERT INTO matches (licence, date_match, adversaire_nom, victoire_defaite, points_resultat, epreuve, adversaire_points, score_ja, score_jb, score_detail, points_calcules, is_validated, coefficient) VALUES (?,?,?,?,0,'Championnat',?,?,?,?,?,0,1.0)" )
                                 ->execute( [ $licence, $dateMatch, $advNom, $res['vd'], $advPoints, (int) $partie->scorea, (int) $partie->scoreb, (string) $partie->detail, $gain ] );
                    }
                    $found++;
                }
            }
        }
        return $found;
    }

    private function scanIndividualMatches( $player, $force = false ) {
        $licence   = $player['licence'];
        $found     = 0;
        $cacheTime = $force ? 0 : 3600;
        $res       = $this->api->request( 'xml_partie.php', [ 'numlic' => $licence ], $cacheTime );
        if ( ! $res || ! isset( $res->partie ) ) return 0;
        $currentMonth = date( 'Y-m' );
        foreach ( $res->partie as $p ) {
            $dateRaw   = (string) ( $p->date ?? $p->datematch );
            $dateMatch = implode( '-', array_reverse( explode( '/', $dateRaw ) ) );
            if ( date( 'Y-m', strtotime( $dateMatch ) ) !== $currentMonth ) continue;
            $advNom   = FFTTApi::cleanName( $p->nom ?? $p->advnompre );
            $epreuve  = (string) ( $p->epreuve ?? $p->libelle );
            $idpartie = (string) $p->idpartie;
            $victoire = (string) $p->victoire;
            $existing = $this->findExistingMatch( $licence, $dateMatch, $advNom, $idpartie, $victoire );
            if ( $existing && $existing['is_validated'] ) continue;
            $advPoints = $this->getLiveAdvPoints( $advNom, null, (float) ( $p->classement ?? 0 ) );
            $coef      = isset( $p->coefchamp ) ? (float) $p->coefchamp : PointsCalculator::detectCoef( $epreuve );
            $gain      = PointsCalculator::calculateGain( $player['points_mensuel'], $advPoints, $victoire == 'V', $coef );
            if ( $existing ) {
                $this->db->prepare( "UPDATE matches SET idpartie=?, epreuve=?, adversaire_points=?, points_calcules=?, coefficient=? WHERE id=?" )
                         ->execute( [ $idpartie, $epreuve, $advPoints, $gain, $coef, $existing['id'] ] );
            } else {
                $this->db->prepare( "INSERT INTO matches (licence, date_match, adversaire_nom, victoire_defaite, points_resultat, epreuve, adversaire_points, points_calcules, is_validated, coefficient, idpartie) VALUES (?,?,?,?,0,?,?,?,0,?,?) ON DUPLICATE KEY UPDATE idpartie=VALUES(idpartie), points_calcules=VALUES(points_calcules)" )
                         ->execute( [ $licence, $dateMatch, $advNom, $victoire, $epreuve, $advPoints, $gain, $coef, $idpartie ] );
            }
            $found++;
        }
        return $found;
    }

    private function getPlayerFromDb( $licence ) {
        $stmt = $this->db->prepare( "SELECT * FROM players WHERE licence = ?" );
        $stmt->execute( [ $licence ] );
        return $stmt->fetch();
    }

    private function isDateRecent( $dateStr ) {
        $d = \DateTime::createFromFormat( 'd/m/Y', $dateStr );
        if ( ! $d ) return false;
        $monthsAgo = ( new \DateTime() )->modify( '-2 months' );
        return $d >= $monthsAgo;
    }

    private function findExistingMatch( $licence, $date, $advNom, $idpartie = null, $victoire = null ) {
        if ( ! empty( $idpartie ) ) {
            $stmt = $this->db->prepare( "SELECT * FROM matches WHERE licence = ? AND idpartie = ? LIMIT 1" );
            $stmt->execute( [ $licence, $idpartie ] );
            $found = $stmt->fetch();
            if ( $found ) return $found;
            $sql    = "SELECT * FROM matches WHERE licence = ? AND date_match = ? AND adversaire_nom = ? AND (idpartie IS NULL OR idpartie = '')";
            $params = [ $licence, $date, $advNom ];
            if ( ! empty( $victoire ) ) { $sql .= " AND victoire_defaite = ?"; $params[] = $victoire; }
            $stmt = $this->db->prepare( $sql . " LIMIT 1" );
            $stmt->execute( $params );
            return $stmt->fetch();
        }
        $sql    = "SELECT * FROM matches WHERE licence = ? AND date_match = ? AND adversaire_nom = ?";
        $params = [ $licence, $date, $advNom ];
        if ( ! empty( $victoire ) ) { $sql .= " AND victoire_defaite = ?"; $params[] = $victoire; }
        $stmt = $this->db->prepare( $sql . " LIMIT 1" );
        $stmt->execute( $params );
        return $stmt->fetch();
    }

    private function checkPlayerInMatch( $player, $partie, $opponentClubName = '' ) {
        $ja = strtoupper( (string) $partie->ja );
        $jb = strtoupper( (string) $partie->jb );
        foreach ( [ $ja, $jb ] as $j ) {
            if ( strpos( $j, 'DOUBLE' ) !== false || strpos( $j, ' ET ' ) !== false || strpos( $j, ' / ' ) !== false ) return false;
        }
        $nom    = $this->normalize( $player['nom'] );
        $prenom = $this->normalize( $player['prenom'] );
        $jaN   = $this->normalize( $ja );
        $jbN   = $this->normalize( $jb );
        if ( ( strpos( $jaN, $nom ) !== false && strpos( $jaN, $prenom ) !== false ) ||
             ( strpos( $jbN, $nom ) !== false && strpos( $jbN, $prenom ) !== false ) ) {
            $isA = ( strpos( $jaN, $nom ) !== false && strpos( $jaN, $prenom ) !== false );
            return [
                'vd'     => ( $isA && (int) $partie->scorea > (int) $partie->scoreb ) || ( ! $isA && (int) $partie->scoreb > (int) $partie->scorea ) ? 'V' : 'D',
                'advNom' => FFTTApi::cleanName( $isA ? $partie->jb : $partie->ja ),
            ];
        }
        return false;
    }

    private function normalize( $str ) {
        $str = str_replace( [ 'é','è','ê','ë','à','â','î','ï','ô','û' ], [ 'e','e','e','e','a','a','i','i','o','u' ], strtolower( $str ) );
        return strtoupper( preg_replace( '/[^a-zA-Z]/', '', $str ) );
    }

    public function getLiveAdvPoints( $advNom, $xmlSheet = null, $fallbackPoints = 0, $targetClubName = '' ) {
        if ( isset( self::$advPointsCache[ $advNom ] ) ) return self::$advPointsCache[ $advNom ];
        // Chercher dans la feuille de match
        if ( $xmlSheet && isset( $xmlSheet->joueur ) ) {
            $advNomNorm = $this->normalize( $advNom );
            foreach ( $xmlSheet->joueur as $j ) {
                foreach ( [ [ (string) $j->xja, (string) $j->xca ], [ (string) $j->xjb, (string) $j->xcb ] ] as [ $name, $pts ] ) {
                    if ( $this->normalize( $name ) === $advNomNorm && preg_match( '/(\d+)\s*pts/i', $pts, $m ) ) {
                        $fallbackPoints = (float) $m[1];
                        break 2;
                    }
                }
            }
        }
        $parts      = explode( ' ', trim( $advNom ) );
        $candidates = [];
        if ( count( $parts ) >= 2 ) {
            $nomsTry = [ strtoupper( $parts[0] ) ];
            if ( count( $parts ) >= 3 ) $nomsTry[] = strtoupper( $parts[0] . ' ' . $parts[1] );
            $prenomTry = end( $parts );
            foreach ( $nomsTry as $nTry ) {
                $res = $this->api->request( 'xml_liste_joueur_o.php', [ 'nom' => $nTry, 'prenom' => $prenomTry ], 3600 );
                if ( $res && isset( $res->joueur ) ) {
                    foreach ( $res->joueur as $j ) {
                        $pts = (float) ( $j->points ?? 0 );
                        if ( $pts > 0 ) $candidates[] = [ 'points' => $pts, 'club_nom' => (string) $j->nclub, 'licence' => (string) $j->licence, 'nom' => (string) $j->nom ];
                    }
                }
                if ( ! empty( $candidates ) ) break;
            }
        }
        if ( empty( $candidates ) ) return self::$advPointsCache[ $advNom ] = ( $fallbackPoints > 0 ? $fallbackPoints : 500 );
        if ( $fallbackPoints > 0 && $fallbackPoints < 100 ) $fallbackPoints *= 100;
        usort( $candidates, fn( $a, $b ) => abs( $a['points'] - $fallbackPoints ) <=> abs( $b['points'] - $fallbackPoints ) );
        $best = $candidates[0];
        if ( $fallbackPoints > 0 && abs( $best['points'] - $fallbackPoints ) > 150 ) {
            return self::$advPointsCache[ $advNom ] = $fallbackPoints;
        }
        $detail  = $this->api->request( 'xml_joueur.php', [ 'licence' => $best['licence'] ], 3600 );
        $pointM  = (float) ( $detail->joueur->pointm ?? $detail->joueur->point ?? $best['points'] );
        $valCla  = (float) ( $detail->joueur->valcla ?? $best['points'] );
        $final   = ( $fallbackPoints > 0 && (int) $fallbackPoints === (int) $valCla ) ? $fallbackPoints : $pointM;
        return self::$advPointsCache[ $advNom ] = $final;
    }
}
