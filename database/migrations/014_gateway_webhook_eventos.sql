BEGIN;

-- ============================================================
-- 014 - FILA IDEMPOTENTE DE WEBHOOKS ASAAS
-- ============================================================
--
-- O payload completo do Asaas NÃO é armazenado.
-- Persistimos somente os campos necessários para:
-- - idempotência;
-- - conciliação;
-- - processamento financeiro;
-- - diagnóstico operacional.
-- ============================================================

CREATE TABLE IF NOT EXISTS public.gateway_webhook_eventos (
    id BIGSERIAL PRIMARY KEY,

    gateway VARCHAR(30) NOT NULL,

    ambiente VARCHAR(20) NOT NULL,

    event_id VARCHAR(200) NOT NULL,

    event_type VARCHAR(60) NOT NULL,

    payment_id VARCHAR(120) NOT NULL,

    gateway_subscription_id
        VARCHAR(120) NOT NULL,

    vencimento_referencia DATE NOT NULL,

    valor NUMERIC(14,2) NOT NULL,

    billing_type VARCHAR(30) NOT NULL,

    payment_status VARCHAR(30) NOT NULL,

    payment_date DATE NULL,

    status VARCHAR(20) NOT NULL
        DEFAULT 'PENDENTE',

    tentativas SMALLINT NOT NULL
        DEFAULT 0,

    resultado_codigo VARCHAR(80) NULL,

    recebido_em TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),

    processado_em TIMESTAMPTZ NULL,

    criado_em TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),

    atualizado_em TIMESTAMPTZ NOT NULL
        DEFAULT NOW(),

    CONSTRAINT ck_gateway_webhook_eventos_gateway
        CHECK (
            gateway ~
                '^[A-Z][A-Z0-9_]{1,29}$'
        ),

    CONSTRAINT ck_gateway_webhook_eventos_ambiente
        CHECK (
            ambiente IN (
                'SANDBOX',
                'PRODUCTION'
            )
        ),

    CONSTRAINT ck_gateway_webhook_eventos_event_id
        CHECK (
            event_id ~
                '^[A-Za-z0-9_&.\-]{3,200}$'
        ),

    CONSTRAINT ck_gateway_webhook_eventos_event_type
        CHECK (
            event_type IN (
                'PAYMENT_CONFIRMED',
                'PAYMENT_RECEIVED'
            )
        ),

    CONSTRAINT ck_gateway_webhook_eventos_payment_id
        CHECK (
            payment_id ~
                '^pay_[A-Za-z0-9_-]{3,116}$'
        ),

    CONSTRAINT ck_gateway_webhook_eventos_subscription_id
        CHECK (
            gateway_subscription_id ~
                '^sub_[A-Za-z0-9_-]{3,116}$'
        ),

    CONSTRAINT ck_gateway_webhook_eventos_valor
        CHECK (
            valor > 0
        ),

    CONSTRAINT ck_gateway_webhook_eventos_billing
        CHECK (
            billing_type IN (
                'PIX',
                'BOLETO',
                'CREDIT_CARD',
                'UNDEFINED'
            )
        ),

    CONSTRAINT ck_gateway_webhook_eventos_status
        CHECK (
            status IN (
                'PENDENTE',
                'PROCESSANDO',
                'PROCESSADO',
                'ERRO'
            )
        ),

    CONSTRAINT ck_gateway_webhook_eventos_tentativas
        CHECK (
            tentativas >= 0
            AND tentativas <= 100
        ),

    CONSTRAINT uq_gateway_webhook_eventos_event
        UNIQUE (
            gateway,
            ambiente,
            event_id
        )
);

CREATE INDEX IF NOT EXISTS
    idx_gateway_webhook_eventos_fila
ON public.gateway_webhook_eventos (
    status,
    recebido_em,
    id
);

CREATE INDEX IF NOT EXISTS
    idx_gateway_webhook_eventos_payment
ON public.gateway_webhook_eventos (
    gateway,
    ambiente,
    payment_id
);

CREATE INDEX IF NOT EXISTS
    idx_gateway_webhook_eventos_subscription
ON public.gateway_webhook_eventos (
    gateway,
    ambiente,
    gateway_subscription_id
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
      'gateway_webhook_eventos'
ORDER BY ordinal_position;


SELECT
    conname AS constraint_name,
    pg_get_constraintdef(oid) AS definition
FROM pg_constraint
WHERE conrelid =
    'public.gateway_webhook_eventos'::regclass
ORDER BY conname;


SELECT
    indexname,
    indexdef
FROM pg_indexes
WHERE schemaname = 'public'
  AND tablename =
      'gateway_webhook_eventos'
ORDER BY indexname;
