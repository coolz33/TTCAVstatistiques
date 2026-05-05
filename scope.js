const fs = require('fs');

let css = fs.readFileSync('r:/projets/ttcav2/assets/css/style.css', 'utf8');

// A very simple scoping logic for CSS:
// Split by rules
// But what about media queries?
// We can use a simple regex approach:
let scoped = css.replace(/([^\r\n,{}]+)(,(?=[^}]*{)|\s*{)/gi, (match, selector, suffix) => {
    selector = selector.trim();
    if (selector.startsWith('@') || selector === '' || selector.startsWith('/*')) return match;
    
    // Split by comma
    let parts = selector.split(',').map(part => {
        part = part.trim();
        if (part === '') return part;
        if (part.startsWith('@')) return part;
        
        // Replace body.dark-theme with #ttcav-app.dark-theme
        if (part.startsWith('body.dark-theme')) return part.replace('body.dark-theme', '#ttcav-app.dark-theme');
        if (part.startsWith('body.light-theme')) return part.replace('body.light-theme', '#ttcav-app.light-theme');
        if (part === ':root') return '#ttcav-app';
        
        // Skip if already scoped
        if (part.startsWith('#ttcav-app')) return part;

        return '#ttcav-app ' + part;
    });
    
    return parts.join(', ') + suffix;
});

// We need to fix media queries blocks because they contain inner rules
// e.g. @media (max-width: 768px) { .foo { ... } }
// The above regex might have broken them or prefixed the media query itself.
// Actually, it's safer to use an actual CSS parser.
