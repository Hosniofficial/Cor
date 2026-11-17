-- Seed a local game player for inventory, exchange, resource, and combat testing.
-- Before applying, verify the target database and username below. This migration only
-- seeds the game player linked to user_auth.username. admin_user is a separate table.
-- To reuse later, change both variables to the verified database and player username.
-- It leaves player.porm promotion, rank, city/building levels, education, and existing hero armies unchanged.
-- Food is raised to 100 billion to cover the configured 100 million troops' hourly upkeep.

SET @seed_expected_schema := _utf8mb4'caesar_test' COLLATE utf8mb4_unicode_ci;
SET @seed_target_username := _utf8mb4'test2' COLLATE utf8mb4_unicode_ci;

CREATE TEMPORARY TABLE _seed_target_player (
    id_player INT NOT NULL PRIMARY KEY
) ENGINE=MEMORY;
INSERT INTO _seed_target_player (id_player)
SELECT p.id_player
FROM player p
JOIN user_auth ua ON ua.id_player = p.id_player
WHERE ua.username = @seed_target_username
  AND DATABASE() = @seed_expected_schema;

CREATE TEMPORARY TABLE _seed_target_city (
    id_player INT NOT NULL PRIMARY KEY,
    id_city INT NOT NULL
) ENGINE=MEMORY;
INSERT INTO _seed_target_city (id_player, id_city)
SELECT t.id_player, MIN(c.id_city)
FROM _seed_target_player t
JOIN city c ON c.id_player = t.id_player
GROUP BY t.id_player;

