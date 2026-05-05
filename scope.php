<?php
$css = file_get_contents('r:/projets/ttcav2/assets/css/style.css');

// Remove comments to make parsing easier, but keep them if possible.
// Actually, simple regex for scoping:
// Find rules blocks: `selector { rules }`
$scopifier = function($css, $scope) {
    // This is a naive regex, might not work perfectly with media queries
    // A better approach is to use a simple parser or just prepend manually for the main blocks.
};
