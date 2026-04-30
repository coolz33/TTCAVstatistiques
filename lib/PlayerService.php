<?php
require_once __DIR__ . '/PointsCalculator.php';

/**
 * Service gérant la logique métier des joueurs et des matchs
 */
class PlayerService {
    private $db;
    private $api;
    private static $advPointsCache = [];

    public function __construct($db, $api) {
        $this->db = $db;
        $this->api = $api;
        ini_set('error_log', __DIR__ . '/../debug.log');
    }

    /**
     * Synchronisation complète d'un joueur (Profil + Matchs officiels + Matchs Live)
     */
    public function fullSync($licence, $force = false) {
        $playerXml = $this->syncPlayerData($licence, $force);
        $this->syncOfficialMatches($licence, $playerXml, $force);
        $this->syncLiveMatches($licence, $playerXml, $force);
        $this->updateVirtualPoints($licence);
    }

    /**
     * Calcule et met à jour les points virtuels d'un joueur
     */
    public function updateVirtualPoints($licence) {
        $player = $this->getPlayerFromDb($licence);
        if (!$player) return;

        // Recalcul systématique de tous les matchs non validés en base
        $stmtMatches = $this->db->prepare("SELECT * FROM matches WHERE licence = ? AND is_validated = 0");
        $stmtMatches->execute([$licence]);
        $matches = $stmtMatches->fetchAll();
        
        foreach ($matches as $m) {
            $advPoints = $this->getLiveAdvPoints($m['adversaire_nom'], null, (float)$m['adversaire_points']);
            $gain = PointsCalculator::calculateGain($player['points_mensuel'], $advPoints, $m['victoire_defaite'] == 'V', (float)$m['coefficient']);
            
            if (abs($gain - (float)$m['points_calcules']) > 0.001 || abs($advPoints - (float)$m['adversaire_points']) > 0.001) {
                $this->db->prepare("UPDATE matches SET adversaire_points = ?, points_calcules = ? WHERE id = ?")
                     ->execute([$advPoints, $gain, $m['id']]);
            }
        }

        $currentMonthStart = date('Y-m-01');
        $stmt = $this->db->prepare("SELECT SUM(points_calcules) as total_gain FROM matches WHERE licence = ? AND is_validated = 0 AND date_match >= ?");
        $stmt->execute([$licence, $currentMonthStart]);
        $row = $stmt->fetch();
        
        $totalGain = $row['total_gain'] ? (float)$row['total_gain'] : 0;
        $pointsVirtuel = (float)$player['points_mensuel'] + $totalGain;

        $this->db->prepare("UPDATE players SET points_virtuel = ? WHERE licence = ?")
            ->execute([$pointsVirtuel, $licence]);
    }

