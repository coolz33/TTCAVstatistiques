<?php
require_once __DIR__ . '/config.php';
require_once LIB_DIR . '/Database.php';

$db = Database::getInstance();

// Récupérer le club sélectionné
$clubId = $_GET['clubId'] ?? FFTT_CLUB_ID;

// Récupérer les infos du club
$stmt = $db->prepare("SELECT * FROM clubs WHERE numero = ?");
$stmt->execute([$clubId]);
$club = $stmt->fetch();

// Récupérer la liste des joueurs
$stmt = $db->prepare("SELECT * FROM players WHERE numclub = ? ORDER BY points_virtuel DESC");
$stmt->execute([$clubId]);
$players = $stmt->fetchAll();

$clubName = $club['nom'] ?? 'Club ' . $clubId;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Les compétiteurs du <?php echo htmlspecialchars($clubName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hammerjs@2.0.8"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom"></script>
</head>
<body class="dark-theme">
    <div class="container py-5">
        <header class="text-center mb-5">
            <h1 class="display-4 fw-bold">Les compétiteurs du <?php echo htmlspecialchars($clubName); ?> <small class="club-number">(<?php echo $clubId; ?>)</small></h1>
            <p class="subtitle lead">Consultez les classements, les progressions et l'historique détaillé de chaque joueur.</p>
        </header>

        <div class="search-bar-container mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-md">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="playerSearch" class="form-control" placeholder="Rechercher un joueur...">
                        <i class="fas fa-times-circle search-clear" id="clearSearch" style="display: none;"></i>
                    </div>
                </div>
                
                <div class="col-md-auto d-none d-md-flex align-items-center gap-2">
                    <div class="btn-group" role="group" id="avatarSizeSelectorPC">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-size="sm" title="Petites vignettes"><i class="fas fa-th"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-size="md" title="Moyennes vignettes"><i class="fas fa-th-large"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-size="lg" title="Grandes vignettes"><i class="fas fa-square"></i></button>
                    </div>

                    <button class="btn btn-outline-warning btn-sm" id="btnSyncAllPC">
                        <i class="fas fa-bolt me-1"></i> Sync Club
                    </button>
                    
                    <button class="btn btn-outline-light btn-sm" onclick="location.href='loading.php?clubId=<?php echo $clubId; ?>'" title="Rafraîchir la page">
                        <i class="fas fa-sync-alt"></i>
                    </button>

                    <button id="themeToggleBtn" title="Basculer thème clair / sombre">
                        <i class="fas fa-sun"></i>
                    </button>
                </div>

                <div class="col-md-auto text-center text-md-start">
                    <div class="stats-summary">
                        <span id="playerCount" class="fw-bold"><?php echo count($players); ?></span> joueurs
                    </div>
                </div>
            </div>

            <!-- Mobile Only Controls (Size & Sync) -->
            <div class="d-flex d-md-none justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="btn-group" role="group" id="avatarSizeSelectorMobile">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-size="sm"><i class="fas fa-th"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-size="md"><i class="fas fa-th-large"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-size="lg"><i class="fas fa-square"></i></button>
                    </div>
                    
                    <div class="sort-selector-wrapper">
                        <select id="mobileSortCol" class="form-select-sm">
                            <option value="2">Officiel</option>
                            <option value="3">Phase 1</option>
                            <option value="4">Phase 2</option>
                            <option value="5">Mensuel</option>
                            <option value="6" selected>Virtuel</option>
                            <option value="7">Prog. Mois</option>
                            <option value="8">Prog. Saison</option>
                        </select>
                        <select id="mobileSortDir" class="form-select-sm">
                            <option value="desc">DESC</option>
                            <option value="asc">ASC</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <button class="btn btn-outline-warning btn-sm" id="btnSyncAllMobile">
                        <i class="fas fa-bolt me-1"></i> Sync
                    </button>
                    <button class="btn btn-outline-light btn-sm" onclick="location.href='loading.php?clubId=<?php echo $clubId; ?>'">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <button id="themeToggleBtnMobile" title="Thème" onclick="toggleTheme()" style="background:var(--toggle-bg);border:1px solid var(--toggle-border);color:var(--toggle-icon);width:32px;height:32px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.9rem;transition:all 0.3s;">
                        <i class="fas fa-sun"></i>
                    </button>
                </div>
            </div>
        </div>

        <div id="syncProgressContainer" class="hidden mb-4 p-3 history-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold"><i class="fas fa-sync fa-spin me-2"></i> Synchronisation du club en cours...</span>
                <span id="syncProgressText" class="badge bg-warning text-dark">0/0</span>
            </div>
            <div class="progress" style="height: 10px; background: rgba(255,255,255,0.1);">
                <div id="syncProgressBar" class="progress-bar bg-warning progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="players-table table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th class="col-rank pc-only-cell">#</th>
                        <th class="col-player"><i class="fas fa-sort"></i> JOUEUR</th>
                        <th class="col-stat pc-only-cell"><i class="fas fa-sort"></i> OFFICIEL</th>
                        <th class="col-stat"><i class="fas fa-sort"></i> PH. 1</th>
                        <th class="col-stat"><i class="fas fa-sort"></i> PH. 2</th>
                        <th class="col-stat"><i class="fas fa-sort"></i> MENSUEL</th>
                        <th class="col-stat col-virtuel"><i class="fas fa-sort-down"></i> VIRTUEL</th>
                        <th class="col-stat"><i class="fas fa-calendar-day"></i> MOIS</th>
                        <th class="col-stat"><i class="fas fa-sort"></i> ANNÉE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach ($players as $p): 
                        // Sécurité : si mensuel est à 0, on prend l'officiel
                        $safeMensuel = ($p['points_mensuel'] > 0) ? $p['points_mensuel'] : $p['points_officiel'];
                        
                        $pointsPh1 = ($p['points_initial'] > 0) ? $p['points_initial'] : $p['points_officiel'];
                        $pointsPh2 = ($p['points_ph2'] > 0) ? $p['points_ph2'] : $p['points_officiel'];
                        
                        // Sécurité : si virtuel est à 0, on prend le mensuel (lui-même sécurisé)
                        $pointsVirtuel = ($p['points_virtuel'] > 0) ? $p['points_virtuel'] : $safeMensuel;
                        
                        // Indicateurs
                        $progMensuelOfficiel = $safeMensuel - $p['points_officiel'];
                        $progVirtuelPh2 = $pointsVirtuel - $pointsPh2;
                        $progMois = ($p['points_mensuel_precedent'] > 0) ? ($safeMensuel - $p['points_mensuel_precedent']) : 0;
                        $progAnnee = $pointsVirtuel - $pointsPh1;
                        
                        $initials = strtoupper(substr($p['nom'], 0, 1) . substr($p['prenom'], 0, 1));
                    ?>
                    <tr class="player-row" data-licence="<?php echo $p['licence']; ?>">
                        <td class="col-rank text-center pc-only-cell">
                            <span class="rank-number"><?php echo $rank++; ?></span>
                        </td>
                        <td class="col-player">
                            <div class="player-info">
                                <div class="mobile-rank-wrapper">
                                    <span class="rank-number"><?php echo ($rank-1); ?></span>
                                </div>
                                <div class="player-avatar avatar-md" data-licence="<?php echo $p['licence']; ?>" 
                                     onclick="event.stopPropagation(); triggerAvatarUpload('<?php echo $p['licence']; ?>')"
                                     oncontextmenu="event.preventDefault(); event.stopPropagation(); const img = this.querySelector('img'); if(img) triggerAvatarRecenter(img, '<?php echo $p['licence']; ?>')">
                                    <?php if (!empty($p['avatar'])): ?>
                                        <div class="avatar-crop-container">
                                            <img src="assets/avatars/<?php echo $p['avatar']; ?>" 
                                                 alt="" 
                                                 class="avatar-img"
                                                 style="width: <?php echo ($p['avatar_zoom'] ?? 1) * 100; ?>%; left: <?php echo $p['avatar_pos_x'] ?? 0; ?>%; top: <?php echo $p['avatar_pos_y'] ?? 0; ?>%;">
                                        </div>
                                        <div class="recenter-hint"><i class="fas fa-arrows-alt"></i> Clic droit pour recentrer</div>
                                        <div class="avatar-magnifier" onclick="event.stopPropagation(); showAvatarBig(this)"><i class="fas fa-search-plus"></i></div>
                                    <?php else: ?>
                                        <div class="avatar-initials"><?php echo $initials; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="player-name-wrapper">
                                    <span class="player-nom"><?php echo htmlspecialchars(strtoupper($p['nom'])); ?></span>
                                    <span class="player-prenom">
                                        <?php echo htmlspecialchars($p['prenom']); ?>
                                        <i class="fas fa-sync-alt refresh-icon-mini" onclick="event.stopPropagation(); syncSinglePlayer('<?php echo $p['licence']; ?>')" title="Actualiser"></i>
                                    </span>
                                    <span class="player-licence"><?php echo $p['licence']; ?></span>
                                </div>
                                <div class="mobile-actions-wrapper">
                                    <button class="btn-mobile-details" onclick="event.stopPropagation(); toggleDetails('<?php echo $p['licence']; ?>')">
                                        Détails
                                    </button>
                                </div>
                            </div>
                        </td>
                        <td class="col-stat val-official pc-only-cell" data-label="OFFICIEL"><?php echo number_format($p['points_officiel'], 1, '.', ''); ?></td>
                        <td class="col-stat" data-label="PH1"><?php echo number_format($pointsPh1, 1, '.', ''); ?></td>
                        <td class="col-stat" data-label="PH2"><?php echo number_format($pointsPh2, 1, '.', ''); ?></td>
                        <td class="col-stat val-monthly" data-label="MENSUEL">
                            <div class="d-flex align-items-center justify-content-center gap-1">
                                <span class="main-val"><?php echo number_format($safeMensuel, 1, '.', ''); ?></span>
                                <?php if ($progMensuelOfficiel != 0): ?>
                                    <span class="prog-val prog-badge <?php echo $progMensuelOfficiel > 0 ? 'plus' : 'minus'; ?>">
                                        <?php echo ($progMensuelOfficiel > 0 ? '+' : '') . number_format($progMensuelOfficiel, 1, '.', ''); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="col-stat col-virtuel-big" data-label="VIRTUEL">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <span class="main-val"><?php echo number_format($pointsVirtuel, 1, '.', ''); ?></span>
                                        <?php if ($progVirtuelPh2 != 0): ?>
                                            <span class="prog-val prog-badge <?php echo $progVirtuelPh2 > 0 ? 'plus' : 'minus'; ?>">
                                        <?php echo ($progVirtuelPh2 > 0 ? '+' : '') . number_format($progVirtuelPh2, 1, '.', ''); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="col-stat prog-highlight stat-center-bold <?php echo $progMois > 0 ? 'plus' : ($progMois < 0 ? 'minus' : ''); ?>" data-label="MOIS">
                            <?php echo ($progMois > 0 ? '+' : '') . number_format($progMois, 1, '.', ''); ?>
                        </td>
                        <td class="col-stat prog-highlight stat-center-bold <?php echo $progAnnee > 0 ? 'plus' : ($progAnnee < 0 ? 'minus' : ''); ?>" data-label="ANNÉE">
                            <?php echo ($progAnnee > 0 ? '+' : '') . number_format($progAnnee, 1, '.', ''); ?>
                        </td>
                    </tr>
                    <tr class="details-row hidden-row" id="details-<?php echo $p['licence']; ?>">
                        <td colspan="9">
                            <div class="details-content">
                                <div class="details-header">
                                    <h3>Historique & Matchs</h3>
                                    <div class="details-actions">
                                        <button class="btn-close-details" onclick="toggleDetails('<?php echo $p['licence']; ?>')">
                                            <i class="fas fa-times"></i> Fermer
                                        </button>
                                    </div>
                                </div>

                                <div class="chart-container mb-4">
                                    <canvas id="chart-<?php echo $p['licence']; ?>"></canvas>
                                </div>

                                <div class="matches-section mt-4">
                                    <h6 class="text-light text-uppercase small fw-bold mb-3" style="opacity: 0.6;">Derniers matchs</h6>
                                    <div class="matches-container">
                                        <div class="text-center p-3 text-muted">Chargement des matchs...</div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
    <input type="file" id="avatarInput" style="display: none;" accept="image/*">
</body>
</html>
