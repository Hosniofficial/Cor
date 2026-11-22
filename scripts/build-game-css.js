const fs = require('node:fs');
const path = require('node:path');

const stylesheetDirectory = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113');
const bundles = {
    'base.bundle.css': ['reset-min.css', 'fonts-min.css', 'base.css', 'city.css', 'world.css'],
    'overrides.bundle.css': ['lastStyle.css', 'lastOfLast.css', 'finishing.css', 'tooltips.css', 'animation.css'],
    'features.bundle.css': ['hero.css', 'godPoints.css', 'ui.css', 'team.css']
};

for (const [bundleName, sourceFiles] of Object.entries(bundles)) {
    const contents = sourceFiles.map(filename =>
        fs.readFileSync(path.join(stylesheetDirectory, filename), 'utf8')
    );
    fs.writeFileSync(path.join(stylesheetDirectory, bundleName), contents.join('\n'), 'utf8');
}
