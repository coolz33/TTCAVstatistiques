const fs = require('fs');
const postcss = require('postcss');
const prefixWrap = require('postcss-prefixwrap');
const path = require('path');

const targetFolders = [
    'wp-plugin/wp-ttcav2',
    'wp-plugin/ttcav-ranking'
];

let css = fs.readFileSync('bootstrap.min.css', 'utf8');

postcss([
    prefixWrap('#ttcav-app', {
        ignoredSelectors: ['#ttcav-app'],
        prefixRootTags: true 
    })
])
.process(css, { from: 'bootstrap.min.css' })
.then(result => {
    let finalCss = result.css.replace(/:root/g, '#ttcav-app');
    
    targetFolders.forEach(folder => {
        const outPath = path.join(folder, 'assets/css/bootstrap-scoped.css');
        if (fs.existsSync(folder)) {
            if (!fs.existsSync(path.dirname(outPath))) {
                fs.mkdirSync(path.dirname(outPath), { recursive: true });
            }
            fs.writeFileSync(outPath, finalCss);
            console.log(`Successfully scoped Bootstrap for ${folder}`);
        }
    });
});
