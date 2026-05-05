const fs = require('fs');
const postcss = require('postcss');
const prefixWrap = require('postcss-prefixwrap');

let css = fs.readFileSync('assets/css/style.css', 'utf8');

// Remove the global * reset to avoid breaking Bootstrap inside the scoped wrapper
css = css.replace(/\*\s*\{[^}]*\}/g, '');

// Pre-process CSS to handle :root and body replacements before prefixing
let preprocessedCss = css
    .replace(/:root/g, '#ttcav-app')
    .replace(/body\.dark-theme/g, '#ttcav-app.dark-theme')
    .replace(/body\.light-theme/g, '#ttcav-app.light-theme');

postcss([
    prefixWrap('#ttcav-app', {
        ignoredSelectors: ['#ttcav-app', '#ttcav-app.dark-theme', '#ttcav-app.light-theme']
    })
])
.process(preprocessedCss, { from: 'assets/css/style.css', to: 'wp-plugin/wp-ttcav2/assets/css/ttcav.css' })
.then(result => {
    fs.writeFileSync('wp-plugin/wp-ttcav2/assets/css/ttcav.css', result.css);
    console.log('Successfully scoped CSS!');
});
