ALTER TABLE projects
    ADD COLUMN hours_per_pm DECIMAL(8,2) NOT NULL DEFAULT 125.00 AFTER currency;

CREATE TABLE IF NOT EXISTS person_hour_allocations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_participant_id INT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    planned_hours DECIMAL(8,2) NULL,
    actual_hours DECIMAL(8,2) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY person_hour_allocations_participant_period_unique (project_participant_id, year, month),
    KEY person_hour_allocations_participant_index (project_participant_id),
    KEY person_hour_allocations_period_index (year, month),
    CONSTRAINT person_hour_allocations_participant_foreign
        FOREIGN KEY (project_participant_id) REFERENCES project_participants (id) ON DELETE RESTRICT,
    CONSTRAINT person_hour_allocations_year_check CHECK (year BETWEEN 2000 AND 2100),
    CONSTRAINT person_hour_allocations_month_check CHECK (month BETWEEN 1 AND 12),
    CONSTRAINT person_hour_allocations_values_check CHECK (planned_hours IS NOT NULL OR actual_hours IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_versions (version, description)
SELECT '007', 'Add project hours-per-PM and monthly person-hour allocations'
WHERE NOT EXISTS (SELECT 1 FROM schema_versions WHERE version = '007');