CREATE TEMPORARY TABLE _seed_item_catalog (
    id_item VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL PRIMARY KEY
) ENGINE=MEMORY;
INSERT INTO _seed_item_catalog (id_item) VALUES
    ('a_play'), ('ancient_coins'), ('apollo_pass'), ('archim_a'), ('archim_b'),
    ('archim_c'), ('archim_d'), ('archit_a'), ('archit_b'), ('archit_c'),
    ('archit_d'), ('army_a_100'), ('army_a_1000'), ('army_all_1'), ('army_all_2'),
    ('army_all_3'), ('army_b_100'), ('army_b_1000'), ('army_box'), ('army_c_100'),
    ('army_c_1000'), ('army_d_100'), ('army_d_1000'), ('army_e_100'), ('army_e_1000'),
    ('army_f_100'), ('army_f_1000'), ('beginner_back_1'), ('beginner_back_10'), ('beginner_back_2'),
    ('beginner_back_3'), ('beginner_back_4'), ('beginner_back_5'), ('beginner_back_6'), ('beginner_back_7'),
    ('beginner_back_8'), ('beginner_back_9'), ('black_ore'), ('brave_slo'), ('bread'),
    ('bright_medal'), ('bright_piece'), ('bright_slo'), ('bronze_bar'), ('bronze_cup'),
    ('bronze_horn'), ('bronze_order'), ('buy_voucher'), ('ceaser_eagle'), ('certain_move'),
    ('change_name'), ('christmas_stocking'), ('christmas_tree'), ('coach'), ('coin_1'),
    ('coin_7'), ('coin_a'), ('coin_b'), ('coin_c'), ('coin_d'),
    ('corn_seed'), ('courage_medal'), ('debris'), ('demigod_papirus'), ('demigod_power'),
    ('drachma'), ('essence'), ('evil_army_pass'), ('evil_pass'), ('exp_hero_100'),
    ('exp_hero_30'), ('exp_hero_8'), ('expan_plan'), ('family_slogan'), ('festival_blue'),
    ('festival_gray'), ('festival_green'), ('festival_orange'), ('festival_purple'), ('festival_special'),
    ('festival_yellow'), ('flag'), ('flag_cartaga'), ('flag_egypt'), ('flag_england'),
    ('flag_france'), ('flag_germany'), ('flag_greek'), ('flag_macdoni'), ('flag_magul'),
    ('flag_roma'), ('flag_spain'), ('food_a'), ('food_b'), ('food_c'),
    ('food_d'), ('freedom_help'), ('fruit'), ('ghostemblem'), ('gift_box'),
    ('god_point_10k'), ('god_point_175'), ('god_point_1k'), ('god_point_2k'), ('god_point_30'),
    ('god_point_5'), ('god_point_50k'), ('god_point_5k'), ('god_point_75'), ('god_point_750'),
    ('god_spring'), ('gold_bar'), ('gold_bar_piece'), ('gold_cup'), ('gold_horn'),
    ('gold_order'), ('golden_box'), ('golden_supply_crate'), ('hammer'), ('help_house_chng'),
    ('hephaestus_pieces_a'), ('hephaestus_pieces_b'), ('hero_back'), ('hero_train'), ('high_glory_stone'),
    ('horn_help'), ('huizhang'), ('king'), ('king_chest'), ('king_medal'),
    ('law_1'), ('law_2'), ('law_3'), ('lion_hrt'), ('lion_slo'),
    ('low_glory_stone'), ('luck_play'), ('luna_fragment'), ('luna_pergament'), ('luna_stone'),
    ('luxury_1'), ('luxury_2'), ('luxury_3'), ('luxury_4'), ('luxury_5'),
    ('luxury_6'), ('luxury_7'), ('luxury_8'), ('luxury_9'), ('march_prot'),
    ('marcus_scheme'), ('marmlo_helmet'), ('mars_pieces_a'), ('mars_pieces_b'), ('mars_pieces_c'),
    ('meat'), ('medal_bronz'), ('medal_ceasro'), ('medal_den'), ('medal_gold'),
    ('medal_leo'), ('medal_silver'), ('medalofcraft'), ('mediacl_statue'), ('medical_moun'),
    ('medium_glory_stone'), ('metal_1'), ('metal_7'), ('metal_a'), ('metal_b'),
    ('metal_c'), ('metal_d'), ('mili_slo'), ('milk'), ('miraculous_coins'),
    ('motiv_60'), ('motiv_7'), ('necklace_1'), ('necklace_2'), ('necklace_3'),
    ('necklace_4'), ('necklace_5'), ('owl_of_minerva'), ('peace'), ('peace_emblem'),
    ('peace_flag'), ('peace_medal'), ('polit_a'), ('polit_b'), ('polit_c'),
    ('polit_d'), ('powder_keg'), ('power_bright'), ('prestige_slogan'), ('prot_pop'),
    ('qulinds_shaft'), ('random_move'), ('rec_letter'), ('red_bursa'), ('red_ore'),
    ('repel_medal'), ('repel_trumpet_1'), ('repel_trumpet_2'), ('retreat_point'), ('roma_colosseum_box'),
    ('rose_flower'), ('sell_voucher'), ('senate_golden_box'), ('senate_platinum_box'), ('senate_silver_box'),
    ('shining_coin'), ('shopping_car'), ('silver_bar'), ('silver_cup'), ('silver_horn'),
    ('silver_order'), ('silver_ore'), ('silver_supply_crate'), ('snowcity'), ('sol_chest'),
    ('sol_fragment'), ('sol_pergament'), ('sol_stone'), ('soul_of_mars'), ('sparta_stab'),
    ('spartans'), ('spartans_pack'), ('spartans_relics'), ('spies'), ('staduim_blue'),
    ('staduim_orange'), ('staduim_red'), ('stone_1'), ('stone_7'), ('stone_a'),
    ('stone_b'), ('stone_c'), ('stone_d'), ('t_map'), ('taffy'),
    ('tagned_3p'), ('tagned_4p'), ('tagned_5p'), ('tagned_6p'), ('tagned_7p'),
    ('tagned_8p'), ('thor_fragment'), ('thor_pergament'), ('thor_stone'), ('train_acce_30'),
    ('train_acce_50'), ('truce_pack'), ('trumpet'), ('union_declar'), ('union_era'),
    ('union_slogan'), ('vertumnus_box'), ('wall_acce'), ('warrior_medal'), ('wheat_1'),
    ('wheat_7'), ('winter_pack'), ('wood_1'), ('wood_7'), ('wood_a'),
    ('wood_b'), ('wood_box'), ('wood_c'), ('wood_d');

