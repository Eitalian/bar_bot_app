-- Read-only role for Claude Code data exploration.
-- Idempotent: safe to re-run.

DO $$
BEGIN
   IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'claude_ro') THEN
      CREATE ROLE claude_ro WITH LOGIN PASSWORD 'claude_ro';
   END IF;
END
$$;

GRANT CONNECT ON DATABASE bar_bot_app TO claude_ro;
GRANT USAGE ON SCHEMA public TO claude_ro;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO claude_ro;
ALTER DEFAULT PRIVILEGES FOR ROLE root IN SCHEMA public GRANT SELECT ON TABLES TO claude_ro;
