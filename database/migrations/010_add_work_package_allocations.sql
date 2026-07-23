ALTER TABLE person_hour_allocations
    DROP INDEX person_hour_allocations_participant_period_unique,
    ADD COLUMN work_package_id INT UNSIGNED NULL AFTER project_participant_id,
    ADD COLUMN work_package_key INT UNSIGNED NOT NULL DEFAULT 0 AFTER work_package_id,
    ADD KEY person_hour_allocations_work_package_index (work_package_id),
    ADD KEY person_hour_allocations_project_period_index (year, month, project_participant_id),
    ADD UNIQUE KEY person_hour_allocations_participant_wp_period_unique
        (project_participant_id, work_package_key, year, month),
    ADD CONSTRAINT person_hour_allocations_work_package_foreign
        FOREIGN KEY (work_package_id) REFERENCES work_packages (id) ON DELETE RESTRICT,
    ADD CONSTRAINT person_hour_allocations_work_package_key_check
        CHECK (
            (work_package_id IS NULL AND work_package_key = 0)
            OR
            (work_package_id IS NOT NULL AND work_package_key = work_package_id)
        );

INSERT INTO schema_versions (version, description)
SELECT '010', 'Allow monthly person-hour allocation breakdown by Work Package'
WHERE NOT EXISTS (SELECT 1 FROM schema_versions WHERE version = '010');