CREATE TEMPORARY TABLE _seed_equipment_catalog (
    type VARCHAR(50) NOT NULL,
    part VARCHAR(50) NOT NULL,
    lvl INT NOT NULL,
    PRIMARY KEY (type, part, lvl)
) ENGINE=MEMORY;
INSERT INTO _seed_equipment_catalog (type, part, lvl) VALUES
    ('blue', 'armor', 1), ('blue', 'boot', 1), ('blue', 'helmet', 1),
    ('blue', 'shield', 1), ('blue', 'sword', 1), ('bright', 'armor', 1),
    ('bright', 'boot', 1), ('bright', 'helmet', 1), ('bright', 'shield', 1),
    ('bright', 'sword', 1), ('dark', 'armor', 1), ('dark', 'belt', 1),
    ('dark', 'belt', 2), ('dark', 'belt', 3), ('dark', 'belt', 4),
    ('dark', 'belt', 5), ('dark', 'belt', 6), ('dark', 'belt', 7),
    ('dark', 'belt', 8), ('dark', 'belt', 9), ('dark', 'boot', 1),
    ('dark', 'helmet', 1), ('dark', 'necklace', 1), ('dark', 'necklace', 2),
    ('dark', 'necklace', 3), ('dark', 'necklace', 4), ('dark', 'necklace', 5),
    ('dark', 'necklace', 6), ('dark', 'necklace', 7), ('dark', 'necklace', 8),
    ('dark', 'necklace', 9), ('dark', 'pendant', 1), ('dark', 'pendant', 2),
    ('dark', 'pendant', 3), ('dark', 'pendant', 4), ('dark', 'pendant', 5),
    ('dark', 'pendant', 6), ('dark', 'pendant', 7), ('dark', 'pendant', 8),
    ('dark', 'pendant', 9), ('dark', 'ring', 1), ('dark', 'ring', 2),
    ('dark', 'ring', 3), ('dark', 'ring', 4), ('dark', 'ring', 5),
    ('dark', 'ring', 6), ('dark', 'ring', 7), ('dark', 'ring', 8),
    ('dark', 'ring', 9), ('dark', 'shield', 1), ('dark', 'steed', 1),
    ('dark', 'steed', 2), ('dark', 'steed', 3), ('dark', 'steed', 4),
    ('dark', 'steed', 5), ('dark', 'steed', 6), ('dark', 'steed', 7),
    ('dark', 'steed', 8), ('dark', 'steed', 9), ('dark', 'sword', 1),
    ('fart', 'armor', 1), ('fart', 'boot', 1), ('fart', 'helmet', 1),
    ('fart', 'shield', 1), ('fart', 'sword', 1), ('fearless', 'armor', 1),
    ('fearless', 'boot', 1), ('fearless', 'helmet', 1), ('fearless', 'shield', 1),
    ('fearless', 'sword', 1), ('holy', 'armor', 1), ('holy', 'belt', 1),
    ('holy', 'belt', 2), ('holy', 'belt', 3), ('holy', 'belt', 4),
    ('holy', 'belt', 5), ('holy', 'belt', 6), ('holy', 'belt', 7),
    ('holy', 'belt', 8), ('holy', 'belt', 9), ('holy', 'boot', 1),
    ('holy', 'helmet', 1), ('holy', 'necklace', 1), ('holy', 'necklace', 2),
    ('holy', 'necklace', 3), ('holy', 'necklace', 4), ('holy', 'necklace', 5),
    ('holy', 'necklace', 6), ('holy', 'necklace', 7), ('holy', 'necklace', 8),
    ('holy', 'necklace', 9), ('holy', 'pendant', 1), ('holy', 'pendant', 2),
    ('holy', 'pendant', 3), ('holy', 'pendant', 4), ('holy', 'pendant', 5),
    ('holy', 'pendant', 6), ('holy', 'pendant', 7), ('holy', 'pendant', 8),
    ('holy', 'pendant', 9), ('holy', 'ring', 1), ('holy', 'ring', 2),
    ('holy', 'ring', 3), ('holy', 'ring', 4), ('holy', 'ring', 5),
    ('holy', 'ring', 6), ('holy', 'ring', 7), ('holy', 'ring', 8),
    ('holy', 'ring', 9), ('holy', 'shield', 1), ('holy', 'steed', 1),
    ('holy', 'steed', 2), ('holy', 'steed', 3), ('holy', 'steed', 4),
    ('holy', 'steed', 5), ('holy', 'steed', 6), ('holy', 'steed', 7),
    ('holy', 'steed', 8), ('holy', 'steed', 9), ('holy', 'sword', 1),
    ('khaled', 'armor', 1), ('khaled', 'belt', 1), ('khaled', 'belt', 2),
    ('khaled', 'belt', 3), ('khaled', 'belt', 4), ('khaled', 'belt', 5),
    ('khaled', 'belt', 6), ('khaled', 'belt', 7), ('khaled', 'belt', 8),
    ('khaled', 'belt', 9), ('khaled', 'boot', 1), ('khaled', 'helmet', 1),
    ('khaled', 'necklace', 1), ('khaled', 'necklace', 2), ('khaled', 'necklace', 3),
    ('khaled', 'necklace', 4), ('khaled', 'necklace', 5), ('khaled', 'necklace', 6),
    ('khaled', 'necklace', 7), ('khaled', 'necklace', 8), ('khaled', 'necklace', 9),
    ('khaled', 'pendant', 1), ('khaled', 'pendant', 2), ('khaled', 'pendant', 3),
    ('khaled', 'pendant', 4), ('khaled', 'pendant', 5), ('khaled', 'pendant', 6),
    ('khaled', 'pendant', 7), ('khaled', 'pendant', 8), ('khaled', 'pendant', 9),
    ('khaled', 'ring', 1), ('khaled', 'ring', 2), ('khaled', 'ring', 3),
    ('khaled', 'ring', 4), ('khaled', 'ring', 5), ('khaled', 'ring', 6),
    ('khaled', 'ring', 7), ('khaled', 'ring', 8), ('khaled', 'ring', 9),
    ('khaled', 'shield', 1), ('khaled', 'steed', 1), ('khaled', 'steed', 2),
    ('khaled', 'steed', 3), ('khaled', 'steed', 4), ('khaled', 'steed', 5),
    ('khaled', 'steed', 6), ('khaled', 'steed', 7), ('khaled', 'steed', 8),
    ('khaled', 'steed', 9), ('khaled', 'sword', 1), ('light', 'armor', 1),
    ('light', 'boot', 1), ('light', 'helmet', 1), ('light', 'shield', 1),
    ('light', 'sword', 1), ('lion', 'armor', 1), ('lion', 'boot', 1),
    ('lion', 'helmet', 1), ('lion', 'shield', 1), ('lion', 'sword', 1),
    ('loy', 'armor', 1), ('loy', 'belt', 1), ('loy', 'belt', 2),
    ('loy', 'belt', 3), ('loy', 'belt', 4), ('loy', 'belt', 5),
    ('loy', 'belt', 6), ('loy', 'belt', 7), ('loy', 'belt', 8),
    ('loy', 'belt', 9), ('loy', 'boot', 1), ('loy', 'helmet', 1),
    ('loy', 'necklace', 1), ('loy', 'necklace', 2), ('loy', 'necklace', 3),
    ('loy', 'necklace', 4), ('loy', 'necklace', 5), ('loy', 'necklace', 6),
    ('loy', 'necklace', 7), ('loy', 'necklace', 8), ('loy', 'necklace', 9),
    ('loy', 'pendant', 1), ('loy', 'pendant', 2), ('loy', 'pendant', 3),
    ('loy', 'pendant', 4), ('loy', 'pendant', 5), ('loy', 'pendant', 6),
    ('loy', 'pendant', 7), ('loy', 'pendant', 8), ('loy', 'pendant', 9),
    ('loy', 'ring', 1), ('loy', 'ring', 2), ('loy', 'ring', 3),
    ('loy', 'ring', 4), ('loy', 'ring', 5), ('loy', 'ring', 6),
    ('loy', 'ring', 7), ('loy', 'ring', 8), ('loy', 'ring', 9),
    ('loy', 'shield', 1), ('loy', 'steed', 1), ('loy', 'steed', 2),
    ('loy', 'steed', 3), ('loy', 'steed', 4), ('loy', 'steed', 5),
    ('loy', 'steed', 6), ('loy', 'steed', 7), ('loy', 'steed', 8),
    ('loy', 'steed', 9), ('loy', 'sword', 1), ('mars', 'armor', 1),
    ('mars', 'boot', 1), ('mars', 'helmet', 1), ('mars', 'shield', 1),
    ('mars', 'sword', 1), ('mili', 'armor', 1), ('mili', 'boot', 1),
    ('mili', 'helmet', 1), ('mili', 'shield', 1), ('mili', 'sword', 1),
    ('neptune', 'armor', 1), ('neptune', 'boot', 1), ('neptune', 'helmet', 1),
    ('neptune', 'shield', 1), ('neptune', 'sword', 1), ('peace', 'armor', 1),
    ('peace', 'boot', 1), ('peace', 'helmet', 1), ('peace', 'shield', 1),
    ('peace', 'sword', 1), ('saton', 'armor', 1), ('saton', 'boot', 1),
    ('saton', 'helmet', 1), ('saton', 'shield', 1), ('saton', 'sword', 1),
    ('sparta', 'armor', 1), ('sparta', 'boot', 1), ('sparta', 'helmet', 1),
    ('sparta', 'shield', 1), ('sparta', 'sword', 1), ('thor', 'armor', 1),
    ('thor', 'boot', 1), ('thor', 'helmet', 1), ('thor', 'shield', 1),
    ('thor', 'sword', 1), ('thunder', 'armor', 1), ('thunder', 'boot', 1),
    ('thunder', 'helmet', 1), ('thunder', 'shield', 1), ('thunder', 'sword', 1),
    ('victory', 'armor', 1), ('victory', 'belt', 1), ('victory', 'belt', 2),
    ('victory', 'belt', 3), ('victory', 'belt', 4), ('victory', 'belt', 5),
    ('victory', 'belt', 6), ('victory', 'belt', 7), ('victory', 'belt', 8),
    ('victory', 'belt', 9), ('victory', 'boot', 1), ('victory', 'helmet', 1),
    ('victory', 'necklace', 1), ('victory', 'necklace', 2), ('victory', 'necklace', 3),
    ('victory', 'necklace', 4), ('victory', 'necklace', 5), ('victory', 'necklace', 6),
    ('victory', 'necklace', 7), ('victory', 'necklace', 8), ('victory', 'necklace', 9),
    ('victory', 'pendant', 1), ('victory', 'pendant', 2), ('victory', 'pendant', 3),
    ('victory', 'pendant', 4), ('victory', 'pendant', 5), ('victory', 'pendant', 6),
    ('victory', 'pendant', 7), ('victory', 'pendant', 8), ('victory', 'pendant', 9),
    ('victory', 'ring', 1), ('victory', 'ring', 2), ('victory', 'ring', 3),
    ('victory', 'ring', 4), ('victory', 'ring', 5), ('victory', 'ring', 6),
    ('victory', 'ring', 7), ('victory', 'ring', 8), ('victory', 'ring', 9),
    ('victory', 'shield', 1), ('victory', 'steed', 1), ('victory', 'steed', 2),
    ('victory', 'steed', 3), ('victory', 'steed', 4), ('victory', 'steed', 5),
    ('victory', 'steed', 6), ('victory', 'steed', 7), ('victory', 'steed', 8),
    ('victory', 'steed', 9), ('victory', 'sword', 1), ('volcan', 'armor', 1),
    ('volcan', 'boot', 1), ('volcan', 'helmet', 1), ('volcan', 'shield', 1),
    ('volcan', 'sword', 1), ('worrior', 'armor', 1), ('worrior', 'boot', 1),
    ('worrior', 'helmet', 1), ('worrior', 'shield', 1), ('worrior', 'sword', 1);

