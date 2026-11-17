const MAX_HEROES_OUTSIDE_CITY = 10;

function getHeroOutsideCityLimit(plazaLevel) {
    const level = Number(plazaLevel);
    if (!Number.isFinite(level) || level < 0) {
        throw new TypeError('Plaza level must be a non-negative number');
    }
    return Math.min(MAX_HEROES_OUTSIDE_CITY, Math.floor(level));
}

module.exports = {
    MAX_HEROES_OUTSIDE_CITY,
    getHeroOutsideCityLimit
};
