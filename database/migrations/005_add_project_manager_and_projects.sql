ALTER TABLE users DROP CHECK users_role_check;
ALTER TABLE users
    ADD CONSTRAINT users_role_check
    CHECK (role IN ('admin', 'project_manager', 'participant', 'viewer'));

CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    acronym VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    internal_code VARCHAR(100) NULL,
    grant_agreement_number VARCHAR(100) NULL,
    funding_agency VARCHAR(255) NULL,
    funding_programme VARCHAR(255) NULL,
    coordinator_organization VARCHAR(255) NULL,
    manager_person_id INT UNSIGNED NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    status VARCHAR(20) NOT NULL,
    total_budget DECIMAL(15,2) NULL,
    currency CHAR(3) NULL,
    website_url VARCHAR(500) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY projects_acronym_unique (acronym),
    UNIQUE KEY projects_internal_code_unique (internal_code),
    UNIQUE KEY projects_grant_number_unique (grant_agreement_number),
    KEY projects_status_index (status),
    KEY projects_start_date_index (start_date),
    KEY projects_end_date_index (end_date),
    KEY projects_manager_index (manager_person_id),
    KEY projects_funding_agency_index (funding_agency),
    KEY projects_funding_programme_index (funding_programme),
    CONSTRAINT projects_manager_foreign FOREIGN KEY (manager_person_id) REFERENCES people (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_versions (version, description)
SELECT '005', 'Add project manager role and project registry'
WHERE NOT EXISTS (
    SELECT 1 FROM schema_versions WHERE version = '005'
);
