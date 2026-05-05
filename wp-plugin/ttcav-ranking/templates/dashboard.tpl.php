<?php
/**
 * Template du tableau TTCAV pour le shortcode WordPress.
 * Version 1.1.6 - Toolbar Pro Unifiée
 */
$rank = 1;
?>
<div id="ttcav-app" class="dark-theme">
    <div class="container-fluid">
        
        <!-- BARRE D'OUTILS UNIFIÉE (COMME IMAGE 2) -->
        <div class="toolbar-pro">
            <div class="search-wrapper-pro">
                <i class="fas fa-search"></i>
                <input type="text" id="playerSearch" placeholder="Rechercher un joueur...">
            </div>

            <div class="btn-group-pro pc-only-flex" style="display: flex; gap: 8px;">
                <div class="btn-group" role="group" id="avatarSizeSelectorPC">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-size="sm"><i class="fas fa-th"></i></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-size="md"><i class="fas fa-th-large"></i></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-size="lg"><i class="fas fa-square"></i></button>
                </div>

                <?php if ( $is_admin ) : ?>
                <button class="btn btn-warning btn-sm" id="btnSyncAllPC">
                    <i class="fas fa-bolt"></i> Sync Club
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <?php endif; ?>

                <button class="theme-toggle" id="themeToggleBtn">
                    <i class="fas fa-sun"></i>
                </button>
            </div>

            <div class="stats-summary-pro" style="white-space: nowrap; font-size: 0.9rem; color: #a0aec0;">
                <span id="playerCount" style="color: #fff; font-weight: bold;"><?php echo count($players); ?></span> joueurs
            </div>
        </div>

        <div class="table-responsive">
            <table class="players-table table table-hover">
                <thead>
                    <tr style="border-bottom: 2px solid #4a5568;">
                        <th style="width: 40px; text-align: center;">#</th>
                        <th>JOUEUR</th>
                        <th class="pc-only-cell" style="text-align: center;">OFFICIEL</th>
                        <th style="text-align: center;">PH. 1</th>
                        <th style="text-align: center;">PH. 2</th>
                        <th style="text-align: center;">MENSUEL</th>
                        <th style="text-align: center; color: #ecc94b;">VIRTUEL</th>
                        <th style="text-align: center;">MOIS</th>
                        <th style="text-align: center;">ANNÉE</th>
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
                        <td style="text-align: center; color: #a0aec0;"><?php echo $rank++; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="avatar-pro">
                                    <?php if (!empty($p['avatar'])): ?>
                                        <img src="<?php echo esc_url('https://ttcav2.coolz.fr/assets/avatars/' . $p['avatar']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                    <?php else: ?>
                                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#2d3748; color:#fff; font-weight:bold;"><?php echo $initials; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex; flex-direction:column;">
                                    <span style="font-weight: 800; font-size: 0.9rem;"><?php echo htmlspecialchars(strtoupper($p['nom'])); ?></span>
                                    <span style="font-size: 0.8rem; color: #a0aec0;"><?php echo htmlspecialchars($p['prenom']); ?></span>
                                    <span style="font-size: 0.7rem; color: #ecc94b;"><?php echo $p['licence']; ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="pc-only-cell" style="text-align: center; color: #4299e1;"><?php echo number_format($p['points_officiel'], 1, '.', ''); ?></td>
                        <td style="text-align: center;"><?php echo number_format($pointsPh1, 1, '.', ''); ?></td>
                        <td style="text-align: center;"><?php echo number_format($pointsPh2, 1, '.', ''); ?></td>
                        <td style="text-align: center; color: #ecc94b;"><?php echo number_format($safeMensuel, 1, '.', ''); ?></td>
                        <td style="text-align: center; color: #ecc94b; font-weight: bold;"><?php echo number_format($pointsVirtuel, 1, '.', ''); ?></td>
                        <td style="text-align: center; font-weight: bold; color: <?php echo $progMois >= 0 ? '#48bb78' : '#f56565'; ?>;">
                            <?php echo ($progMois > 0 ? '+' : '') . number_format($progMois, 1, '.', ''); ?>
                        </td>
                        <td style="text-align: center; font-weight: bold; color: <?php echo $progAnnee >= 0 ? '#48bb78' : '#f56565'; ?>;">
                            <?php echo ($progAnnee > 0 ? '+' : '') . number_format($progAnnee, 1, '.', ''); ?>
                        </td>
                    </tr>
                    <tr class="details-row hidden-row" id="details-<?php echo $p['licence']; ?>">
                        <td colspan="9">
                            <div style="padding: 20px; background: #1a202c; border-bottom: 2px solid #ecc94b;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                                    <h3 style="color: #ecc94b; margin: 0;">Historique & Matchs</h3>
                                    <button class="btn btn-outline-light btn-sm" onclick="toggleDetails('<?php echo $p['licence']; ?>')">FERMER</button>
                                </div>
                                <div style="height: 300px;"><canvas id="chart-<?php echo $p['licence']; ?>"></canvas></div>
                                <div class="matches-container mt-4">
                                    <div class="text-center p-3 text-muted">Chargement des matchs...</div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
