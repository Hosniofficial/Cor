-- Migration: Add attack_cooldown_until to city table
-- Used by Targeted City Move (certain_move) to enforce 24-hour attack/expedition cooldown
-- matching raw game rule (buff.moveCity.coolDown / moveCastleAdv.funcDesc).

ALTER TABLE `city`
    ADD COLUMN `attack_cooldown_until` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `spies`;
