-- Remove unsupported zero-power equipment from the seeded local test player only.
DELETE pe
FROM `player_equip` pe
JOIN `user_auth` ua ON ua.`id_player` = pe.`id_player`
WHERE DATABASE() = 'caesar_test'
  AND ua.`username` = 'test2'
  AND pe.`type` IN ('worrior', 'thunder', 'thor', 'neptune', 'light');
