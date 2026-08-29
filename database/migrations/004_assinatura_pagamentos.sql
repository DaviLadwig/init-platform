BEGIN;

CREATE TABLE IF NOT EXISTS public.assinatura_pagamentos (
    id BIGSERIAL PRIMARY KEY,

    assinatura_id BIGINT NOT NULL,

    /*
     * Cobrança que este pagamento está quitando.
     *
     * Não usamos pago_em como referência da competência,
     * pois o cliente pode pagar antes ou depois do vencimento.
     */
    vencimento_referencia DATE NOT NULL,

    /*
     * Snapshot financeiro do pagamento.
     *
     * O valor é copiado da assinatura pelo backend no momento
     * da confirmação. Nunca deve ser confiado a partir do navegador.
     */
    valor NUMERIC(12, 2) NOT NULL,

    moeda VARCHAR(3) NOT NULL DEFAULT 'BRL',

    /*
     * Esta tabela representa pagamentos efetivamente reconhecidos.
     * ESTORNADO fica disponível para uma futura integração financeira.
     */
    status VARCHAR(20) NOT NULL DEFAULT 'CONFIRMADO',

    /*
     * MANUAL:
     * registro administrativo feito pela Init Platform.
     *
     * GATEWAY:
     * confirmação recebida futuramente por integração/webhook.
     */
    origem VARCHAR(20) NOT NULL DEFAULT 'MANUAL',

    /*
     * Método é informativo e não contém dados sensíveis.
     *
     * Nunca armazenar PAN/número completo do cartão, CVV,
     * senha, token secreto ou dados bancários protegidos aqui.
     */
    metodo VARCHAR(30) NULL,

    pago_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    /*
     * Usuário interno que registrou manualmente o pagamento.
     * Pode ser NULL para registros automáticos vindos de gateway.
     */
    registrado_por BIGINT NULL,

    /*
     * Identificadores públicos/operacionais do gateway.
     * Segredos e API keys nunca são armazenados nesta tabela.
     */
    gateway VARCHAR(50) NULL,
    gateway_payment_id VARCHAR(255) NULL,

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_assinatura_pagamentos_assinatura
        FOREIGN KEY (assinatura_id)
        REFERENCES public.assinaturas(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_assinatura_pagamentos_usuario
        FOREIGN KEY (registrado_por)
        REFERENCES public.usuarios(id)
        ON DELETE RESTRICT,

    CONSTRAINT ck_assinatura_pagamentos_valor
        CHECK (valor >= 0),

    CONSTRAINT ck_assinatura_pagamentos_moeda
        CHECK (moeda ~ '^[A-Z]{3}$'),

    CONSTRAINT ck_assinatura_pagamentos_status
        CHECK (
            status IN (
                'CONFIRMADO',
                'ESTORNADO'
            )
        ),

    CONSTRAINT ck_assinatura_pagamentos_origem
        CHECK (
            origem IN (
                'MANUAL',
                'GATEWAY'
            )
        ),

    CONSTRAINT ck_assinatura_pagamentos_metodo
        CHECK (
            metodo IS NULL
            OR metodo IN (
                'PIX',
                'BOLETO',
                'CARTAO',
                'TRANSFERENCIA',
                'DINHEIRO',
                'OUTRO'
            )
        ),

    CONSTRAINT ck_assinatura_pagamentos_gateway
        CHECK (
            (
                origem = 'MANUAL'
                AND gateway IS NULL
                AND gateway_payment_id IS NULL
            )
            OR
            (
                origem = 'GATEWAY'
                AND gateway IS NOT NULL
                AND gateway_payment_id IS NOT NULL
            )
        )
);

CREATE INDEX IF NOT EXISTS idx_assinatura_pagamentos_assinatura
    ON public.assinatura_pagamentos (assinatura_id);

CREATE INDEX IF NOT EXISTS idx_assinatura_pagamentos_pago_em
    ON public.assinatura_pagamentos (pago_em DESC);

CREATE INDEX IF NOT EXISTS idx_assinatura_pagamentos_vencimento
    ON public.assinatura_pagamentos (vencimento_referencia);

 /*
  * Uma cobrança só pode possuir um pagamento confirmado.
  *
  * Se houver estorno futuramente, a mesma competência poderá
  * receber uma nova confirmação sem apagar o histórico anterior.
  */
CREATE UNIQUE INDEX IF NOT EXISTS uq_assinatura_pagamentos_confirmado_vencimento
    ON public.assinatura_pagamentos (
        assinatura_id,
        vencimento_referencia
    )
    WHERE status = 'CONFIRMADO';

 /*
  * Webhooks podem ser reenviados pelo gateway.
  *
  * Esse índice ajudará na idempotência financeira futura:
  * o mesmo pagamento externo não poderá ser persistido duas vezes.
  */
CREATE UNIQUE INDEX IF NOT EXISTS uq_assinatura_pagamentos_gateway
    ON public.assinatura_pagamentos (
        gateway,
        gateway_payment_id
    )
    WHERE gateway_payment_id IS NOT NULL;

COMMIT;
