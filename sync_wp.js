const fs = require('fs');

// 1. Sync dashboard.tpl.php with index.php
let indexPhp = fs.readFileSync('r:/projets/ttcav2/index.php', 'utf8');

// Extract the inner content of index.php (inside the container)
let startStr = '<div class="search-bar-container mb-4">';
let endStr = '</div><!-- table-responsive -->'; // Wait, let's find the exact end.

// Let's use regex to grab the exact tbody and controls.
let tbodyMatch = indexPhp.match(/<tbody>[\s\S]*?<\/tbody>/);
if (tbodyMatch) {
    let tplPhp = fs.readFileSync('r:/projets/ttcav2/wp-plugin/wp-ttcav2/templates/dashboard.tpl.php', 'utf8');
    
    // Replace ttcav- prefixed IDs in tplPhp
    tplPhp = tplPhp.replace(/id="ttcav-([^"]+)"/g, (match, p1) => {
        if (p1 === 'app') return 'id="ttcav-app"';
        return `id="${p1}"`;
    });
    
    // Also remove ttcavToggleDetails and replace with toggleDetails
    tplPhp = tplPhp.replace(/ttcavToggleDetails/g, 'toggleDetails');
    
    // Update the tbody in tplPhp with the one from index.php
    // Wait, the PHP variables inside tbody in index.php use $p, $clubId, etc.
    // In tplPhp we also have $p. But index.php has some loading.php links which we need to remove for WP.
    let newTbody = tbodyMatch[0];
    newTbody = newTbody.replace(/loading\.php\?clubId=<\?php echo \$clubId; \?>/g, '#');
    // Also in index.php, image url is assets/avatars/. We need to replace it with absolute URL for WP.
    // Wait, in index.php: src="assets/avatars/<?php echo $p['avatar']; ?>"
    // We should replace it with: src="<?php echo esc_url('https://ttcav2.coolz.fr/assets/avatars/' . $p['avatar']); ?>"
    newTbody = newTbody.replace(/src="assets\/avatars\/<\?php echo \$p\['avatar'\]; \?>"/g, 'src="<?php echo esc_url(\'https://ttcav2.coolz.fr/assets/avatars/\' . $p[\'avatar\']); ?>"');

    tplPhp = tplPhp.replace(/<tbody>[\s\S]*?<\/tbody>/, newTbody);
    
    fs.writeFileSync('r:/projets/ttcav2/wp-plugin/wp-ttcav2/templates/dashboard.tpl.php', tplPhp);
}

// 2. Sync ttcav.js with dashboard.js
let dashJs = fs.readFileSync('r:/projets/ttcav2/assets/js/dashboard.js', 'utf8');

// Replace ajax.php?action=X with ttcav.ajax_url + ?action=ttcav_X&_ajax_nonce=...
// Example: fetch(`ajax.php?action=getHistory&licence=${licence}&t=${ts}`)
dashJs = dashJs.replace(/ajax\.php\?action=([a-zA-Z0-9_]+)/g, (match, action) => {
    // Some actions in ttcav-ajax.php: ttcav_sync_player, ttcav_get_history, ttcav_get_matches, ttcav_full_sync
    let mappedAction = 'ttcav_' + action.replace(/([A-Z])/g, "_$1").toLowerCase();
    // mappedAction is like ttcav_get_history
    if (action === 'getMatches') mappedAction = 'ttcav_get_matches';
    if (action === 'syncPlayer') mappedAction = 'ttcav_sync_player';
    if (action === 'fullSync') mappedAction = 'ttcav_full_sync';
    if (action === 'uploadAvatar') mappedAction = 'ttcav_upload_avatar';
    
    return `\${ttcav.ajax_url}?action=${mappedAction}&_ajax_nonce=\${ttcav.nonce}`;
});

// Also fix uploadAvatar where FormData is posted:
// fetch('ajax.php?action=uploadAvatar' -> fetch(ttcav.ajax_url + '?action=ttcav_upload_avatar&_ajax_nonce=' + ttcav.nonce
dashJs = dashJs.replace(/'ajax\.php\?action=uploadAvatar'/g, 'ttcav.ajax_url + "?action=ttcav_upload_avatar&_ajax_nonce=" + ttcav.nonce');

// The original dashboard.js uses relative paths for avatars: "assets/avatars/"
// We need to change it to the absolute url:
dashJs = dashJs.replace(/src="assets\/avatars\//g, 'src="https://ttcav2.coolz.fr/assets/avatars/');

fs.writeFileSync('r:/projets/ttcav2/wp-plugin/wp-ttcav2/assets/js/ttcav.js', dashJs);
console.log('Synced files!');
