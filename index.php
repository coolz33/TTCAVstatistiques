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

        <!-- TOOLBAR UNIFIÉE ET RESPONSIVE -->
        <div class="toolbar-pro">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <!-- Bloc Principal: Recherche + Compteur (Connectés) -->
                <div class="flex-grow-1 d-flex align-items-center" style="min-width: 300px;">
                    <div class="search-wrapper-pro flex-grow-1">
                        <i class="fas fa-search"></i>
                        <input type="text" id="playerSearch" placeholder="Rechercher un joueur..." class="form-control rounded-end-0">
                        <i class="fas fa-times-circle search-clear hidden" id="searchClear"></i>
                        <div class="player-count-badge d-md-none">
                            <span id="playerCountMobile"><?php echo count($players); ?></span>
                        </div>
                    </div>
                    
                    <div class="stats-summary d-none d-sm-flex align-items-center px-3 rounded-end border border-start-0" style="height: 38px; background: var(--bg-input);">
                        <span id="playerCount" class="fw-bold me-1"><?php echo count($players); ?></span> <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.6rem;">joueurs</span>
                    </div>
                </div>

                <!-- Bloc Filtres et Actions -->
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch d-flex align-items-center">
                        <input class="form-check-input" type="checkbox" id="showInactive" checked>
                        <label class="form-check-label ms-2 text-nowrap small text-muted fw-bold text-uppercase d-none d-sm-inline" for="showInactive" style="font-size: 0.65rem; letter-spacing: 0.05em;">Inactifs</label>
                    </div>

                    <div class="btn-group d-flex" role="group" id="avatarSizeSelector">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-size="sm" title="Petites vignettes"><i class="fas fa-th"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-size="md" title="Moyennes vignettes"><i class="fas fa-th-large"></i></button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-size="lg" title="Grandes vignettes"><i class="fas fa-square"></i></button>
                    </div>

                    <div class="vr d-none d-md-block" style="opacity: 0.1;"></div>

                    <button class="btn btn-outline-warning btn-sm" id="btnSyncAll">
                        <i class="fas fa-bolt me-1"></i> <span class="d-none d-sm-inline">Sync Club</span>
                    </button>
                    
                    <button class="theme-toggle" id="themeToggleBtn">
                        <i class="fas fa-moon"></i>
                    </button>
                </div>

                <!-- Tri Mobile Uniquement (Visible < 768px) -->
                <div class="col-12 d-md-none mt-2">
                    <div class="mobile-sort-container d-flex gap-2 align-items-center">
                        <span class="text-muted small fw-bold text-uppercase">Trier par:</span>
                        <select id="mobileSortCol" class="form-select form-select-sm flex-grow-1">
                            <option value="1">Nom</option>
                            <option value="2">Officiel</option>
                            <option value="3">Phase 1</option>
                            <option value="4">Phase 2</option>
                            <option value="5">Mensuel</option>
                            <option value="6" selected>Virtuel</option>
                            <option value="7">Mois</option>
                            <option value="8">Année</option>
                        </select>
                        <button id="mobileSortDir" class="btn btn-outline-secondary btn-sm" data-dir="desc">
                            <i class="fas fa-sort-amount-down"></i>
                        </button>
                    </div>
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
                        <th class="text-center">#</th>
                        <th class="col-player">JOUEUR <i class="fas fa-sort"></i></th>
                        <th class="text-start col-stat">OFFICIEL <i class="fas fa-sort-down"></i></th>
                        <th class="text-start col-stat">PH. 1 <i class="fas fa-sort"></i></th>
                        <th class="text-start col-stat">PH. 2 <i class="fas fa-sort"></i></th>
                        <th class="text-start col-stat">MENSUEL <i class="fas fa-sort"></i></th>
                        <th class="text-start col-stat">VIRTUEL <i class="fas fa-sort"></i></th>
                        <th class="text-start col-stat">MOIS <i class="fas fa-sort"></i></th>
                        <th class="text-start col-stat">ANNÉE <i class="fas fa-sort"></i></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $p): ?>
                    <?php 
                        $pointsPh1 = (float)(($p['points_initial'] ?? 0) ?: $p['points_officiel']);
                        $pointsPh2 = (float)(($p['points_ph2'] ?? 0) ?: $p['points_officiel']);
                        $pointsVirtuel = (float)$p['points_virtuel'];
                        $safeMensuel = (float)(($p['points_mensuel'] ?? 0) ?: $p['points_officiel']);
                        
                        $progMois = $pointsVirtuel - $safeMensuel;
                        $progAnnee = $pointsVirtuel - $pointsPh1;
                        $initials = strtoupper(substr($p['nom'], 0, 1) . substr($p['prenom'], 0, 1));
                    ?>
                    <tr class="player-row" data-licence="<?php echo $p['licence']; ?>" data-prog-annee="<?php echo $progAnnee; ?>">
                        <td class="text-center d-none d-md-table-cell">
                            <span class="rank-number"><?php echo $rank++; ?></span>
                        </td>
                        <td class="col-main-content">
                            <div class="player-info">
                                <div class="rank-number d-md-none me-1" style="min-width: 28px;"><?php echo ($rank - 1); ?></div>
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
                                    <div class="player-name" onclick="event.stopPropagation(); copyToClipboard('<?php echo $p['licence']; ?>', this)" title="Cliquer pour copier la licence">
                                        <span class="text-uppercase"><?php echo htmlspecialchars($p['nom']); ?></span>
                                        <i class="fas fa-sync-alt refresh-icon" onclick="event.stopPropagation(); syncPlayer('<?php echo $p['licence']; ?>')"></i>
                                    </div>
                                    <div class="player-prenom" onclick="event.stopPropagation(); copyToClipboard('<?php echo $p['licence']; ?>', this)" title="Cliquer pour copier la licence"><?php echo htmlspecialchars($p['prenom']); ?></div>
                                    <div class="player-licence" onclick="event.stopPropagation(); copyToClipboard('<?php echo $p['licence']; ?>', this)" title="Cliquer pour copier la licence">
                                        <?php echo $p['licence']; ?>
                                    </div>
                                </div>
                                <!-- BOUTON DÉTAILS -->
                                <button class="btn-row-details" onclick="event.stopPropagation(); toggleDetails('<?php echo $p['licence']; ?>')">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>

                            <!-- GRILLE STATS MOBILE -->
                            <div class="mobile-stats-grid d-md-none">
                                <div class="row g-0 stat-row">
                                    <div class="col-4 stat-item">
                                        <div class="stat-label">PHASE 1</div>
                                        <div class="stat-value"><?php echo number_format($pointsPh1, 1, '.', ''); ?></div>
                                    </div>
                                    <div class="col-4 stat-item">
                                        <div class="stat-label">MENSUEL</div>
                                        <div class="stat-value val-monthly">
                                            <span class="main-val"><?php echo number_format($safeMensuel, 1, '.', ''); ?></span>
                                            <?php if ($safeMensuel - $p['points_officiel'] != 0): ?>
                                            <small class="prog-val <?php echo ($safeMensuel - $p['points_officiel']) > 0 ? 'plus' : 'minus'; ?>">
                                                <?php echo ($safeMensuel - $p['points_officiel']) > 0 ? '+' : ''; ?><?php echo number_format($safeMensuel - $p['points_officiel'], 1, '.', ''); ?>
                                            </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-4 stat-item">
                                        <div class="stat-label">MOIS</div>
                                        <div class="stat-value prog-highlight <?php echo $progMois > 0 ? 'plus' : ($progMois < 0 ? 'minus' : ''); ?>">
                                            <?php echo ($progMois > 0 ? '+' : '') . number_format($progMois, 1, '.', ''); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-0 stat-row">
                                    <div class="col-4 stat-item">
                                        <div class="stat-label">PHASE 2</div>
                                        <div class="stat-value"><?php echo number_format($pointsPh2, 1, '.', ''); ?></div>
                                    </div>
                                    <div class="col-4 stat-item">
                                        <div class="stat-label">VIRTUEL</div>
                                        <div class="stat-value col-virtuel-big">
                                            <span class="main-val"><?php echo number_format($pointsVirtuel, 1, '.', ''); ?></span>
                                            <?php if ($pointsVirtuel - $p['points_officiel'] != 0): ?>
                                            <small class="prog-val <?php echo ($pointsVirtuel - $p['points_officiel']) > 0 ? 'plus' : 'minus'; ?>">
                                                <?php echo ($pointsVirtuel - $p['points_officiel']) > 0 ? '+' : ''; ?><?php echo number_format($pointsVirtuel - $p['points_officiel'], 1, '.', ''); ?>
                                            </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-4 stat-item">
                                        <div class="stat-label">ANNÉE</div>
                                        <div class="stat-value prog-highlight <?php echo $progAnnee > 0 ? 'plus' : ($progAnnee < 0 ? 'minus' : ''); ?>">
                                            <?php echo ($progAnnee > 0 ? '+' : '') . number_format($progAnnee, 1, '.', ''); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="text-start val-official d-none d-md-table-cell"><?php echo number_format($p['points_officiel'], 1, '.', ''); ?></td>
                        <td class="text-start d-none d-md-table-cell"><?php echo number_format($pointsPh1, 1, '.', ''); ?></td>
                        <td class="text-start d-none d-md-table-cell"><?php echo number_format($pointsPh2, 1, '.', ''); ?></td>
                        <td class="text-start val-monthly d-none d-md-table-cell">
                            <span class="main-val"><?php echo number_format($safeMensuel, 1, '.', ''); ?></span>
                            <?php if ($safeMensuel - $p['points_officiel'] != 0): ?>
                            <small class="prog-val <?php echo ($safeMensuel - $p['points_officiel']) > 0 ? 'plus' : 'minus'; ?>">
                                <?php echo ($safeMensuel - $p['points_officiel']) > 0 ? '+' : ''; ?><?php echo number_format($safeMensuel - $p['points_officiel'], 1, '.', ''); ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td class="text-start col-virtuel-big d-none d-md-table-cell">
                            <span class="main-val"><?php echo number_format($pointsVirtuel, 1, '.', ''); ?></span>
                            <?php if ($pointsVirtuel - $p['points_officiel'] != 0): ?>
                            <small class="prog-val <?php echo ($pointsVirtuel - $p['points_officiel']) > 0 ? 'plus' : 'minus'; ?>" style="font-size: 0.7rem; font-weight: 700; margin-left: 4px;">
                                <?php echo ($pointsVirtuel - $p['points_officiel']) > 0 ? '+' : ''; ?><?php echo number_format($pointsVirtuel - $p['points_officiel'], 1, '.', ''); ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td class="text-start prog-highlight <?php echo $progMois > 0 ? 'plus' : ($progMois < 0 ? 'minus' : ''); ?> d-none d-md-table-cell">
                            <?php echo ($progMois > 0 ? '+' : '') . number_format($progMois, 1, '.', ''); ?>
                        </td>
                        <td class="text-start prog-highlight <?php echo $progAnnee > 0 ? 'plus' : ($progAnnee < 0 ? 'minus' : ''); ?> d-none d-md-table-cell">
                            <?php echo ($progAnnee > 0 ? '+' : '') . number_format($progAnnee, 1, '.', ''); ?>
                        </td>
                    </tr>
                    <tr class="details-row hidden-row" id="details-<?php echo $p['licence']; ?>">
                        <td colspan="9" class="p-0">
                            <div class="details-wrapper">
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
