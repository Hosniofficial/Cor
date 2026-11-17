const test = require('node:test');
const assert = require('node:assert/strict');
const {
    MAX_HEROES_OUTSIDE_CITY,
    getHeroOutsideCityLimit
} = require('../caesar-shared/hero-deployment');

test('plaza level controls simultaneous heroes outside the city up to ten', () => {
    assert.equal(MAX_HEROES_OUTSIDE_CITY, 10);
    assert.equal(getHeroOutsideCityLimit(0), 0);
    assert.equal(getHeroOutsideCityLimit(5), 5);
    assert.equal(getHeroOutsideCityLimit(10), 10);
    assert.equal(getHeroOutsideCityLimit(30), 10);
});

test('invalid plaza levels are rejected instead of silently granting slots', () => {
    assert.throws(() => getHeroOutsideCityLimit(-1), TypeError);
    assert.throws(() => getHeroOutsideCityLimit('unknown'), TypeError);
});
