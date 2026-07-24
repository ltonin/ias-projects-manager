ALTER TABLE people
    ADD COLUMN annual_capacity_hours DECIMAL(8,2) NULL AFTER default_monthly_capacity_hours;

UPDATE people
SET annual_capacity_hours = CASE
    WHEN position_type IN ('full_professor', 'associate_professor', 'assistant_professor', 'researcher')
        THEN 1150.00
    ELSE 1500.00
END
WHERE annual_capacity_hours IS NULL;

ALTER TABLE people
    MODIFY annual_capacity_hours DECIMAL(8,2) NOT NULL DEFAULT 1500.00;

INSERT INTO schema_versions (version, description)
SELECT '011', 'Add annual capacity to people'
WHERE NOT EXISTS (SELECT 1 FROM schema_versions WHERE version = '011');