CREATE TEMPORARY TABLE _seed_piece_number (
    piece TINYINT NOT NULL PRIMARY KEY
) ENGINE=MEMORY;
INSERT INTO _seed_piece_number (piece) VALUES (1), (2), (3), (4), (5), (6), (7), (8), (9), (10);

START TRANSACTION;

UPDATE player p
JOIN _seed_target_player t ON t.id_player = p.id_player
SET p.gold = GREATEST(p.gold, 1000000000),
    p.prestige = GREATEST(p.prestige, 1000000000),
    p.honor = GREATEST(p.honor, 1000000000);

UPDATE city c
JOIN _seed_target_player t ON t.id_player = c.id_player
SET c.coin = GREATEST(c.coin, 1000000000),
    c.food = GREATEST(c.food, 100000000000),
    c.wood = GREATEST(c.wood, 1000000000),
    c.stone = GREATEST(c.stone, 1000000000),
    c.metal = GREATEST(c.metal, 1000000000),
    c.army_a = GREATEST(c.army_a, 100000000),
    c.army_b = GREATEST(c.army_b, 100000000),
    c.army_c = GREATEST(c.army_c, 100000000),
    c.army_d = GREATEST(c.army_d, 100000000),
    c.army_e = GREATEST(c.army_e, 100000000),
    c.army_f = GREATEST(c.army_f, 100000000);

