CREATE TABLE IF NOT EXISTS project_participants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    person_id INT UNSIGNED NOT NULL,
    project_role VARCHAR(40) NOT NULL,
    participation_start DATE NULL,
    participation_end DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY project_participants_project_person_unique (project_id, person_id),
    KEY project_participants_project_index (project_id),
    KEY project_participants_person_index (person_id),
    KEY project_participants_role_index (project_role),
    KEY project_participants_active_index (is_active),
    KEY project_participants_start_index (participation_start),
    KEY project_participants_end_index (participation_end),
    CONSTRAINT project_participants_project_foreign FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT project_participants_person_foreign FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_versions (version, description)
SELECT '006', 'Create project participants'
WHERE NOT EXISTS (
    SELECT 1 FROM schema_versions WHERE version = '006'
);
