<?php
require_once 'config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once LIB_DIR . '/Database.php';
require_once LIB_DIR . '/FFTTApi.php';
require_once LIB_DIR . '/PointsCalculator.php';
require_once LIB_DIR . '/PlayerService.php';

$db = Database::getInstance();
$api = new FFTTApi(FFTT_APP_ID, FFTT_APP_KEY);
$playerService = new PlayerService($db, $api);

$action = $_GET['action'] ?? '';
$licence = $_GET['licence'] ?? ($_POST['licence'] ?? 'N/A');
file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] AJAX REQUEST: $action for licence $licence\n", FILE_APPEND);
header('Content-Type: application/json');

try {
    switch ($action) {
        case 'syncClub':
            // Synchronisation de tous les joueurs du club
            $xml = $api->getClubPlayers(FFTT_CLUB_ID);
            if (!$xml || !isset($xml->joueur)) throw new Exception('API FFTT indisponible');

            foreach ($xml->joueur as $j) {
                $licence = (string)$j->licence;
                // On insère ou met à jour le profil de base
                $stmt = $db->prepare("INSERT INTO players (licence, nom, prenom, points_officiel, points_mensuel) 
                                     VALUES (?, ?, ?, ?, ?) 
                                     ON DUPLICATE KEY UPDATE nom=VALUES(nom), prenom=VALUES(prenom)");
                $stmt->execute([$licence, (string)$j->nom, (string)$j->prenom, (float)$j->points, (float)$j->points]);
                
                // On synchronise les données détaillées
                $playerService->syncPlayerData($licence);
            }
            echo json_encode(['success' => true]);
            break;

        case 'sync_player':
        case 'syncPlayer':
        case 'fullSync':
            $licence = $_GET['licence'] ?? '';
            if (!$licence) throw new Exception('Licence manquante');
            $playerService->fullSync($licence, true);
            echo json_encode(['success' => true]);
            break;

        case 'syncLiveMatches':
            $licence = $_GET['licence'] ?? '';
            $found = $playerService->syncLiveMatches($licence);
            $playerService->updateVirtualPoints($licence);
            echo json_encode(['success' => true, 'found' => $found]);
            break;

        case 'getMatches':
            $licence = $_GET['licence'] ?? '';
            $stmt = $db->prepare("SELECT * FROM matches WHERE licence = ? ORDER BY date_match DESC, idpartie ASC, id ASC");
            $stmt->execute([$licence]);
            $results = $stmt->fetchAll();
            file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] AJAX RESPONSE: getMatches returned " . count($results) . " matches\n", FILE_APPEND);
            echo json_encode($results);
            break;

        case 'getHistory':
            $licence = $_GET['licence'] ?? '';
            $xml = $api->getPlayerHistory($licence);
            $history = [];
            if ($xml && isset($xml->histo)) {
                foreach ($xml->histo as $h) {
                    $history[] = [
                        'saison' => (string)$h->saison,
                        'phase' => (string)$h->phase,
                        'points' => (float)$h->point
                    ];
                }
            }
            echo json_encode($history);
            break;

        case 'uploadAvatar':
            $licence = $_POST['licence'] ?? '';
            if (!$licence) throw new Exception('Licence manquante');
            
            if (!isset($_FILES['avatar'])) throw new Exception('Aucun fichier reçu');
            
            $file = $_FILES['avatar'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (!in_array($ext, $allowed)) throw new Exception('Format non supporté');
            
            $filename = $licence . '_' . time() . '.' . $ext;
            $target = __DIR__ . '/assets/avatars/' . $filename;
            
            // Supprimer l'ancien avatar s'il existe
            $stmt = $db->prepare("SELECT avatar FROM players WHERE licence = ?");
            $stmt->execute([$licence]);
            $old = $stmt->fetchColumn();
            if ($old && file_exists(__DIR__ . '/assets/avatars/' . $old)) {
                @unlink(__DIR__ . '/assets/avatars/' . $old);
            }
            
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $db->prepare("UPDATE players SET avatar = ?, avatar_pos_x = 0, avatar_pos_y = 0, avatar_zoom = 1 WHERE licence = ?")->execute([$filename, $licence]);
                echo json_encode(['success' => true, 'avatar' => $filename]);
            } else {
                $error = error_get_last();
                throw new Exception('Erreur lors du déplacement du fichier. Code PHP: ' . $file['error'] . ' Msg: ' . ($error['message'] ?? 'inconnu'));
            }
            break;

        case 'updateAvatarPos':
            $licence = $_POST['licence'] ?? '';
            $x = intval($_POST['x'] ?? 50);
            $y = intval($_POST['y'] ?? 50);
            $zoom = floatval($_POST['zoom'] ?? 1.0);
            if (!$licence) throw new Exception('Licence manquante');
            
            $db->prepare("UPDATE players SET avatar_pos_x = ?, avatar_pos_y = ?, avatar_zoom = ? WHERE licence = ?")->execute([$x, $y, $zoom, $licence]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Action inconnue']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