INSERT INTO player_item (id_player, id_item, amount, bound)
SELECT t.id_player, i.id_item, 1000, 'T'
FROM _seed_target_player t
CROSS JOIN _seed_item_catalog i
WHERE 1 = 1
ON DUPLICATE KEY UPDATE amount = GREATEST(amount, VALUES(amount));

INSERT INTO player_equip (id_player, id_hero, type, part, lvl, on_hero)
SELECT t.id_player, 0, e.type, e.part, e.lvl, 0
FROM _seed_target_player t
CROSS JOIN _seed_equipment_catalog e
CROSS JOIN _seed_piece_number n
LEFT JOIN (
    SELECT pe.id_player, pe.type, pe.part, pe.lvl, COUNT(*) AS owned_count
    FROM player_equip pe
    JOIN _seed_target_player target ON target.id_player = pe.id_player
    WHERE (pe.id_hero = 0 OR pe.id_hero IS NULL)
      AND COALESCE(pe.on_hero, 0) = 0
    GROUP BY pe.id_player, pe.type, pe.part, pe.lvl
) owned ON owned.id_player = t.id_player
       AND owned.type = e.type
       AND owned.part = e.part
       AND owned.lvl = e.lvl
WHERE n.piece > COALESCE(owned.owned_count, 0);

