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
$rank = 1;

$day = (int)date('d');
$isProvisionalPeriod = ($day >= 1 && $day <= 10);
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
    <div class="container">
        <header class="text-center mb-5">
            <h1 class="display-4 fw-bold">Les compétiteurs du <?php echo htmlspecialchars($clubName); ?> <small class="club-number">(<?php echo $clubId; ?>)</small></h1>
            <p class="lead text-muted">Consultez les classements, les progressions et l'historique détaillé de chaque joueur.</p>
        </header>

        <!-- TOOLBAR UNIFIÉE -->
        <div class="toolbar-pro" style="display: flex; align-items: center; gap: 15px; background: var(--bg-card); padding: 12px 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid var(--border-color);">
            <div class="search-wrapper-pro" style="flex-grow: 1; position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text" id="playerSearch" placeholder="Rechercher un joueur..." class="form-control">
                <i class="fas fa-times-circle search-clear hidden" id="searchClear" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); cursor: pointer; z-index: 10;"></i>
            </div>

            <div class="btn-group-pro" style="display: flex; gap: 8px;">
                <div class="btn-group" role="group" id="avatarSizeSelectorPC">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-size="sm"><i class="fas fa-th"></i></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-size="md"><i class="fas fa-th-large"></i></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-size="lg"><i class="fas fa-square"></i></button>
                </div>

                <button class="btn btn-outline-warning btn-sm" id="btnSyncAllPC">
                    <i class="fas fa-bolt me-1"></i> Sync Club
                </button>
                
                <button class="theme-toggle" id="themeToggleBtn" style="background: none; border: 1px solid var(--border-color); color: var(--accent-yellow); border-radius: 8px; padding: 5px 12px; cursor: pointer;">
                    <i class="fas fa-moon"></i>
                </button>

                <div class="stats-summary ms-3" style="color: var(--text-muted); font-weight: 500; display: flex; align-items: center;">
                    <span id="playerCount" class="fw-bold me-1" style="color: var(--text-main);"><?php echo count($players); ?></span> joueurs
                </div>
            </div>
        </div>

        <!-- PROGRESS BAR SYNC -->
        <div id="syncProgressContainer" class="hidden mb-4" style="background: var(--bg-card); padding: 15px; border-radius: 12px; border: 1px solid var(--accent-yellow);">
            <div class="d-flex justify-content-between mb-2">
                <span class="fw-bold" style="color: var(--accent-yellow);">Synchronisation du club en cours...</span>
                <span id="syncProgressText" class="text-muted">0/0</span>
            </div>
            <div class="progress" style="height: 10px; background: rgba(255,255,255,0.1);">
                <div id="syncProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning" role="progressbar" style="width: 0%"></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="players-table table table-dark table-hover">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th class="col-player" style="text-align: left;">JOUEUR <i class="fas fa-sort"></i></th>
                        <th class="col-stat">OFFICIEL <i class="fas fa-sort"></i></th>
                        <th class="col-stat">PH. 1 <i class="fas fa-sort"></i></th>
                        <th class="col-stat">PH. 2 <i class="fas fa-sort"></i></th>
                        <th class="col-stat">MENSUEL <i class="fas fa-sort"></i></th>
                        <th class="col-stat col-virtuel" style="color: var(--accent-yellow);">VIRTUEL <i class="fas fa-sort"></i></th>
                        <th class="col-stat">MOIS <i class="fas fa-sort"></i></th>
                        <th class="col-stat">ANNÉE <i class="fas fa-sort"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($players as $p): 
                        $safeMensuel = ($p['points_mensuel'] > 0) ? $p['points_mensuel'] : $p['points_officiel'];
                        $pointsPh1 = ($p['points_initial'] > 0) ? $p['points_initial'] : $p['points_officiel'];
                        $pointsPh2 = ($p['points_ph2'] > 0) ? $p['points_ph2'] : $p['points_officiel'];
                        $pointsVirtuel = ($p['points_virtuel'] > 0) ? $p['points_virtuel'] : $safeMensuel;
                        
                        $progMois = ($p['points_mensuel_precedent'] > 0) ? ($safeMensuel - $p['points_mensuel_precedent']) : 0;
                        $progAnnee = $pointsVirtuel - $pointsPh1;
                        $initials = strtoupper(substr($p['nom'], 0, 1) . substr($p['prenom'], 0, 1));
                    ?>
                    <tr class="player-row" data-licence="<?php echo $p['licence']; ?>">
                        <td class="text-center">
                            <span class="rank-number"><?php echo $rank++; ?></span>
                        </td>
                        <td>
                            <div class="player-info">
                                <div class="player-avatar avatar-md" 
                                     onclick="triggerAvatarUpload('<?php echo $p['licence']; ?>')"
                                     oncontextmenu="event.preventDefault(); event.stopPropagation(); const img = this.querySelector('img'); if(img) triggerAvatarRecenter(img, '<?php echo $p['licence']; ?>')">
                                    <?php if (!empty($p['avatar'])): ?>
                                        <div class="avatar-crop-container">
                                            <img src="https://ttcav2.coolz.fr/assets/avatars/<?php echo $p['avatar']; ?>" class="avatar-img" 
                                                 style="width: <?php echo ($p['avatar_zoom'] * 100); ?>%; left: <?php echo $p['avatar_pos_x']; ?>%; top: <?php echo $p['avatar_pos_y']; ?>%;">
                                        </div>
                                        <div class="recenter-hint"><i class="fas fa-arrows-alt"></i> Clic droit pour recentrer</div>
                                    <?php else: ?>
                                        <?php echo $initials; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="player-name-wrapper">
                                    <div class="player-name">
                                        <span class="text-uppercase"><?php echo htmlspecialchars($p['nom']); ?></span>
                                        <i class="fas fa-sync-alt refresh-icon" onclick="syncPlayer('<?php echo $p['licence']; ?>')"></i>
                                    </div>
                                    <div style="color: rgba(255,255,255,0.7); font-size: 0.85rem;"><?php echo htmlspecialchars($p['prenom']); ?></div>
                                    <div class="player-licence"><?php echo $p['licence']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center val-official"><?php echo number_format($p['points_officiel'], 1, '.', ''); ?></td>
                        <td class="text-center"><?php echo number_format($pointsPh1, 1, '.', ''); ?></td>
                        <td class="text-center"><?php echo number_format($pointsPh2, 1, '.', ''); ?></td>
                        <td class="text-center val-monthly">
                            <span class="main-val"><?php echo number_format($safeMensuel, 1, '.', ''); ?></span>
                            <?php if ($safeMensuel - $p['points_officiel'] != 0): ?>
                            <small class="prog-val <?php echo ($safeMensuel - $p['points_officiel']) > 0 ? 'plus' : 'minus'; ?>">
                                <?php echo ($safeMensuel - $p['points_officiel']) > 0 ? '+' : ''; ?><?php echo number_format($safeMensuel - $p['points_officiel'], 1, '.', ''); ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center col-virtuel-big">
                            <span class="main-val"><?php echo number_format($pointsVirtuel, 1, '.', ''); ?></span>
                            <?php if ($pointsVirtuel - $p['points_officiel'] != 0): ?>
                            <small class="prog-val <?php echo ($pointsVirtuel - $p['points_officiel']) > 0 ? 'plus' : 'minus'; ?>" style="font-size: 0.7rem; font-weight: 700; margin-left: 4px;">
                                <?php echo ($pointsVirtuel - $p['points_officiel']) > 0 ? '+' : ''; ?><?php echo number_format($pointsVirtuel - $p['points_officiel'], 1, '.', ''); ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center prog-highlight <?php echo $progMois > 0 ? 'plus' : ($progMois < 0 ? 'minus' : ''); ?>">
                            <?php echo ($progMois > 0 ? '+' : '') . number_format($progMois, 1, '.', ''); ?>
                        </td>
                        <td class="text-center prog-highlight <?php echo $progAnnee > 0 ? 'plus' : ($progAnnee < 0 ? 'minus' : ''); ?>">
                            <?php echo ($progAnnee > 0 ? '+' : '') . number_format($progAnnee, 1, '.', ''); ?>
                        </td>
                    </tr>
                    <tr class="details-row hidden-row" id="details-<?php echo $p['licence']; ?>">
                        <td colspan="9">
                            <div class="details-content">
                                <div class="details-header">
                                    <h3>HISTORIQUE & MATCHS</h3>
                                    <button class="btn-close-details" onclick="toggleDetails('<?php echo $p['licence']; ?>')">FERMER</button>
                                </div>
                                <div class="chart-container">
                                    <canvas id="chart-<?php echo $p['licence']; ?>"></canvas>
                                </div>
                                <div class="matches-container mt-4"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Éléments masqués pour le fonctionnement JS -->
    <input type="file" id="avatarInput" style="display: none;" accept="image/*">
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
