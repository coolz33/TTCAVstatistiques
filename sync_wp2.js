const fs = require('fs');

let indexPhp = fs.readFileSync('r:/projets/ttcav2/index.php', 'utf8');

// Match everything inside <div class="container py-5"> up to the end of the container
// It ends at line 273 (</body>)
let bodyContent = indexPhp.match(/<div class="container py-5">[\s\S]*?(?=<\/body>)/)[0];

// Replace loading.php links with location.reload()
bodyContent = bodyContent.replace(/location\.href='loading\.php\?clubId=<\?php echo \$clubId; \?>'/g, 'location.reload()');

// Replace avatar paths
bodyContent = bodyContent.replace(/src="assets\/avatars\/<\?php echo \$p\['avatar'\]; \?>"/g, 'src="<?php echo esc_url(\'https://ttcav2.coolz.fr/assets/avatars/\' . $p[\'avatar\']); ?>"');

// Wrap sync and refresh buttons with $is_admin check for PC
let pcSyncBlock = `<button class="btn btn-outline-warning btn-sm" id="btnSyncAllPC">
                        <i class="fas fa-bolt me-1"></i> Sync Club
                    </button>
                    
                    <button class="btn btn-outline-light btn-sm" onclick="location.reload()" title="Rafraîchir la page">
                        <i class="fas fa-sync-alt"></i>
                    </button>`;
bodyContent = bodyContent.replace(pcSyncBlock, `<?php if ( $is_admin ) : ?>\n                    ${pcSyncBlock}\n                    <?php endif; ?>`);

// Wrap sync and refresh buttons with $is_admin check for Mobile
let mobileSyncBlock = `<button class="btn btn-outline-warning btn-sm" id="btnSyncAllMobile">
                        <i class="fas fa-bolt me-1"></i> Sync
                    </button>
                    <button class="btn btn-outline-light btn-sm" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i>
                    </button>`;
bodyContent = bodyContent.replace(mobileSyncBlock, `<?php if ( $is_admin ) : ?>\n                    ${mobileSyncBlock}\n                    <?php endif; ?>`);

// Wrap individual refresh button
let playerRefreshBlock = `<i class="fas fa-sync-alt refresh-icon-mini" onclick="event.stopPropagation(); syncSinglePlayer('<?php echo $p['licence']; ?>')" title="Actualiser"></i>`;
bodyContent = bodyContent.replace(playerRefreshBlock, `<?php if ( $is_admin ) : ?>\n                                        ${playerRefreshBlock}\n                                        <?php endif; ?>`);

// Add the wrapper and header
let tplContent = `<?php
/**
 * Template du tableau TTCAV pour le shortcode WordPress.
 * Variables disponibles : $players, $club_name, $club_id, $is_admin
 */
$rank = 1;
?>
<div id="ttcav-app" class="dark-theme">
${bodyContent}
</div>
`;

// Also replace $clubName with $club_name
tplContent = tplContent.replace(/\$clubName/g, '$club_name');

fs.writeFileSync('r:/projets/ttcav2/wp-plugin/wp-ttcav2/templates/dashboard.tpl.php', tplContent);
console.log('Successfully cloned dashboard!');
