CREATE TABLE IF NOT EXISTS work_packages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    code VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    responsible_participant_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY work_packages_project_code_unique (project_id, code),
    KEY work_packages_project_active_index (project_id, is_active),
    KEY work_packages_responsible_index (responsible_participant_id),
    KEY work_packages_start_index (start_date),
    KEY work_packages_end_index (end_date),
    CONSTRAINT work_packages_project_foreign FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT work_packages_responsible_foreign FOREIGN KEY (responsible_participant_id) REFERENCES project_participants (id) ON DELETE RESTRICT,
    CONSTRAINT work_packages_dates_check CHECK (start_date IS NULL OR end_date IS NULL OR start_date <= end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_versions (version, description)
SELECT '009', 'Create project Work Package registry'
WHERE NOT EXISTS (SELECT 1 FROM schema_versions WHERE version = '009');
