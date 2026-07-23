ALTER TABLE people
    ADD COLUMN default_monthly_capacity_hours DECIMAL(8,2) NOT NULL DEFAULT 125.00 AFTER is_active;

CREATE TABLE IF NOT EXISTS person_month_capacity_overrides (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    person_id INT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    available_hours DECIMAL(8,2) NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY person_capacity_overrides_person_period_unique (person_id, year, month),
    KEY person_capacity_overrides_person_index (person_id),
    KEY person_capacity_overrides_period_index (year, month),
    CONSTRAINT person_capacity_overrides_person_foreign FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE CASCADE,
    CONSTRAINT person_capacity_overrides_year_check CHECK (year BETWEEN 2000 AND 2100),
    CONSTRAINT person_capacity_overrides_month_check CHECK (month BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_versions (version, description)
SELECT '008', 'Add standard person capacity and monthly overrides'
WHERE NOT EXISTS (SELECT 1 FROM schema_versions WHERE version = '008');
