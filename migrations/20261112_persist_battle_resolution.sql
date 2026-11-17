CREATE INDEX `idx_timed_task_type_arrival`
    ON `timed_task` (`task_type`, `time_arrive`, `id_task`);
