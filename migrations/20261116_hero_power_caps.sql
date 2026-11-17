UPDATE city_hero
SET power_max = LEAST(150, GREATEST(0, lvl) + 50),
    power = LEAST(GREATEST(0, power), LEAST(150, GREATEST(0, lvl) + 50))
WHERE power_max <> LEAST(150, GREATEST(0, lvl) + 50)
   OR power <> LEAST(GREATEST(0, power), LEAST(150, GREATEST(0, lvl) + 50));

ALTER TABLE city_hero
    MODIFY power INT NOT NULL DEFAULT 51,
    MODIFY power_max INT NOT NULL DEFAULT 51;
