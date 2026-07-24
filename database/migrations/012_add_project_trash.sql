ALTER TABLE projects
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
    ADD COLUMN deleted_by_user_id INT UNSIGNED NULL AFTER deleted_at,
    ADD KEY projects_deleted_at_index (deleted_at),
    ADD KEY projects_deleted_by_index (deleted_by_user_id),
    ADD CONSTRAINT projects_deleted_by_foreign
        FOREIGN KEY (deleted_by_user_id) REFERENCES users (id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS project_deletion_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    project_name VARCHAR(305) NOT NULL,
    project_code VARCHAR(100) NOT NULL,
    action VARCHAR(40) NOT NULL,
    acting_user_id INT UNSIGNED NULL,
    dependency_counts JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY project_deletion_audit_project_index (project_id),
    KEY project_deletion_audit_action_index (action),
    KEY project_deletion_audit_created_index (created_at),
    CONSTRAINT project_deletion_audit_user_foreign
        FOREIGN KEY (acting_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT project_deletion_audit_action_check
        CHECK (action IN ('project_soft_deleted','project_restored','project_permanently_deleted'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_versions (version, description)
SELECT '012', 'Add safe project Trash and independent deletion audit'
WHERE NOT EXISTS (SELECT 1 FROM schema_versions WHERE version = '012');
