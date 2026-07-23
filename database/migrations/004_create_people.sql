CREATE TABLE IF NOT EXISTS people (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    institutional_email VARCHAR(255) NULL,
    affiliation VARCHAR(255) NULL,
    position_type VARCHAR(40) NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 1,
    active_from DATE NULL,
    active_to DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY people_user_unique (user_id),
    UNIQUE KEY people_institutional_email_unique (institutional_email),
    KEY people_name_index (last_name, first_name, id),
    KEY people_position_type_index (position_type),
    KEY people_active_index (is_active),
    KEY people_internal_index (is_internal),
    CONSTRAINT people_user_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_versions (version, description)
SELECT '004', 'Create people registry'
WHERE NOT EXISTS (
    SELECT 1 FROM schema_versions WHERE version = '004'
);
