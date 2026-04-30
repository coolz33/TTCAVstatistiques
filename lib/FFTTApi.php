<?php
/**
 * Interface avec l'API officielle de la FFTT
 */
class FFTTApi {
    private $id;
    private $password;
    private $baseUrl = 'http://www.fftt.com/mobile/pxml/';

    public function __construct($id, $password) {
        $this->id = $id;
        $this->password = md5($password);
    }

    /**
     * Effectue une requête avec cache (1 heure par défaut)
     */
    public function request($script, $params = [], $cacheTime = 3600) {
        $cacheKey = $script . '_' . md5(json_encode($params));
        $cacheFile = __DIR__ . '/../cache/' . $cacheKey . '.xml';

        if ($cacheTime > 0 && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
            return $this->parseXml(file_get_contents($cacheFile));
        }

        if ($cacheTime === 0 && file_exists($cacheFile)) {
            // Optionnel : on pourrait supprimer le fichier ici
        }

        $xml = $this->requestRaw($script, $params);
        if ($xml && $cacheTime > 0) {
            // Sauvegarde brute avant parsing
            // Note: On pourrait sauvegarder le résultat de requestRaw
        }
        return $xml;
    }

    private function requestRaw($script, $params = []) {
        $time = round(microtime(true) * 1000);
        $tmc = hash_hmac("sha1", $time, $this->password);
        
        $url = $this->baseUrl . $script . '?serie=' . $this->id . '&tm=' . $time . '&tmc=' . $tmc . '&id=' . $this->id;
        foreach ($params as $key => $value) {
            $url .= '&' . $key . '=' . urlencode($value);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $cacheKey = $script . '_' . md5(json_encode($params));
            $cacheFile = __DIR__ . '/../cache/' . $cacheKey . '.xml';
            file_put_contents($cacheFile, $response);
        }

        return $this->parseXml($response);
    }

    /**
     * Parse et convertit le XML en objet SimpleXML avec gestion d'encodage
     */
    private function parseXml($xmlString) {
        if (!$xmlString || trim($xmlString) === '') return null;
        
        // Conversion propre en UTF-8
        $encoding = mb_detect_encoding($xmlString, ['UTF-8', 'ISO-8859-1'], true);
        if ($encoding !== 'UTF-8') {
            $xmlString = mb_convert_encoding($xmlString, 'UTF-8', $encoding);
        }

        // Force le header XML en UTF-8
        $xmlString = preg_replace('/encoding="[^"]+"/', 'encoding="UTF-8"', $xmlString);

        try {
            return @simplexml_load_string($xmlString);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Nettoie un nom (accents, doublons d'encodage, virgules)
     */
    public static function cleanName($name) {
        if (!$name) return '';
        $name = (string)$name;
        
        $name = str_replace(',', '', $name); 
        $name = preg_replace('/\s+/', ' ', $name); 
        $name = trim($name);

        // Correction "multi-passes" pour les noms corrompus (RÃ©my)
        while (strpos($name, 'Ã') !== false && mb_check_encoding($name, 'UTF-8')) {
            $test = @mb_convert_encoding($name, 'ISO-8859-1', 'UTF-8');
            if ($test === false || $test === $name) break;
            $name = $test;
        }

        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1');
        }

        return $name;
    }

    // --- Méthodes de récupération des données ---

    public function getClubPlayers($clubNumber) {
        return $this->request('xml_liste_joueur_o.php', ['club' => $clubNumber]);
    }

    public function getPlayerDetails($licence) {
        return $this->request('xml_licence_b.php', ['licence' => $licence]);
    }

    public function getPlayerRanking($licence) {
        return $this->request('xml_joueur.php', ['licence' => $licence]);
    }

    public function getPlayerMatches($licence) {
        return $this->request('xml_partie_mysql.php', ['licence' => $licence]);
    }

    public function getPlayerHistory($licence) {
        return $this->request('xml_histo_classement.php', ['numlic' => $licence]);
    }
}
