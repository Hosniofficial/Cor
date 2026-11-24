'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const repositoryRoot = path.join(__dirname, '..');
const adminPage = fs.readFileSync(path.join(repositoryRoot, 'caesar-admin', 'index.php'), 'utf8');
const adminApi = fs.readFileSync(path.join(repositoryRoot, 'caesar-admin', 'api.php'), 'utf8');

test('admin catalog images use existing assets and stop fallback error recursion', () => {
    assert.match(
        adminPage,
        /<img src="\.\.\/caesar-assets\/web\/\$\{eq\.image\}" onerror="this\.onerror=null;this\.src='\.\.\/caesar-assets\/web\/images\/equip\/equip0\.jpg'">/
    );
    assert.match(
        adminPage,
        /<img src="\.\.\/caesar-assets\/web\/\$\{it\.image\}" onerror="this\.onerror=null;this\.src='\.\.\/caesar-assets\/web\/images\/items\/item000\.jpg'">/
    );

    for (const asset of [
        'caesar-assets/web/images/equip/equip0.jpg',
        'caesar-assets/web/images/items/item000.jpg'
    ]) {
        assert.ok(fs.existsSync(path.join(repositoryRoot, asset)), `${asset} should exist as a fallback`);
    }
});

test('admin resource entry and grants use the shared city balance limit', () => {
    const resourceService = fs.readFileSync(
        path.join(repositoryRoot, 'caesar-api', 'services', 'ResourceService.php'),
        'utf8'
    );
    assert.match(resourceService, /MAX_CITY_BALANCE\s*=\s*1000000000000/);
    assert.match(adminPage, /require_once __DIR__ \. '\/\.\.\/caesar-api\/services\/ResourceService\.php';/);
    assert.match(adminPage, /\$maxCityBalance = ResourceService::MAX_CITY_BALANCE;/);

    for (const resource of ['food', 'wood', 'stone', 'metal', 'coin']) {
        assert.match(
            adminPage,
            new RegExp(`<input type="number" id="grant-all-${resource}"[^>]*max="<\\?= \\$maxCityBalance \\?>"`)
        );
        assert.match(
            adminPage,
            new RegExp(`<input type="number" id="c-${resource}"[^>]*max="<\\?= \\$maxCityBalance \\?>"`)
        );
    }

    assert.equal(
        (adminApi.match(/max_range'\s*=>\s*ResourceService::MAX_CITY_BALANCE/g) || []).length,
        2,
        'both bulk grants and single-city edits should validate against the shared limit'
    );
    assert.match(
        adminApi,
        /GREATEST\(CAST\(`\$resource` AS DECIMAL\(20, 0\)\), LEAST\(CAST\(`\$resource` AS DECIMAL\(20, 0\)\) \+ \?,/
    );
    assert.match(
        adminApi,
        /\. ResourceService::MAX_CITY_BALANCE \. '\)\)';/
    );
});