INSERT INTO city_hero (
    id_city, id_player, name, lvl, avatar, exp, ultra_p,
    point_a, point_b, point_c, p_b_a, p_b_b, p_b_c,
    power, power_max, loyal, in_city
)
SELECT tc.id_city, tc.id_player, 'Test Hero Level 255', 255, 1, 0, 1,
       106, 101, 101, 21, 16, 17, 100, 560, 80, 1
FROM _seed_target_city tc
WHERE NOT EXISTS (
    SELECT 1 FROM city_hero existing
    WHERE existing.id_player = tc.id_player
      AND existing.name = 'Test Hero Level 255'
      AND existing.lvl = 255
)
AND (
    SELECT COUNT(*) FROM city_hero current_heroes WHERE current_heroes.id_city = tc.id_city
) < GREATEST(
    3,
    COALESCE((
        SELECT MAX(b.lvl) FROM city_building b
        WHERE b.id_city = tc.id_city AND (b.type = 12 OR b.place = 'palace')
    ), 0)
);

INSERT IGNORE INTO hero_army (id_hero, id_player)
SELECT h.id_hero, h.id_player
FROM city_hero h
JOIN _seed_target_player t ON t.id_player = h.id_player
WHERE h.name = 'Test Hero Level 255' AND h.lvl = 255;

INSERT IGNORE INTO hero_medal (id_hero, id_player)
SELECT h.id_hero, h.id_player
FROM city_hero h
JOIN _seed_target_player t ON t.id_player = h.id_player
WHERE h.name = 'Test Hero Level 255' AND h.lvl = 255;

UPDATE city c
JOIN _seed_target_player t ON t.id_player = c.id_player
SET c.food_out =
        c.army_a * 4 + c.army_b * 18 + c.army_c * 36 +
        c.army_d * 5 + c.army_e * 20 + c.army_f * 150 +
        COALESCE((
            SELECT SUM(
                CASE a.f_1_type WHEN 1 THEN a.f_1_num * 4 WHEN 2 THEN a.f_1_num * 18 WHEN 3 THEN a.f_1_num * 5 WHEN 4 THEN a.f_1_num * 36 WHEN 5 THEN a.f_1_num * 20 WHEN 6 THEN a.f_1_num * 150 ELSE 0 END +
                CASE a.f_2_type WHEN 1 THEN a.f_2_num * 4 WHEN 2 THEN a.f_2_num * 18 WHEN 3 THEN a.f_2_num * 5 WHEN 4 THEN a.f_2_num * 36 WHEN 5 THEN a.f_2_num * 20 WHEN 6 THEN a.f_2_num * 150 ELSE 0 END +
                CASE a.f_3_type WHEN 1 THEN a.f_3_num * 4 WHEN 2 THEN a.f_3_num * 18 WHEN 3 THEN a.f_3_num * 5 WHEN 4 THEN a.f_3_num * 36 WHEN 5 THEN a.f_3_num * 20 WHEN 6 THEN a.f_3_num * 150 ELSE 0 END +
                CASE a.b_1_type WHEN 1 THEN a.b_1_num * 4 WHEN 2 THEN a.b_1_num * 18 WHEN 3 THEN a.b_1_num * 5 WHEN 4 THEN a.b_1_num * 36 WHEN 5 THEN a.b_1_num * 20 WHEN 6 THEN a.b_1_num * 150 ELSE 0 END +
                CASE a.b_2_type WHEN 1 THEN a.b_2_num * 4 WHEN 2 THEN a.b_2_num * 18 WHEN 3 THEN a.b_2_num * 5 WHEN 4 THEN a.b_2_num * 36 WHEN 5 THEN a.b_2_num * 20 WHEN 6 THEN a.b_2_num * 150 ELSE 0 END +
                CASE a.b_3_type WHEN 1 THEN a.b_3_num * 4 WHEN 2 THEN a.b_3_num * 18 WHEN 3 THEN a.b_3_num * 5 WHEN 4 THEN a.b_3_num * 36 WHEN 5 THEN a.b_3_num * 20 WHEN 6 THEN a.b_3_num * 150 ELSE 0 END
            )
            FROM city_hero h
            LEFT JOIN hero_army a ON a.id_hero = h.id_hero
            WHERE h.id_city = c.id_city
              AND (h.in_city = 1 OR h.in_city IS NULL)
        ), 0),
    c.coin_out = COALESCE((
        SELECT SUM(h.lvl * 20)
        FROM city_hero h
        WHERE h.id_city = c.id_city
          AND (h.in_city = 1 OR h.in_city IS NULL)
    ), 0);

