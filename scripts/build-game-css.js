const fs = require('node:fs');
const path = require('node:path');

const stylesheetDirectory = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113');
const bundles = {
    'core.bundle.css': ['reset-min.css', 'fonts-min.css', 'base.css', 'animation.css'],
    'gameplay.bundle.css': ['city.css', 'world.css', 'tooltips.css', 'hero.css', 'godPoints.css', 'ui.css', 'team.css'],
    'overrides.bundle.css': ['finishing.css', 'lastStyle.css', 'lastOfLast.css']
};

for (const [bundleName, sourceFiles] of Object.entries(bundles)) {
    const contents = sourceFiles.map(filename =>
        fs.readFileSync(path.join(stylesheetDirectory, filename), 'utf8')
    );
    const bundle = contents.join('\n').replace(/\s+$/, '');
    fs.writeFileSync(path.join(stylesheetDirectory, bundleName), `${bundle}\n`, 'utf8');
}
