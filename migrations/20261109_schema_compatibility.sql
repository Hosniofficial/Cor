-- Preserve the established capacity and semantics of legacy columns while
-- retaining the primary keys and indexes required by the current application.
-- These widening changes are safe to apply repeatedly and preserve existing data.

ALTER TABLE `guild`
    MODIFY `prestige` BIGINT NOT NULL DEFAULT 0,
    MODIFY `honor` BIGINT NOT NULL DEFAULT 0,
    MODIFY `slog_top` VARCHAR(100) NOT NULL DEFAULT '1',
    MODIFY `slog_cnt` VARCHAR(100) NOT NULL DEFAULT '1',
    MODIFY `slog_btm` VARCHAR(100) NOT NULL DEFAULT '1',
    MODIFY `food` BIGINT NOT NULL DEFAULT 0,
    MODIFY `wood` BIGINT NOT NULL DEFAULT 0,
    MODIFY `stone` BIGINT NOT NULL DEFAULT 0,
    MODIFY `metal` BIGINT NOT NULL DEFAULT 0,
    MODIFY `coin` BIGINT NOT NULL DEFAULT 0;

ALTER TABLE `guild_req`
    MODIFY `type` VARCHAR(10) NOT NULL DEFAULT 'req';

ALTER TABLE `report_player`
    MODIFY `detail` LONGTEXT NULL;

ALTER TABLE `spy_report`
    MODIFY `spy_for` VARCHAR(50) NOT NULL DEFAULT 'city',
    MODIFY `content` LONGTEXT NULL;
