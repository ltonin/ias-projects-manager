-- Apply once through phpMyAdmin. The insert is guarded by the primary key.
CREATE TABLE IF NOT EXISTS schema_versions (
    version VARCHAR(20) NOT NULL,
    description VARCHAR(255) NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_versions (version, description)
SELECT '001', 'Create schema version infrastructure'
WHERE NOT EXISTS (
    SELECT 1 FROM schema_versions WHERE version = '001'
);
