BEGIN;

-- ============================================================
-- 011 - ÍNDICES PARA CONSULTAS FINANCEIRAS
-- Init SaaS Platform
--
-- Objetivo:
-- preparar o Financeiro para crescimento sem criar excesso de
-- índices e sem duplicar os índices simples que já existem.
--
-- Consultas principais atendidas:
-- - cobranças a receber;
-- - cobranças vencidas;
-- - previsão de caixa;
-- - pagamentos confirmados por período.
--
-- Não criamos índices específicos para MRR por produto/plano
-- neste momento porque isso aumentaria custo de escrita sem
-- ganho comprovado na base atual. Esses agregados serão medidos
-- novamente quando houver volume real.
-- ============================================================


-- ------------------------------------------------------------
-- 1. ASSINATURAS
-- ------------------------------------------------------------
-- As consultas financeiras trabalham quase sempre com:
--
-- status IN ('ATIVA', 'ATRASADA', 'SUSPENSA')
-- +
-- proxima_cobranca_em por intervalo/data.
--
-- O índice parcial reduz seu tamanho e deixa de carregar:
-- - PENDENTE_ATIVACAO
-- - CANCELADA
-- - demais estados sem cobrança financeira corrente.
--
-- Também favorece a previsão, que usa:
-- status = 'ATIVA'
-- + faixa de proxima_cobranca_em.
-- ------------------------------------------------------------

CREATE INDEX IF NOT EXISTS idx_assinaturas_financeiro_cobranca
    ON public.assinaturas (
        status,
        proxima_cobranca_em
    )
    WHERE
        proxima_cobranca_em IS NOT NULL
        AND status IN (
            'ATIVA',
            'ATRASADA',
            'SUSPENSA'
        );


-- ------------------------------------------------------------
-- 2. PAGAMENTOS
-- ------------------------------------------------------------
-- O Financeiro calcula receita realizada somente a partir de:
--
-- status = 'CONFIRMADO'
-- +
-- pago_em dentro do período.
--
-- Já existe um índice geral em pago_em. Este índice parcial é
-- menor e especializado para a consulta financeira recorrente.
--
-- ESTORNADO fica fora, portanto não polui a leitura do realizado.
-- ------------------------------------------------------------

CREATE INDEX IF NOT EXISTS idx_assinatura_pagamentos_financeiro_confirmado
    ON public.assinatura_pagamentos (
        pago_em DESC
    )
    WHERE status = 'CONFIRMADO';


COMMIT;


-- ============================================================
-- VERIFICAÇÃO
-- Mantida no final da própria migration conforme padrão atual.
-- ============================================================

SELECT
    indexname,
    indexdef
FROM pg_indexes
WHERE schemaname = 'public'
  AND tablename = 'assinaturas'
  AND indexname = 'idx_assinaturas_financeiro_cobranca';


SELECT
    indexname,
    indexdef
FROM pg_indexes
WHERE schemaname = 'public'
  AND tablename = 'assinatura_pagamentos'
  AND indexname = 'idx_assinatura_pagamentos_financeiro_confirmado';


-- ------------------------------------------------------------
-- Inventário dos índices relacionados ao Financeiro.
-- Serve para conferirmos que não estamos removendo os índices
-- anteriores nem criando duplicações acidentais.
-- ------------------------------------------------------------

SELECT
    tablename,
    indexname,
    indexdef
FROM pg_indexes
WHERE schemaname = 'public'
  AND tablename IN (
      'assinaturas',
      'assinatura_pagamentos'
  )
ORDER BY
    tablename,
    indexname;
