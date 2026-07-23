-- Apply once after migration 002.
-- Existing rows receive deterministic, valid, unique usernames in the form user-{id}.
ALTER TABLE users ADD COLUMN username VARCHAR(50) NULL AFTER id;

UPDATE users
SET username = CONCAT('user-', id)
WHERE username IS NULL;

ALTER TABLE users
    MODIFY username VARCHAR(50) NOT NULL,
    ADD UNIQUE KEY users_username_unique (username);

INSERT INTO schema_versions (version, description)
SELECT '003', 'Add unique usernames to users'
WHERE NOT EXISTS (
    SELECT 1 FROM schema_versions WHERE version = '003'
);
