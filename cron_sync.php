<?php
/**
 * Script de synchronisation en tâche de fond (CRON)
 * Utilise la nouvelle architecture orientée objet pour une maintenance simplifiée.
 */

require_once __DIR__ . '/config.php';
require_once LIB_DIR . '/Database.php';
require_once LIB_DIR . '/FFTTApi.php';
require_once LIB_DIR . '/PointsCalculator.php';
require_once LIB_DIR . '/PlayerService.php';

set_time_limit(0); 

$db = Database::getInstance();
$api = new FFTTApi(FFTT_APP_ID, FFTT_APP_KEY);
$playerService = new PlayerService($db, $api);

$clubId = FFTT_CLUB_ID;

// 1. Récupérer tous les joueurs du club
$stmt = $db->prepare("SELECT licence FROM players ORDER BY last_sync ASC");
$stmt->execute();
$players = $stmt->fetchAll();

echo "Début de la synchronisation de fond pour " . count($players) . " joueurs...\n";

foreach ($players as $index => $p) {
    $licence = $p['licence'];
    echo "[" . ($index + 1) . "/" . count($players) . "] Mise à jour de $licence... ";
    
    try {
        // On utilise notre service centralisé pour la mise à jour complète
        $playerService->fullSync($licence);
        echo "OK\n";
    } catch (Exception $e) {
        echo "Erreur : " . $e->getMessage() . "\n";
    }

    // Petite pause pour respecter l'API FFTT
    usleep(500000); // 0.5 seconde
}

echo "Synchronisation de fond terminée.\n";
