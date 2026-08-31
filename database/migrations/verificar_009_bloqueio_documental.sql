-- ============================================================
-- VERIFICAÇÃO DA MIGRATION 009
-- ============================================================

-- 1. Trigger
SELECT
    event_object_table AS tabela,
    trigger_name,
    event_manipulation AS evento
FROM information_schema.triggers
WHERE trigger_schema = 'public'
  AND event_object_table = 'assinaturas'
  AND trigger_name = 'trg_validar_documentos_ativacao_assinatura';


-- 2. Função
SELECT
    p.proname AS funcao
FROM pg_proc AS p
INNER JOIN pg_namespace AS n
    ON n.oid = p.pronamespace
WHERE n.nspname = 'public'
  AND p.proname = 'fn_validar_documentos_ativacao_assinatura';


-- 3. Documentos obrigatórios ativos atuais
SELECT
    dc.id,
    dc.produto_id,
    dc.tipo,
    dc.titulo,
    dc.versao,
    dc.obrigatorio_ativacao,
    dc.ativo
FROM public.documentos_contratuais AS dc
WHERE dc.ativo = TRUE
  AND dc.obrigatorio_ativacao = TRUE
ORDER BY
    dc.tipo,
    dc.produto_id NULLS FIRST,
    dc.id;
