BEGIN;

-- This app talks to Supabase from the server as the postgres owner role,
-- so we can safely deny all browser-facing access by enabling RLS without
-- adding anon/authenticated policies.
ALTER TABLE IF EXISTS public.users ENABLE ROW LEVEL SECURITY;
ALTER TABLE IF EXISTS public.services ENABLE ROW LEVEL SECURITY;
ALTER TABLE IF EXISTS public.appointments ENABLE ROW LEVEL SECURITY;
ALTER TABLE IF EXISTS public.appointment_teeth ENABLE ROW LEVEL SECURITY;

COMMIT;