    /**
     * Synchronise les informations de base d'un joueur
     */
    public function syncPlayerData($licence, $force = false) {
        $cacheTime = $force ? 0 : 3600;
        $xmlBase = $this->api->request('xml_licence_b.php', ['licence' => $licence], $cacheTime);
        $xmlRanking = $this->api->getPlayerRanking($licence);
        
        if (!$xmlBase || !isset($xmlBase->licence)) return false;

        $p = $xmlBase->licence;
        $pointsOfficiel = (float)($p->point ?? 0);
        $pointsMensuel = (float)($p->pointm ?? $pointsOfficiel);
        if ($pointsMensuel == 0) $pointsMensuel = $pointsOfficiel;

        $pointsInitials = (float)($p->initm ?? $p->apointm ?? $pointsOfficiel);
        $progMois = (float)($p->mouv ?? 0);
        $progAnnee = $pointsMensuel - $pointsInitials;

        $rangReg = 0; $rangDep = 0; $rangNat = 0;
        if ($xmlRanking && isset($xmlRanking->joueur)) {
            $rangReg = (int)($xmlRanking->joueur->rangreg ?? 0);
            $rangDep = (int)($xmlRanking->joueur->rangdep ?? 0);
            $rangNat = (int)($xmlRanking->joueur->clnat ?? 0);
        }

        $stmt = $this->db->prepare("INSERT INTO players 
            (licence, nom, prenom, numclub, cat, points_mensuel, points_officiel, points_initial, progression_mois, progression_annee, rang_regional, rang_departemental, rang_national, last_sync)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            nom = VALUES(nom), prenom = VALUES(prenom), numclub = VALUES(numclub), cat = VALUES(cat),
            points_mensuel = VALUES(points_mensuel), points_officiel = VALUES(points_officiel), points_initial = VALUES(points_initial),
            progression_mois = VALUES(progression_mois), progression_annee = VALUES(progression_annee),
            rang_regional = VALUES(rang_regional), rang_departemental = VALUES(rang_departemental), rang_national = VALUES(rang_national),
            last_sync = NOW()");
        $stmt->execute([
            $licence, (string)$p->nom, (string)$p->prenom, (string)$p->numclub, (string)$p->cat,
            $pointsMensuel, $pointsOfficiel, $pointsInitials, 
            $progMois, $progAnnee, $rangReg, $rangDep, $rangNat
        ]);
        return $xmlBase;
    }

    /**
     * Synchronise les matchs validés (Historique mensuel)
     */
    private function syncOfficialMatches($licence, $playerXml, $force = false) {
        $cacheTime = $force ? 0 : 3600;
        $xmlMatches = $this->api->request('xml_partie_mysql.php', ['licence' => $licence], $cacheTime);
        if (!$xmlMatches || !isset($xmlMatches->partie)) return 0;

        $found = 0;
        foreach ($xmlMatches->partie as $m) {
            $dateMatch = implode('-', array_reverse(explode('/', (string)$m->date)));
            $advNom = FFTTApi::cleanName($m->advnompre);
            $epreuve = PointsCalculator::getEpreuveName($m->libelle, $m->epreuve, $m->codechamp);
            $idpartie = isset($m->idpartie) ? (string)$m->idpartie : null;
            
            if ($idpartie) {
                // Tenter de lier ce match validé à un éventuel match existant sans idpartie (live ou ancien validé)
                $stmt = $this->db->prepare("UPDATE matches SET idpartie = ? WHERE licence = ? AND date_match = ? AND adversaire_nom = ? AND idpartie IS NULL LIMIT 1");
                $stmt->execute([$idpartie, $licence, $dateMatch, $advNom]);
            }

            $stmt = $this->db->prepare("INSERT INTO matches 
                (licence, date_match, adversaire_nom, victoire_defaite, points_resultat, epreuve, adversaire_points, points_calcules, is_validated, coefficient, idpartie) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1.0, ?)
                ON DUPLICATE KEY UPDATE 
                    points_resultat = VALUES(points_resultat), 
                    points_calcules = VALUES(points_calcules), 
                    is_validated = 1");
            $stmt->execute([$licence, $dateMatch, $advNom, (string)$m->vd, (float)$m->pointres, $epreuve, (float)$m->advcla, (float)$m->pointres, $idpartie]);
            $found++;
        }
        return $found;
    }

    /**
     * Scan les matchs récents non encore validés officiellement
     */
    private function syncLiveMatches($licence, $playerXml, $force = false) {
        $player = $this->getPlayerFromDb($licence);
        if (!$player) {
            file_put_contents('debug_match.log', "PLAYER NOT FOUND IN DB: licence=$licence\n", FILE_APPEND);
            return 0;
        }
        file_put_contents('debug_match.log', "SYNCING PLAYER: " . print_r($player, true) . "\n", FILE_APPEND);

        $found = 0;
        $found += $this->scanTeamMatches($player);
        $found += $this->scanIndividualMatches($player, $force);
        return $found;
    }

    /**
     * Scan des feuilles de rencontres par équipe
     */
    private function scanTeamMatches($player) {
        $licence = $player['licence'];
        $found = 0;
        $xmlEquipes = $this->api->request('xml_equipe.php', ['numclu' => FFTT_CLUB_ID]);
        
        if (!$xmlEquipes || !isset($xmlEquipes->equipe)) return 0;

        foreach ($xmlEquipes->equipe as $eq) {
            parse_str((string)$eq->liendivision, $divParams);
            if (!isset($divParams['cx_poule'])) continue;

            $xmlResults = $this->api->request('xml_result_equ.php', ['cx_poule' => $divParams['cx_poule'], 'D1' => $divParams['D1']]);
            if (!$xmlResults || !isset($xmlResults->tour)) continue;

            foreach ($xmlResults->tour as $tour) {
                $dateTour = (string)$tour->datereelle;
                if (!$this->isDateRecent($dateTour)) continue;

                parse_str((string)$tour->lien, $rencParams);
                if (!isset($rencParams['renc_id'])) continue;

                $xmlSheet = $this->api->request('xml_chp_renc.php', $rencParams);
                if (!$xmlSheet || !isset($xmlSheet->partie)) continue;

                // Identifier le club adverse
                $resNode = $xmlSheet->resultat ?? null;
                $clubA = (string)($resNode->equa ?? '');
                $clubB = (string)($resNode->equb ?? '');
                $this->log("Encounter: $clubA vs $clubB");
                
                // On cherche quel club n'est pas celui de Villefranche
                $opponentClubName = '';
                $isA_Villefranche = (strpos(strtolower($clubA), 'villefranche') !== false || strpos(strtolower($clubA), 'ttcav') !== false || strpos(strtolower($clubA), 'saone') !== false);
                
                if ($isA_Villefranche) {
                    $opponentClubName = $clubB;
                } else {
                    $opponentClubName = $clubA;
                }
                // Nettoyer le nom du club (enlever le numéro d'équipe à la fin)
                $opponentClubName = preg_replace('/\s+\d+$/', '', $opponentClubName);

                foreach ($xmlSheet->partie as $partie) {
                    $res = $this->checkPlayerInMatch($player, $partie, $opponentClubName);
                    if (!$res) continue;

                    $advNom = $res['advNom'];
                    $dateMatch = implode('-', array_reverse(explode('/', $dateTour)));

                    // Recherche d'un match existant (validé ou live)
                    $existing = $this->findExistingMatch($licence, $dateMatch, $advNom);
                    
                    if ($existing && $existing['is_validated']) continue;

                    $advPoints = $this->getLiveAdvPoints($advNom, $xmlSheet, 0, $opponentClubName);
                    $gain = PointsCalculator::calculateGain($player['points_mensuel'], $advPoints, $res['vd'] == 'V', 1.0);

                    if ($existing) {
                        // Mise à jour du match live existant (on recalcule TOUJOURS les points avec la nouvelle table)
                        $this->db->prepare("UPDATE matches SET 
                            adversaire_points = ?, points_calcules = ?, 
                            score_ja = ?, score_jb = ?, score_detail = ?, 
                            coefficient = 1.0 
                            WHERE id = ?")
                            ->execute([$advPoints, $gain, (int)$partie->scorea, (int)$partie->scoreb, (string)$partie->detail, $existing['id']]);
                    } else {
                        // Insertion d'un nouveau match
                        $this->db->prepare("INSERT INTO matches 
                            (licence, date_match, adversaire_nom, victoire_defaite, points_resultat, epreuve, adversaire_points, score_ja, score_jb, score_detail, points_calcules, is_validated, coefficient) 
                            VALUES (?, ?, ?, ?, 0, 'Championnat', ?, ?, ?, ?, ?, 0, 1.0)")
                            ->execute([$licence, $dateMatch, $advNom, $res['vd'], $advPoints, (int)$partie->scorea, (int)$partie->scoreb, (string)$partie->detail, $gain]);
                    }
                    $found++;
                }
            }
        }
        return $found;
    }

    /**
     * Scan des tournois et critériums (xml_partie)
     */
    private function scanIndividualMatches($player, $force = false) {
        $licence = $player['licence'];
        $found = 0;
        $cacheTime = $force ? 0 : 3600;
        $res = $this->api->request('xml_partie.php', ['numlic' => $licence], $cacheTime);
        if (!$res || !isset($res->partie)) return 0;

        $currentMonth = date('Y-m');
        foreach ($res->partie as $p) {
            $dateRaw = (string)($p->date ?? $p->datematch);
            $dateMatch = implode('-', array_reverse(explode('/', $dateRaw)));
            $isApril = (date('Y-m', strtotime($dateMatch)) === $currentMonth);
            
            file_put_contents('debug_match.log', "INDIVIDUAL MATCH: date=$dateMatch, april=" . ($isApril ? 'YES' : 'NO') . ", adv=" . ($p->nom ?? $p->advnompre) . "\n", FILE_APPEND);
            
            if (!$isApril) continue;

            $advNom = FFTTApi::cleanName($p->nom ?? $p->advnompre);
            $epreuve = (string)($p->epreuve ?? $p->libelle);
            
            // Recherche d'un match existant
            $idpartie = (string)$p->idpartie;
            $victoire = (string)$p->victoire;
            $existing = $this->findExistingMatch($licence, $dateMatch, $advNom, $idpartie, $victoire);
            if ($existing && $existing['is_validated']) continue;

            $advPoints = $this->getLiveAdvPoints($advNom, null, (float)($p->classement ?? 0));
            $coef = isset($p->coefchamp) ? (float)$p->coefchamp : PointsCalculator::detectCoef($epreuve);
            $gain = PointsCalculator::calculateGain($player['points_mensuel'], $advPoints, (string)$p->victoire == 'V', $coef);

            if ($existing) {
                // Mise à jour (on recalcule TOUJOURS avec la nouvelle table, le coef détecté et les points frais de l'adversaire)
                $this->db->prepare("UPDATE matches SET 
                    idpartie = ?, epreuve = ?, adversaire_points = ?, points_calcules = ?, coefficient = ? 
                    WHERE id = ?")
                    ->execute([$idpartie, $epreuve, $advPoints, $gain, $coef, $existing['id']]);
            } else {
                $this->db->prepare("INSERT INTO matches 
                    (licence, date_match, adversaire_nom, victoire_defaite, points_resultat, epreuve, adversaire_points, points_calcules, is_validated, coefficient, idpartie) 
                    VALUES (?, ?, ?, ?, 0, ?, ?, ?, 0, ?, ?)
                    ON DUPLICATE KEY UPDATE idpartie=VALUES(idpartie), points_calcules=VALUES(points_calcules)")
                    ->execute([$licence, $dateMatch, $advNom, (string)$p->victoire, $epreuve, $advPoints, $gain, $coef, $idpartie]);
            }
            $found++;
        }
        return $found;
    }

    // --- Helpers Utilitaires ---

    private function getPlayerFromDb($licence) {
        $stmt = $this->db->prepare("SELECT * FROM players WHERE licence = ?");
        $stmt->execute([$licence]);
        return $stmt->fetch();
    }

    private function isDateRecent($dateStr) {
        return (strpos($dateStr, '/04/2026') !== false);
    }

    private function findExistingMatch($licence, $date, $advNom, $idpartie = null, $victoire = null) {
        // 1. Si on a un ID partie, c'est le critère absolu
        if (!empty($idpartie)) {
            $stmt = $this->db->prepare("SELECT * FROM matches WHERE licence = ? AND idpartie = ? LIMIT 1");
            $stmt->execute([$licence, $idpartie]);
            $found = $stmt->fetch();
            if ($found) return $found;
            
            // Si on ne l'a pas trouvé par ID, on peut chercher par date/nom MAIS seulement si le match en base n'a PAS d'ID
            // (pour permettre de lier un match synchronisé via feuille de match à un match individuel)
            $sql = "SELECT * FROM matches WHERE licence = ? AND date_match = ? AND adversaire_nom = ? AND (idpartie IS NULL OR idpartie = '')";
            $params = [$licence, $date, $advNom];
            if (!empty($victoire)) {
                $sql .= " AND victoire_defaite = ?";
                $params[] = $victoire;
            }
            $stmt = $this->db->prepare($sql . " LIMIT 1");
            $stmt->execute($params);
            return $stmt->fetch();
        }
        
        // 2. Sinon (pas d'ID fourni, ex: feuille de match par équipe), on cherche par nom/date/résultat
        $sql = "SELECT * FROM matches WHERE licence = ? AND date_match = ? AND adversaire_nom = ?";
        $params = [$licence, $date, $advNom];
        
        if (!empty($victoire)) {
            $sql .= " AND victoire_defaite = ?";
            $params[] = $victoire;
        }
        
        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    private function isMatchAlreadyValidated($licence, $date, $advNom, $victoire = null) {
        $match = $this->findExistingMatch($licence, $date, $advNom, null, $victoire);
        return $match && $match['is_validated'] == 1;
    }

    private function checkPlayerInMatch($player, $partie, $opponentClubName = '') {
        $ja = strtoupper((string)$partie->ja);
        $jb = strtoupper((string)$partie->jb);
        
        // Ignorer les matchs en double
        if (strpos($ja, 'DOUBLE') !== false || strpos($jb, 'DOUBLE') !== false ||
            strpos($ja, ' ET ') !== false || strpos($jb, ' ET ') !== false ||
            strpos($ja, ' / ') !== false || strpos($jb, ' / ') !== false) {
            return false;
        }

        $nom = $this->normalize(is_array($player) ? $player['nom'] : (string)$player->nom);
        $prenom = $this->normalize(is_array($player) ? $player['prenom'] : (string)$player->prenom);
        
        $ja_norm = $this->normalize($ja);
        $jb_norm = $this->normalize($jb);
        
        $matchA_nom = (strpos($ja_norm, $nom) !== false);
        $matchA_prenom = (strpos($ja_norm, $prenom) !== false);
        $matchB_nom = (strpos($jb_norm, $nom) !== false);
        $matchB_prenom = (strpos($jb_norm, $prenom) !== false);

        if (($matchA_nom && $matchA_prenom) || ($matchB_nom && $matchB_prenom)) {
            $isA = ($matchA_nom && $matchA_prenom);
            $isB = ($matchB_nom && $matchB_prenom);
            file_put_contents('debug_match.log', "MATCH SUCCESS: player=$nom $prenom | ja=$ja_norm | jb=$jb_norm\n", FILE_APPEND);
            return [
                'vd' => ($isA && (int)$partie->scorea > (int)$partie->scoreb) || ($isB && (int)$partie->scoreb > (int)$partie->scorea) ? 'V' : 'D',
                'advNom' => FFTTApi::cleanName($isA ? $partie->jb : $partie->ja)
            ];
        }
        
        // Log failures only for FLAMAND to avoid too many logs
        if ($nom === 'FLAMAND') {
             // file_put_contents('debug_match.log', "MATCH FAIL: player=$nom $prenom | ja=$ja_norm | jb=$jb_norm\n", FILE_APPEND);
        }
        return false;
    }

    private function normalize($str) {
        $str = str_replace(['Ã«', 'Ë', 'ë', 'é', 'è', 'ê', 'à', 'â', 'î', 'ï', 'ô', 'û'], ['E', 'E', 'E', 'E', 'E', 'E', 'A', 'A', 'I', 'I', 'O', 'U'], $str);
        return strtoupper(preg_replace('/[^a-zA-Z]/', '', $str));
    }

    private function log($msg) {
        error_log("[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", 3, __DIR__ . "/../debug.log");
    }

    public function getLiveAdvPoints($advNom, $xmlSheet = null, $fallbackPoints = 0, $targetClubName = '') {
        if (isset(self::$advPointsCache[$advNom])) return self::$advPointsCache[$advNom];

        // 1. Recherche prioritaire dans la feuille de rencontre si fournie pour définir une cible
        if ($xmlSheet && isset($xmlSheet->joueur)) {
            $advNomNorm = $this->normalize($advNom);
            foreach ($xmlSheet->joueur as $j) {
                // xja/xca pour équipe A, xjb/xcb pour équipe B
                $ja = (string)$j->xja;
                $jb = (string)$j->xjb;
                
                $isMatch = false;
                $pointsRaw = '';
                
                if ($this->normalize($ja) === $advNomNorm) {
                    $isMatch = true;
                    $pointsRaw = (string)$j->xca;
                } elseif ($this->normalize($jb) === $advNomNorm) {
                    $isMatch = true;
                    $pointsRaw = (string)$j->xcb;
                }
                
                if ($isMatch) {
                    // Extraire les points du format "M 1234pts" ou "N°123 - M 1234pts"
                    if (preg_match('/(\d+)\s*pts/i', $pointsRaw, $m)) {
                        $fallbackPoints = (float)$m[1];
                        $this->log("TARGET FOUND IN SHEET: $advNom -> $fallbackPoints pts");
                        break;
                    }
                }
            }
        }

        $this->log("--- LIVE SEARCH: $advNom (Target: $fallbackPoints, Club: $targetClubName) ---");
        
        $parts = explode(' ', trim($advNom));
        $count = count($parts);
        
        $nomsToTry = [];
        $prenomsToTry = [];

        if ($count >= 2) {
            $lastPart = end($parts);
            $firstPart = $parts[0];
            
            // Cas standard : Nom Prénom
            $nomsToTry[] = strtoupper($firstPart);
            $prenomsToTry[] = $lastPart;
            
            // Cas nom composé : Nom1 Nom2 Prénom
            if ($count >= 3) {
                $nomsToTry[] = strtoupper($firstPart . ' ' . $parts[1]);
                // Cas avec particule : DE CROZALS -> on cherche aussi juste CROZALS
                if (in_array(strtoupper($firstPart), ['DE', 'LE', 'LA', 'DU', 'DES', 'AUX'])) {
                    $nomsToTry[] = strtoupper($parts[1]);
                }
            }
        }
        
        $candidates = [];
        
        foreach ($nomsToTry as $nTry) {
            foreach ($prenomsToTry as $pTry) {
                // Variantes du prénom (accents, préfixes)
                $pVariants = [$pTry];
                $pNoAccent = str_replace(
                    ['é', 'è', 'ê', 'ë', 'à', 'â', 'î', 'ï', 'ô', 'û', 'ù', 'É', 'È', 'Ê', 'Ë', 'À', 'Â', 'Î', 'Ï', 'Ô', 'Û', 'Ù'], 
                    ['e', 'e', 'e', 'e', 'a', 'a', 'i', 'i', 'o', 'u', 'u', 'E', 'E', 'E', 'E', 'A', 'A', 'I', 'I', 'O', 'U', 'U'], 
                    $pTry
                );
                if ($pNoAccent !== $pTry) $pVariants[] = $pNoAccent;
                if (mb_strlen($pTry) >= 3) $pVariants[] = mb_substr($pTry, 0, 2);

                foreach ($pVariants as $vPrenom) {
                    $this->log("API QUERY: nom=$nTry, prenom=$vPrenom");
                    $res = $this->api->request('xml_liste_joueur_o.php', ['nom' => $nTry, 'prenom' => $vPrenom], 3600);
                    
                    if ($res && isset($res->joueur)) {
                        foreach ($res->joueur as $j) {
                            $licence = (string)$j->licence;
                            $jNom = strtoupper(trim((string)$j->nom));
                            $jPrenom = (string)$j->prenom;
                            
                            // On vérifie si le nom matche (souplesse pour noms composés)
                            $match = false;
                            if ($jNom === $nTry) $match = true;
                            elseif (strpos($advNom, $jNom) !== false) $match = true;
                            elseif (strpos($jNom, $parts[0]) !== false) $match = true;
                            
                            if (!$match) continue;

                            $pts = (float)($j->points ?? 0);
                            if ($pts > 0) {
                                $this->log("CANDIDATE: $jNom $jPrenom ($licence) : $pts pts (Club: " . (string)$j->nclub . ")");
                                $candidates[] = [
                                    'licence' => $licence,
                                    'nom' => $jNom,
                                    'prenom' => $jPrenom,
                                    'points' => $pts,
                                    'club_nom' => (string)$j->nclub
                                ];
                            }
                        }
                    }
                    
                    // Si on a trouvé au moins un candidat proche du target, on arrête TOUT
                    foreach ($candidates as $cand) {
                        if ($fallbackPoints > 0 && abs($cand['points'] - $fallbackPoints) < 150) {
                            break 3; // On sort des 3 boucles
                        }
                    }
                }
            }
        }
        
        if (empty($candidates)) {
            $this->log("RESULT: No candidates found. Using fallback: $fallbackPoints");
            return self::$advPointsCache[$advNom] = ($fallbackPoints > 0 ? $fallbackPoints : 500);
        }

        // Stratégie : prendre le joueur dont les points sont les plus proches du fallbackPoints
        // Normaliser fallbackPoints (si c'est un classement genre 13 au lieu de 1300)
        if ($fallbackPoints > 0 && $fallbackPoints < 100) {
            $fallbackPoints *= 100;
        }

        usort($candidates, function($a, $b) use ($fallbackPoints, $targetClubName) {
            // Priorité 1 : Le club correspond
            if (!empty($targetClubName)) {
                $targetWords = preg_split('/[\s\-\/]+/', strtolower($targetClubName), -1, PREG_SPLIT_NO_EMPTY);
                $targetWords = array_filter($targetWords, function($w) { return strlen($w) > 2; }); // Ignorer les petits mots
                
                $matchWords = function($clubNom, $words) {
                    $clubNom = strtolower($clubNom);
                    foreach ($words as $w) {
                        if (strpos($clubNom, $w) === false) return false;
                    }
                    return true;
                };

                $matchA = $matchWords($a['club_nom'], $targetWords);
                $matchB = $matchWords($b['club_nom'], $targetWords);
                
                if ($matchA && !$matchB) return -1;
                if (!$matchA && $matchB) return 1;
            }

            // Priorité 2 : Proximité des points
            if ($fallbackPoints > 0) {
                return abs($a['points'] - $fallbackPoints) <=> abs($b['points'] - $fallbackPoints);
            } else {
                return $b['points'] <=> $a['points']; // Si pas de cible, on prend le plus fort (souvent le bon match pour des noms communs)
            }
        });

        $best = $candidates[0];
        $diff = abs($best['points'] - $fallbackPoints);

        // Sécurité : si l'écart est trop grand (> 150 pts), on rejette le candidat
        if ($fallbackPoints > 0 && $diff > 150) {
            $this->log("RESULT: Best candidate {$best['nom']} ({$best['points']} pts) is too far from target ($fallbackPoints pts). Trusting target points.");
            return self::$advPointsCache[$advNom] = $fallbackPoints;
        }

        // On a le bon candidat ! On récupère maintenant ses points mensuels ultra-précis
        $this->log("RESULT: Picked {$best['nom']} {$best['prenom']} ({$best['licence']}) with {$best['points']} pts (Target: $fallbackPoints)");
        
        $finalDetail = $this->api->request('xml_joueur.php', ['licence' => $best['licence']], 3600);
        $pointM = (float)($finalDetail->joueur->pointm ?? $finalDetail->joueur->point ?? $best['points']);
        $valCla = (float)($finalDetail->joueur->valcla ?? $best['points']);
        
        // LOGIQUE CRITIQUE : Si le target (classement XML) correspond au classement officiel (valcla), 
        // on l'utilise car c'est probablement la base du calcul officiel du tournoi.
        // Sinon, on prend les points mensuels (plus dynamique) SAUF si le target est 0.
        if ($fallbackPoints > 0 && (int)$fallbackPoints === (int)$valCla) {
            $this->log("MATCHING OFFICIAL RATING: Using fallback $fallbackPoints instead of monthly $pointM");
            $finalPoints = $fallbackPoints;
        } else {
            $finalPoints = $pointM;
        }

        return self::$advPointsCache[$advNom] = $finalPoints;
    }
}