COMMIT;

SELECT DATABASE() AS database_name,
       @seed_target_username AS target_username,
       (SELECT COUNT(*) FROM _seed_target_player) AS matched_players,
       (SELECT id_player FROM _seed_target_player LIMIT 1) AS target_player_id;
SELECT p.id_player, p.porm, p.rank, p.user_group, p.gold, p.prestige, p.honor,
       (SELECT COUNT(*) FROM city c WHERE c.id_player = p.id_player) AS city_count,
       (SELECT COUNT(*) FROM city_hero h WHERE h.id_player = p.id_player) AS hero_count
FROM _seed_target_player t
JOIN player p ON p.id_player = t.id_player
GROUP BY p.id_player, p.porm, p.rank, p.user_group, p.gold, p.prestige, p.honor;
SELECT c.id_city, c.coin, c.food, c.wood, c.stone, c.metal,
       c.army_a, c.army_b, c.army_c, c.army_d, c.army_e, c.army_f,
       c.food_out
FROM city c
JOIN _seed_target_city tc ON tc.id_city = c.id_city;
SELECT COUNT(*) AS seeded_item_rows, MIN(p.amount) AS min_seeded_item_amount
FROM player_item p
JOIN _seed_target_player t ON t.id_player = p.id_player
JOIN _seed_item_catalog i ON i.id_item = p.id_item;
SELECT COUNT(*) AS equipment_variants,
       MIN(owned.pieces) AS minimum_pieces_per_variant,
       SUM(owned.pieces) AS total_seeded_equipment_pieces
FROM (
    SELECT e.type, e.part, e.lvl, COUNT(p.id_equip) AS pieces
    FROM _seed_equipment_catalog e
    CROSS JOIN _seed_target_player t
    LEFT JOIN player_equip p ON p.id_player = t.id_player
                            AND p.type = e.type
                            AND p.part = e.part
                            AND p.lvl = e.lvl
                            AND (p.id_hero = 0 OR p.id_hero IS NULL)
                            AND COALESCE(p.on_hero, 0) = 0
    GROUP BY e.type, e.part, e.lvl
) owned;
SELECT h.id_hero, h.lvl, h.point_a, h.point_b, h.point_c, h.points, h.ultra_p,
       e.leader AS leadership_study,
       (30000 + h.point_a * 500) +
       CEIL((30000 + h.point_a * 500) * (COALESCE(e.leader, 0) * 3 / 100.0)) +
       IF(COALESCE(hm.medal_ceasro, 0) > UNIX_TIMESTAMP(), FLOOR(h.point_a * 500 * 0.25), 0) +
       IF(COALESCE(hm.ceaser_eagle, 0) > UNIX_TIMESTAMP(), FLOOR(h.point_a * 500 * 0.25), 0)
           AS actual_army_capacity
FROM city_hero h
JOIN _seed_target_player t ON t.id_player = h.id_player
LEFT JOIN player_edu e ON e.id_player = h.id_player
LEFT JOIN hero_medal hm ON hm.id_hero = h.id_hero
WHERE h.name = 'Test Hero Level 255' AND h.lvl = 255;
