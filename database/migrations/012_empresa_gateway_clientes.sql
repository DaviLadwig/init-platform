BEGIN;

-- ============================================================
-- 012 - VÍNCULO EMPRESA x CLIENTE DO GATEWAY
-- ============================================================
--
-- O customer do Asaas pertence à EMPRESA pagadora, não a uma
-- assinatura individual.
--
-- Uma mesma empresa poderá contratar:
-- - Init RH;
-- - Init Clinic;
-- - futuros produtos.
--
-- E continuará utilizando o mesmo customer no mesmo gateway/
-- ambiente.
--
-- Sandbox e Produção são armazenados separadamente porque seus
-- identificadores externos não são compartilhados.
-- ============================================================

CREATE TABLE IF NOT EXISTS public.empresa_gateway_clientes (
    id BIGSERIAL PRIMARY KEY,

    empresa_id BIGINT NOT NULL,

    gateway VARCHAR(30) NOT NULL,

    ambiente VARCHAR(20) NOT NULL,

    gateway_customer_id VARCHAR(120) NOT NULL,

    external_reference VARCHAR(120) NOT NULL,

    sincronizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_empresa_gateway_clientes_empresa
        FOREIGN KEY (empresa_id)
        REFERENCES public.empresas (id)
        ON DELETE RESTRICT,

    CONSTRAINT ck_empresa_gateway_clientes_gateway
        CHECK (
            gateway ~ '^[A-Z][A-Z0-9_]{1,29}$'
        ),

    CONSTRAINT ck_empresa_gateway_clientes_ambiente
        CHECK (
            ambiente IN (
                'SANDBOX',
                'PRODUCTION'
            )
        ),

    CONSTRAINT ck_empresa_gateway_clientes_customer_id
        CHECK (
            gateway_customer_id ~
                '^[A-Za-z0-9_-]{3,120}$'
        ),

    CONSTRAINT ck_empresa_gateway_clientes_external_reference
        CHECK (
            external_reference ~
                '^[A-Za-z0-9_-]{3,120}$'
        ),

    CONSTRAINT uq_empresa_gateway_clientes_empresa
        UNIQUE (
            empresa_id,
            gateway,
            ambiente
        ),

    CONSTRAINT uq_empresa_gateway_clientes_customer
        UNIQUE (
            gateway,
            ambiente,
            gateway_customer_id
        ),

    CONSTRAINT uq_empresa_gateway_clientes_external_reference
        UNIQUE (
            gateway,
            ambiente,
            external_reference
        )
);

CREATE INDEX IF NOT EXISTS idx_empresa_gateway_clientes_empresa
    ON public.empresa_gateway_clientes (
        empresa_id
    );

CREATE INDEX IF NOT EXISTS idx_empresa_gateway_clientes_gateway_ambiente
    ON public.empresa_gateway_clientes (
        gateway,
        ambiente
    );

COMMIT;


-- ============================================================
-- VERIFICAÇÃO
-- ============================================================

SELECT
    column_name,
    data_type,
    is_nullable,
    column_default
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'empresa_gateway_clientes'
ORDER BY ordinal_position;


SELECT
    conname AS constraint_name,
    pg_get_constraintdef(oid) AS definition
FROM pg_constraint
WHERE conrelid =
    'public.empresa_gateway_clientes'::regclass
ORDER BY conname;


SELECT
    indexname,
    indexdef
FROM pg_indexes
WHERE schemaname = 'public'
  AND tablename = 'empresa_gateway_clientes'
ORDER BY indexname;
