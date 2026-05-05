<?php
// Copie directe — aucune dépendance externe
if ( class_exists( 'PointsCalculator' ) ) return;

class PointsCalculator {
    public static function calculateGain( $pointsPerso, $pointsAdv, $isVictoire, $coef = 1.0 ) {
        if ( $pointsPerso < 100 ) $pointsPerso *= 100;
        if ( $pointsAdv   < 100 ) $pointsAdv   *= 100;
        $pointsPerso = round( (float) $pointsPerso );
        $pointsAdv   = round( (float) $pointsAdv );
        $ecart    = $pointsAdv - $pointsPerso;
        $absEcart = abs( $ecart );
        if ( $isVictoire ) {
            if ( $ecart >= 0 ) {
                if ( $absEcart <= 24  ) $gain = 6;
                elseif ( $absEcart <= 49  ) $gain = 7;
                elseif ( $absEcart <= 99  ) $gain = 8;
                elseif ( $absEcart <= 149 ) $gain = 10;
                elseif ( $absEcart <= 199 ) $gain = 13;
                elseif ( $absEcart <= 299 ) $gain = 17;
                elseif ( $absEcart <= 399 ) $gain = 22;
                elseif ( $absEcart <= 499 ) $gain = 28;
                else $gain = 40;
            } else {
                if ( $absEcart <= 24  ) $gain = 6;
                elseif ( $absEcart <= 49  ) $gain = 5.5;
                elseif ( $absEcart <= 99  ) $gain = 5;
                elseif ( $absEcart <= 149 ) $gain = 4;
                elseif ( $absEcart <= 199 ) $gain = 3;
                elseif ( $absEcart <= 299 ) $gain = 2;
                elseif ( $absEcart <= 399 ) $gain = 1;
                elseif ( $absEcart <= 499 ) $gain = 0.5;
                else $gain = 0;
            }
        } else {
            if ( $ecart <= 0 ) {
                if ( $absEcart <= 24  ) $gain = -5;
                elseif ( $absEcart <= 49  ) $gain = -6;
                elseif ( $absEcart <= 99  ) $gain = -7;
                elseif ( $absEcart <= 149 ) $gain = -8;
                elseif ( $absEcart <= 199 ) $gain = -10;
                elseif ( $absEcart <= 299 ) $gain = -12.5;
                elseif ( $absEcart <= 399 ) $gain = -16;
                elseif ( $absEcart <= 499 ) $gain = -20;
                else $gain = -29;
            } else {
                if ( $absEcart <= 24  ) $gain = -5;
                elseif ( $absEcart <= 49  ) $gain = -4.5;
                elseif ( $absEcart <= 99  ) $gain = -4;
                elseif ( $absEcart <= 149 ) $gain = -3;
                elseif ( $absEcart <= 199 ) $gain = -2;
                elseif ( $absEcart <= 299 ) $gain = -1;
                elseif ( $absEcart <= 399 ) $gain = -0.5;
                else $gain = 0;
            }
        }
        return $gain * (float) $coef;
    }

    public static function detectCoef( $text ) {
        $t = strtolower( $text );
        if ( strpos( $t, 'criterium' ) !== false || strpos( $t, 'critéri' ) !== false ) return 1.5;
        if ( strpos( $t, 'jeunes' ) !== false ) return 0.5;
        if ( strpos( $t, 'championnat' ) !== false || strpos( $t, 'equipe' ) !== false ) return 1.0;
        if ( strpos( $t, 'tournoi' ) !== false ) return 0.5;
        $map = [ '1' => 1.0, 'I' => 1.5, 'J' => 0.5, 'C' => 0.5, '#' => 0.5 ];
        return $map[ $text ] ?? 1.0;
    }

    public static function getEpreuveName( $libelle, $epreuve, $code ) {
        foreach ( [ $libelle, $epreuve, $code ] as $c ) {
            $c = (string) $c;
            if ( ! empty( $c ) && $c !== '#' ) return $c;
        }
        return 'Compétition';
    }
}
