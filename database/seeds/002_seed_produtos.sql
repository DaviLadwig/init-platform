-- ============================================================
-- INIT SAAS PLATFORM
-- Seed: 002_seed_produtos.sql
--
-- Produtos SaaS iniciais da Init Sistemas.
-- ============================================================

INSERT INTO public.produtos (
    codigo,
    nome,
    slug,
    descricao,
    ativo
)
VALUES
(
    'INIT_RH',
    'Init RH',
    'init-rh',
    'Sistema de Recursos Humanos e Departamento Pessoal.',
    TRUE
),
(
    'INIT_CLINIC',
    'Init Clinic',
    'init-clinic',
    'Sistema de gestão para clínicas e serviços de saúde.',
    TRUE
)
ON CONFLICT (codigo)
DO NOTHING;