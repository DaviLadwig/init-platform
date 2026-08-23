-- ============================================================
-- INIT SAAS PLATFORM
-- Seed: 001_seed_roles.sql
--
-- Perfis administrativos iniciais.
-- ============================================================

INSERT INTO public.roles (
    codigo,
    nome,
    ativo
)
VALUES (
    'SUPER_ADMIN',
    'Super Administrador',
    TRUE
)
ON CONFLICT (codigo)
DO NOTHING;