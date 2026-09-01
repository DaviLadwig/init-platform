BEGIN;

-- ============================================================
-- 013 - VÍNCULO ASSINATURA INIT x ASSINATURA RECORRENTE GATEWAY
-- ============================================================
--
-- A recorrência comercial da Init passa a possuir um vínculo
-- canônico por gateway e ambiente.
--
-- Não usamos apenas assinaturas.gateway_subscription_id porque
-- esse campo não diferencia SANDBOX de PRODUCTION.
--
-- Os campos antigos de gateway em public.assinaturas NÃO são
-- removidos nesta migration.
-- ============================================================

CREATE TABLE IF NOT EXISTS public.assinatura_gateway_assinaturas (
    id BIGSERIAL PRIMARY KEY,

    assinatura_id BIGINT NOT NULL,

    gateway VARCHAR(30) NOT NULL,

    ambiente VARCHAR(20) NOT NULL,

    gateway_subscription_id VARCHAR(120) NOT NULL,

    external_reference VARCHAR(120) NOT NULL,

    billing_type VARCHAR(30) NOT NULL,

    cycle VARCHAR(30) NOT NULL,

    sincronizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_assinatura_gateway_assinaturas_assinatura
        FOREIGN KEY (assinatura_id)
        REFERENCES public.assinaturas (id)
        ON DELETE RESTRICT,

    CONSTRAINT ck_assinatura_gateway_assinaturas_gateway
        CHECK (
            gateway ~ '^[A-Z][A-Z0-9_]{1,29}$'
        ),

    CONSTRAINT ck_assinatura_gateway_assinaturas_ambiente
        CHECK (
            ambiente IN (
                'SANDBOX',
                'PRODUCTION'
            )
        ),

    CONSTRAINT ck_assinatura_gateway_assinaturas_remote_id
        CHECK (
            gateway_subscription_id ~
                '^[A-Za-z0-9_-]{3,120}$'
        ),

    CONSTRAINT ck_assinatura_gateway_assinaturas_reference
        CHECK (
            external_reference ~
                '^[A-Za-z0-9_-]{3,120}$'
        ),

    CONSTRAINT ck_assinatura_gateway_assinaturas_billing_type
        CHECK (
            billing_type IN (
                'UNDEFINED',
                'BOLETO',
                'CREDIT_CARD',
                'PIX'
            )
        ),

    CONSTRAINT ck_assinatura_gateway_assinaturas_cycle
        CHECK (
            cycle IN (
                'MONTHLY',
                'QUARTERLY',
                'SEMIANNUALLY',
                'YEARLY'
            )
        ),

    CONSTRAINT uq_assinatura_gateway_assinaturas_local
        UNIQUE (
            assinatura_id,
            gateway,
            ambiente
        ),

    CONSTRAINT uq_assinatura_gateway_assinaturas_remote
        UNIQUE (
            gateway,
            ambiente,
            gateway_subscription_id
        ),

    CONSTRAINT uq_assinatura_gateway_assinaturas_reference
        UNIQUE (
            gateway,
            ambiente,
            external_reference
        )
);

CREATE INDEX IF NOT EXISTS idx_assinatura_gateway_assinaturas_assinatura
    ON public.assinatura_gateway_assinaturas (
        assinatura_id
    );

CREATE INDEX IF NOT EXISTS idx_assinatura_gateway_assinaturas_gateway_ambiente
    ON public.assinatura_gateway_assinaturas (
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
  AND table_name =
      'assinatura_gateway_assinaturas'
ORDER BY ordinal_position;


SELECT
    conname AS constraint_name,
    pg_get_constraintdef(oid) AS definition
FROM pg_constraint
WHERE conrelid =
    'public.assinatura_gateway_assinaturas'::regclass
ORDER BY conname;


SELECT
    indexname,
    indexdef
FROM pg_indexes
WHERE schemaname = 'public'
  AND tablename =
      'assinatura_gateway_assinaturas'
ORDER BY indexname;
