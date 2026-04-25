-- Add 'cancelled' to the appointments status check constraint (PostgreSQL)
-- Run this in your Supabase SQL Editor or psql

DO $$
DECLARE
    constraint_name TEXT;
BEGIN
    -- Find the check constraint on the status column
    SELECT tc.constraint_name INTO constraint_name
    FROM information_schema.table_constraints tc
    JOIN information_schema.constraint_column_usage ccu
        ON tc.constraint_name = ccu.constraint_name
    WHERE tc.table_name = 'appointments'
      AND tc.constraint_type = 'CHECK'
      AND ccu.column_name = 'status';

    IF constraint_name IS NOT NULL THEN
        EXECUTE format('ALTER TABLE appointments DROP CONSTRAINT %I', constraint_name);
    END IF;

    -- Add the new check constraint with all valid statuses including cancelled
    ALTER TABLE appointments
    ADD CONSTRAINT appointments_status_check
    CHECK (status IN ('pending', 'approved', 'rejected', 'completed', 'no_show', 'cancelled'));
END $$;

