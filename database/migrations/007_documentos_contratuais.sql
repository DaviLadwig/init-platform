BEGIN;

-- ============================================================
-- 007 - DOCUMENTOS CONTRATUAIS
-- Init SaaS Platform
--
-- Objetivo:
-- - versionar modelos/termos contratuais;
-- - vincular documentos imutáveis a cada assinatura;
-- - suportar fluxo manual com Autentique agora;
-- - permitir integração por API futuramente sem alterar o modelo;
-- - manter arquivos fora de public/, armazenando apenas storage keys.
-- ============================================================

-- ============================================================
-- 1. CATÁLOGO / VERSIONAMENTO DOS DOCUMENTOS
-- ============================================================

CREATE TABLE public.documentos_contratuais (
    id BIGSERIAL PRIMARY KEY,

/*
 * NULL = documento global da plataforma.
 * Preenchido = documento específico de um produto.
 */
produto_id BIGINT NULL,
tipo VARCHAR(60) NOT NULL,
titulo VARCHAR(200) NOT NULL,
versao VARCHAR(30) NOT NULL,
descricao TEXT NULL,

/*
 * Quando TRUE, o backend deverá exigir um documento
 * assinado desta versão/escopo antes de ativar a assinatura.
 */
obrigatorio_ativacao BOOLEAN NOT NULL DEFAULT TRUE,

/*
 * Referência opcional para um arquivo-base privado.
 *
 * Nunca armazenar URL pública aqui.
 * Exemplos futuros:
 * contracts/templates/termo-saas/1.0/modelo.pdf
 * s3-key privada equivalente.
 */
storage_key_modelo VARCHAR(500) NULL,
hash_modelo CHAR(64) NULL,
ativo BOOLEAN NOT NULL DEFAULT TRUE,
criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
CONSTRAINT fk_documentos_contratuais_produto FOREIGN KEY (produto_id) REFERENCES public.produtos (id) ON DELETE RESTRICT,
CONSTRAINT ck_documentos_contratuais_tipo CHECK (
    tipo = UPPER(tipo)
    AND tipo ~ '^[A-Z0-9]+(_[A-Z0-9]+)*$'
),
CONSTRAINT ck_documentos_contratuais_titulo CHECK (
    char_length(btrim (titulo)) BETWEEN 3 AND 200
),
CONSTRAINT ck_documentos_contratuais_versao CHECK (
    char_length(btrim (versao)) BETWEEN 1 AND 30
),
CONSTRAINT ck_documentos_contratuais_hash_modelo CHECK (
    hash_modelo IS NULL
    OR hash_modelo ~ '^[a-f0-9]{64}$'
),

/*
 * storage key e hash do modelo devem existir juntos.
 */
CONSTRAINT ck_documentos_contratuais_modelo_integridade CHECK (
    (
        storage_key_modelo IS NULL
        AND hash_modelo IS NULL
    )
    OR (
        storage_key_modelo IS NOT NULL
        AND hash_modelo IS NOT NULL
    )
),

/*
 * Evita persistir URL pública ou path absoluto.
 * A aplicação deve salvar somente a chave relativa do storage.
 */
CONSTRAINT ck_documentos_contratuais_storage_key_modelo
        CHECK (
            storage_key_modelo IS NULL
            OR (
                storage_key_modelo !~* '^https?://'
                AND storage_key_modelo !~ '^/'
                AND storage_key_modelo !~ '(^|/)\.\.(/|$)'
            )
        )
);

-- Uma versão não pode se repetir no mesmo escopo.
-- COALESCE(produto_id, 0) diferencia global x produto.
CREATE UNIQUE INDEX uq_documentos_contratuais_escopo_tipo_versao ON public.documentos_contratuais (
    COALESCE(produto_id, 0),
    tipo,
    versao
);

-- Apenas uma versão ATIVA do mesmo tipo em cada escopo.
CREATE UNIQUE INDEX uq_documentos_contratuais_ativo_escopo_tipo ON public.documentos_contratuais (COALESCE(produto_id, 0), tipo)
WHERE
    ativo = TRUE;

CREATE INDEX idx_documentos_contratuais_produto ON public.documentos_contratuais (produto_id);

CREATE INDEX idx_documentos_contratuais_ativos_obrigatorios ON public.documentos_contratuais (produto_id, tipo)
WHERE
    ativo = TRUE
    AND obrigatorio_ativacao = TRUE;

-- ============================================================
-- 2. DOCUMENTOS VINCULADOS A CADA ASSINATURA
-- ============================================================


CREATE TABLE public.assinatura_documentos (
    id BIGSERIAL PRIMARY KEY,

    assinatura_id BIGINT NOT NULL,
    documento_contratual_id BIGINT NOT NULL,

/*
 * Responsável contratante/signatário.
 * O Service deverá validar que o responsável pertence
 * à mesma empresa da assinatura.
 */
responsavel_id BIGINT NULL,

/*
 * Snapshot do documento no momento da geração.
 */
titulo_snapshot VARCHAR(200) NOT NULL,
versao_snapshot VARCHAR(30) NOT NULL,
status VARCHAR(30) NOT NULL DEFAULT 'GERADO',

/*
 * No fluxo manual inicial:
 * provider = AUTENTIQUE
 * origem_registro = MANUAL
 *
 * Na futura integração:
 * provider = AUTENTIQUE
 * origem_registro = API
 */
provider VARCHAR(30) NULL,
provider_document_id VARCHAR(255) NULL,
origem_registro VARCHAR(20) NOT NULL DEFAULT 'MANUAL',

/*
 * Documento original gerado pela Init.
 */
storage_key_original VARCHAR(500) NOT NULL,
hash_original CHAR(64) NOT NULL,
tamanho_original_bytes BIGINT NULL,

/*
 * Documento final assinado.
 */
storage_key_assinado VARCHAR(500) NULL,
hash_assinado CHAR(64) NULL,
tamanho_assinado_bytes BIGINT NULL,

/*
 * Snapshot do signatário.
 */
signatario_nome VARCHAR(150) NULL,
signatario_email VARCHAR(255) NULL,

/*
 * Evidências técnicas quando fornecidas de forma legítima
 * pelo provedor. Não inventar esses dados no fluxo manual.
 */
ip_assinatura INET NULL,
user_agent_assinatura VARCHAR(1000) NULL,
enviado_em TIMESTAMPTZ NULL,
assinado_em TIMESTAMPTZ NULL,
cancelado_em TIMESTAMPTZ NULL,
criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
CONSTRAINT fk_assinatura_documentos_assinatura FOREIGN KEY (assinatura_id) REFERENCES public.assinaturas (id) ON DELETE RESTRICT,
CONSTRAINT fk_assinatura_documentos_documento FOREIGN KEY (documento_contratual_id) REFERENCES public.documentos_contratuais (id) ON DELETE RESTRICT,
CONSTRAINT fk_assinatura_documentos_responsavel FOREIGN KEY (responsavel_id) REFERENCES public.empresa_responsaveis (id) ON DELETE RESTRICT,
CONSTRAINT ck_assinatura_documentos_status CHECK (
    status IN (
        'GERADO',
        'AGUARDANDO_ASSINATURA',
        'ASSINADO',
        'CANCELADO'
    )
),
CONSTRAINT ck_assinatura_documentos_provider CHECK (
    provider IS NULL
    OR provider IN (
        'AUTENTIQUE',
        'ZAPSIGN',
        'OUTRO'
    )
),
CONSTRAINT ck_assinatura_documentos_origem_registro CHECK (
    origem_registro IN ('MANUAL', 'API')
),

/*
 * Integração via API deve possuir identificador externo.
 */
CONSTRAINT ck_assinatura_documentos_api_provider_id CHECK (
    origem_registro <> 'API'
    OR (
        provider IS NOT NULL
        AND provider_document_id IS NOT NULL
        AND btrim (provider_document_id) <> ''
    )
),
CONSTRAINT ck_assinatura_documentos_titulo_snapshot CHECK (
    char_length(btrim (titulo_snapshot)) BETWEEN 3 AND 200
),
CONSTRAINT ck_assinatura_documentos_versao_snapshot CHECK (
    char_length(btrim (versao_snapshot)) BETWEEN 1 AND 30
),
CONSTRAINT ck_assinatura_documentos_hash_original CHECK (
    hash_original ~ '^[a-f0-9]{64}$'
),
CONSTRAINT ck_assinatura_documentos_hash_assinado CHECK (
    hash_assinado IS NULL
    OR hash_assinado ~ '^[a-f0-9]{64}$'
),
CONSTRAINT ck_assinatura_documentos_tamanho_original CHECK (
    tamanho_original_bytes IS NULL
    OR tamanho_original_bytes > 0
),
CONSTRAINT ck_assinatura_documentos_tamanho_assinado CHECK (
    tamanho_assinado_bytes IS NULL
    OR tamanho_assinado_bytes > 0
),

/*
 * Apenas storage keys privadas.
 */
CONSTRAINT ck_assinatura_documentos_storage_key_original CHECK (
    storage_key_original ! ~ * '^https?://'
    AND storage_key_original ! ~ '^/'
    AND storage_key_original ! ~ '(^|/)\.\.(/|$)'
),
CONSTRAINT ck_assinatura_documentos_storage_key_assinado CHECK (
    storage_key_assinado IS NULL
    OR (
        storage_key_assinado ! ~ * '^https?://'
        AND storage_key_assinado ! ~ '^/'
        AND storage_key_assinado ! ~ '(^|/)\.\.(/|$)'
    )
),
CONSTRAINT ck_assinatura_documentos_aguardando CHECK (
    status <> 'AGUARDANDO_ASSINATURA'
    OR (
        provider IS NOT NULL
        AND enviado_em IS NOT NULL
    )
),

/*
 * Documento assinado precisa das evidências mínimas.
 */
CONSTRAINT ck_assinatura_documentos_assinado
        CHECK (
            status <> 'ASSINADO'
            OR (
                responsavel_id IS NOT NULL
                AND provider IS NOT NULL
                AND storage_key_assinado IS NOT NULL
                AND hash_assinado IS NOT NULL
                AND signatario_nome IS NOT NULL
                AND char_length(btrim(signatario_nome)) >= 3
                AND assinado_em IS NOT NULL
            )
        ),

    CONSTRAINT ck_assinatura_documentos_cancelado
        CHECK (
            status <> 'CANCELADO'
            OR cancelado_em IS NOT NULL
        ),

    CONSTRAINT ck_assinatura_documentos_datas
        CHECK (
            (
                enviado_em IS NULL
                OR enviado_em >= criado_em
            )
            AND
            (
                assinado_em IS NULL
                OR assinado_em >= criado_em
            )
            AND
            (
                cancelado_em IS NULL
                OR cancelado_em >= criado_em
            )
            AND
            (
                enviado_em IS NULL
                OR assinado_em IS NULL
                OR assinado_em >= enviado_em
            )
        )
);

CREATE INDEX idx_assinatura_documentos_assinatura ON public.assinatura_documentos (assinatura_id);

CREATE INDEX idx_assinatura_documentos_documento ON public.assinatura_documentos (documento_contratual_id);

CREATE INDEX idx_assinatura_documentos_responsavel ON public.assinatura_documentos (responsavel_id);

CREATE INDEX idx_assinatura_documentos_status ON public.assinatura_documentos (status);

CREATE INDEX idx_assinatura_documentos_assinado_em ON public.assinatura_documentos (assinado_em DESC)
WHERE
    assinado_em IS NOT NULL;

-- Se uma tentativa for cancelada, uma nova poderá ser criada.
CREATE UNIQUE INDEX uq_assinatura_documento_vigente ON public.assinatura_documentos (
    assinatura_id,
    documento_contratual_id
)
WHERE
    status <> 'CANCELADO';

-- Idempotência para futura integração via provider.
CREATE UNIQUE INDEX uq_assinatura_documentos_provider_document_id ON public.assinatura_documentos (
    provider,
    provider_document_id
)
WHERE
    provider_document_id IS NOT NULL;

-- ============================================================
-- 3. IMUTABILIDADE DE MODELOS JÁ UTILIZADOS
-- ============================================================

CREATE OR REPLACE FUNCTION public.fn_proteger_documento_contratual_referenciado()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM public.assinatura_documentos ad
        WHERE ad.documento_contratual_id = OLD.id
        LIMIT 1
    ) THEN
        IF
            NEW.produto_id IS DISTINCT FROM OLD.produto_id
            OR NEW.tipo IS DISTINCT FROM OLD.tipo
            OR NEW.titulo IS DISTINCT FROM OLD.titulo
            OR NEW.versao IS DISTINCT FROM OLD.versao
            OR NEW.storage_key_modelo IS DISTINCT FROM OLD.storage_key_modelo
            OR NEW.hash_modelo IS DISTINCT FROM OLD.hash_modelo
            OR NEW.obrigatorio_ativacao IS DISTINCT FROM OLD.obrigatorio_ativacao
        THEN
            RAISE EXCEPTION
                'Documento contratual referenciado não pode ter sua versão/conteúdo alterados. Crie uma nova versão.';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_proteger_documento_contratual_referenciado
BEFORE UPDATE ON public.documentos_contratuais
FOR EACH ROW
EXECUTE FUNCTION public.fn_proteger_documento_contratual_referenciado();

-- ============================================================
-- 4. IMUTABILIDADE DE DOCUMENTO JÁ ASSINADO
-- ============================================================

CREATE OR REPLACE FUNCTION public.fn_proteger_assinatura_documento_assinado()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status = 'ASSINADO' THEN
            RAISE EXCEPTION
                'Documento assinado não pode ser excluído.';
        END IF;

        RETURN OLD;
    END IF;

    IF OLD.status = 'ASSINADO' THEN
        RAISE EXCEPTION
            'Documento assinado é imutável.';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_proteger_assinatura_documento_assinado
BEFORE UPDATE OR DELETE ON public.assinatura_documentos
FOR EACH ROW
EXECUTE FUNCTION public.fn_proteger_assinatura_documento_assinado();

COMMIT;

--=====================================================================
--VERIFICAÇÃO DE INTEGRIDADE DAS MIGRAÇÕES
--=====================================================================

BEGIN;

-- ============================================================
-- 007 - DOCUMENTOS CONTRATUAIS
-- Init SaaS Platform
--
-- Objetivo:
-- - versionar modelos/termos contratuais;
-- - vincular documentos imutáveis a cada assinatura;
-- - suportar fluxo manual com Autentique agora;
-- - permitir integração por API futuramente sem alterar o modelo;
-- - manter arquivos fora de public/, armazenando apenas storage keys.
-- ============================================================

-- ============================================================
-- 1. CATÁLOGO / VERSIONAMENTO DOS DOCUMENTOS
-- ============================================================

CREATE TABLE public.documentos_contratuais (
    id BIGSERIAL PRIMARY KEY,

/*
 * NULL = documento global da plataforma.
 * Preenchido = documento específico de um produto.
 */
produto_id BIGINT NULL,
tipo VARCHAR(60) NOT NULL,
titulo VARCHAR(200) NOT NULL,
versao VARCHAR(30) NOT NULL,
descricao TEXT NULL,

/*
 * Quando TRUE, o backend deverá exigir um documento
 * assinado desta versão/escopo antes de ativar a assinatura.
 */
obrigatorio_ativacao BOOLEAN NOT NULL DEFAULT TRUE,

/*
 * Referência opcional para um arquivo-base privado.
 *
 * Nunca armazenar URL pública aqui.
 * Exemplos futuros:
 * contracts/templates/termo-saas/1.0/modelo.pdf
 * s3-key privada equivalente.
 */
storage_key_modelo VARCHAR(500) NULL,
hash_modelo CHAR(64) NULL,
ativo BOOLEAN NOT NULL DEFAULT TRUE,
criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
CONSTRAINT fk_documentos_contratuais_produto FOREIGN KEY (produto_id) REFERENCES public.produtos (id) ON DELETE RESTRICT,
CONSTRAINT ck_documentos_contratuais_tipo CHECK (
    tipo = UPPER(tipo)
    AND tipo ~ '^[A-Z0-9]+(_[A-Z0-9]+)*$'
),
CONSTRAINT ck_documentos_contratuais_titulo CHECK (
    char_length(btrim (titulo)) BETWEEN 3 AND 200
),
CONSTRAINT ck_documentos_contratuais_versao CHECK (
    char_length(btrim (versao)) BETWEEN 1 AND 30
),
CONSTRAINT ck_documentos_contratuais_hash_modelo CHECK (
    hash_modelo IS NULL
    OR hash_modelo ~ '^[a-f0-9]{64}$'
),

/*
 * storage key e hash do modelo devem existir juntos.
 */
CONSTRAINT ck_documentos_contratuais_modelo_integridade CHECK (
    (
        storage_key_modelo IS NULL
        AND hash_modelo IS NULL
    )
    OR (
        storage_key_modelo IS NOT NULL
        AND hash_modelo IS NOT NULL
    )
),

/*
 * Evita persistir URL pública ou path absoluto.
 * A aplicação deve salvar somente a chave relativa do storage.
 */
CONSTRAINT ck_documentos_contratuais_storage_key_modelo
        CHECK (
            storage_key_modelo IS NULL
            OR (
                storage_key_modelo !~* '^https?://'
                AND storage_key_modelo !~ '^/'
                AND storage_key_modelo !~ '(^|/)\.\.(/|$)'
            )
        )
);

-- Uma versão não pode se repetir no mesmo escopo.
-- COALESCE(produto_id, 0) diferencia global x produto.
CREATE UNIQUE INDEX uq_documentos_contratuais_escopo_tipo_versao ON public.documentos_contratuais (
    COALESCE(produto_id, 0),
    tipo,
    versao
);

-- Apenas uma versão ATIVA do mesmo tipo em cada escopo.
CREATE UNIQUE INDEX uq_documentos_contratuais_ativo_escopo_tipo ON public.documentos_contratuais (COALESCE(produto_id, 0), tipo)
WHERE
    ativo = TRUE;

CREATE INDEX idx_documentos_contratuais_produto ON public.documentos_contratuais (produto_id);

CREATE INDEX idx_documentos_contratuais_ativos_obrigatorios ON public.documentos_contratuais (produto_id, tipo)
WHERE
    ativo = TRUE
    AND obrigatorio_ativacao = TRUE;

-- ============================================================
-- 2. DOCUMENTOS VINCULADOS A CADA ASSINATURA
-- ============================================================


CREATE TABLE public.assinatura_documentos (
    id BIGSERIAL PRIMARY KEY,

    assinatura_id BIGINT NOT NULL,
    documento_contratual_id BIGINT NOT NULL,

/*
 * Responsável contratante/signatário.
 * O Service deverá validar que o responsável pertence
 * à mesma empresa da assinatura.
 */
responsavel_id BIGINT NULL,

/*
 * Snapshot do documento no momento da geração.
 */
titulo_snapshot VARCHAR(200) NOT NULL,
versao_snapshot VARCHAR(30) NOT NULL,
status VARCHAR(30) NOT NULL DEFAULT 'GERADO',

/*
 * No fluxo manual inicial:
 * provider = AUTENTIQUE
 * origem_registro = MANUAL
 *
 * Na futura integração:
 * provider = AUTENTIQUE
 * origem_registro = API
 */
provider VARCHAR(30) NULL,
provider_document_id VARCHAR(255) NULL,
origem_registro VARCHAR(20) NOT NULL DEFAULT 'MANUAL',

/*
 * Documento original gerado pela Init.
 */
storage_key_original VARCHAR(500) NOT NULL,
hash_original CHAR(64) NOT NULL,
tamanho_original_bytes BIGINT NULL,

/*
 * Documento final assinado.
 */
storage_key_assinado VARCHAR(500) NULL,
hash_assinado CHAR(64) NULL,
tamanho_assinado_bytes BIGINT NULL,

/*
 * Snapshot do signatário.
 */
signatario_nome VARCHAR(150) NULL,
signatario_email VARCHAR(255) NULL,

/*
 * Evidências técnicas quando fornecidas de forma legítima
 * pelo provedor. Não inventar esses dados no fluxo manual.
 */
ip_assinatura INET NULL,
user_agent_assinatura VARCHAR(1000) NULL,
enviado_em TIMESTAMPTZ NULL,
assinado_em TIMESTAMPTZ NULL,
cancelado_em TIMESTAMPTZ NULL,
criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
CONSTRAINT fk_assinatura_documentos_assinatura FOREIGN KEY (assinatura_id) REFERENCES public.assinaturas (id) ON DELETE RESTRICT,
CONSTRAINT fk_assinatura_documentos_documento FOREIGN KEY (documento_contratual_id) REFERENCES public.documentos_contratuais (id) ON DELETE RESTRICT,
CONSTRAINT fk_assinatura_documentos_responsavel FOREIGN KEY (responsavel_id) REFERENCES public.empresa_responsaveis (id) ON DELETE RESTRICT,
CONSTRAINT ck_assinatura_documentos_status CHECK (
    status IN (
        'GERADO',
        'AGUARDANDO_ASSINATURA',
        'ASSINADO',
        'CANCELADO'
    )
),
CONSTRAINT ck_assinatura_documentos_provider CHECK (
    provider IS NULL
    OR provider IN (
        'AUTENTIQUE',
        'ZAPSIGN',
        'OUTRO'
    )
),
CONSTRAINT ck_assinatura_documentos_origem_registro CHECK (
    origem_registro IN ('MANUAL', 'API')
),

/*
 * Integração via API deve possuir identificador externo.
 */
CONSTRAINT ck_assinatura_documentos_api_provider_id CHECK (
    origem_registro <> 'API'
    OR (
        provider IS NOT NULL
        AND provider_document_id IS NOT NULL
        AND btrim (provider_document_id) <> ''
    )
),
CONSTRAINT ck_assinatura_documentos_titulo_snapshot CHECK (
    char_length(btrim (titulo_snapshot)) BETWEEN 3 AND 200
),
CONSTRAINT ck_assinatura_documentos_versao_snapshot CHECK (
    char_length(btrim (versao_snapshot)) BETWEEN 1 AND 30
),
CONSTRAINT ck_assinatura_documentos_hash_original CHECK (
    hash_original ~ '^[a-f0-9]{64}$'
),
CONSTRAINT ck_assinatura_documentos_hash_assinado CHECK (
    hash_assinado IS NULL
    OR hash_assinado ~ '^[a-f0-9]{64}$'
),
CONSTRAINT ck_assinatura_documentos_tamanho_original CHECK (
    tamanho_original_bytes IS NULL
    OR tamanho_original_bytes > 0
),
CONSTRAINT ck_assinatura_documentos_tamanho_assinado CHECK (
    tamanho_assinado_bytes IS NULL
    OR tamanho_assinado_bytes > 0
),

/*
 * Apenas storage keys privadas.
 */
CONSTRAINT ck_assinatura_documentos_storage_key_original CHECK (
    storage_key_original ! ~ * '^https?://'
    AND storage_key_original ! ~ '^/'
    AND storage_key_original ! ~ '(^|/)\.\.(/|$)'
),
CONSTRAINT ck_assinatura_documentos_storage_key_assinado CHECK (
    storage_key_assinado IS NULL
    OR (
        storage_key_assinado ! ~ * '^https?://'
        AND storage_key_assinado ! ~ '^/'
        AND storage_key_assinado ! ~ '(^|/)\.\.(/|$)'
    )
),
CONSTRAINT ck_assinatura_documentos_aguardando CHECK (
    status <> 'AGUARDANDO_ASSINATURA'
    OR (
        provider IS NOT NULL
        AND enviado_em IS NOT NULL
    )
),

/*
 * Documento assinado precisa das evidências mínimas.
 */
CONSTRAINT ck_assinatura_documentos_assinado
        CHECK (
            status <> 'ASSINADO'
            OR (
                responsavel_id IS NOT NULL
                AND provider IS NOT NULL
                AND storage_key_assinado IS NOT NULL
                AND hash_assinado IS NOT NULL
                AND signatario_nome IS NOT NULL
                AND char_length(btrim(signatario_nome)) >= 3
                AND assinado_em IS NOT NULL
            )
        ),

    CONSTRAINT ck_assinatura_documentos_cancelado
        CHECK (
            status <> 'CANCELADO'
            OR cancelado_em IS NOT NULL
        ),

    CONSTRAINT ck_assinatura_documentos_datas
        CHECK (
            (
                enviado_em IS NULL
                OR enviado_em >= criado_em
            )
            AND
            (
                assinado_em IS NULL
                OR assinado_em >= criado_em
            )
            AND
            (
                cancelado_em IS NULL
                OR cancelado_em >= criado_em
            )
            AND
            (
                enviado_em IS NULL
                OR assinado_em IS NULL
                OR assinado_em >= enviado_em
            )
        )
);

CREATE INDEX idx_assinatura_documentos_assinatura ON public.assinatura_documentos (assinatura_id);

CREATE INDEX idx_assinatura_documentos_documento ON public.assinatura_documentos (documento_contratual_id);

CREATE INDEX idx_assinatura_documentos_responsavel ON public.assinatura_documentos (responsavel_id);

CREATE INDEX idx_assinatura_documentos_status ON public.assinatura_documentos (status);

CREATE INDEX idx_assinatura_documentos_assinado_em ON public.assinatura_documentos (assinado_em DESC)
WHERE
    assinado_em IS NOT NULL;

-- Se uma tentativa for cancelada, uma nova poderá ser criada.
CREATE UNIQUE INDEX uq_assinatura_documento_vigente ON public.assinatura_documentos (
    assinatura_id,
    documento_contratual_id
)
WHERE
    status <> 'CANCELADO';

-- Idempotência para futura integração via provider.
CREATE UNIQUE INDEX uq_assinatura_documentos_provider_document_id ON public.assinatura_documentos (
    provider,
    provider_document_id
)
WHERE
    provider_document_id IS NOT NULL;

-- ============================================================
-- 3. IMUTABILIDADE DE MODELOS JÁ UTILIZADOS
-- ============================================================

CREATE OR REPLACE FUNCTION public.fn_proteger_documento_contratual_referenciado()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM public.assinatura_documentos ad
        WHERE ad.documento_contratual_id = OLD.id
        LIMIT 1
    ) THEN
        IF
            NEW.produto_id IS DISTINCT FROM OLD.produto_id
            OR NEW.tipo IS DISTINCT FROM OLD.tipo
            OR NEW.titulo IS DISTINCT FROM OLD.titulo
            OR NEW.versao IS DISTINCT FROM OLD.versao
            OR NEW.storage_key_modelo IS DISTINCT FROM OLD.storage_key_modelo
            OR NEW.hash_modelo IS DISTINCT FROM OLD.hash_modelo
            OR NEW.obrigatorio_ativacao IS DISTINCT FROM OLD.obrigatorio_ativacao
        THEN
            RAISE EXCEPTION
                'Documento contratual referenciado não pode ter sua versão/conteúdo alterados. Crie uma nova versão.';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_proteger_documento_contratual_referenciado
BEFORE UPDATE ON public.documentos_contratuais
FOR EACH ROW
EXECUTE FUNCTION public.fn_proteger_documento_contratual_referenciado();

-- ============================================================
-- 4. IMUTABILIDADE DE DOCUMENTO JÁ ASSINADO
-- ============================================================

CREATE OR REPLACE FUNCTION public.fn_proteger_assinatura_documento_assinado()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status = 'ASSINADO' THEN
            RAISE EXCEPTION
                'Documento assinado não pode ser excluído.';
        END IF;

        RETURN OLD;
    END IF;

    IF OLD.status = 'ASSINADO' THEN
        RAISE EXCEPTION
            'Documento assinado é imutável.';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_proteger_assinatura_documento_assinado
BEFORE UPDATE OR DELETE ON public.assinatura_documentos
FOR EACH ROW
EXECUTE FUNCTION public.fn_proteger_assinatura_documento_assinado();

COMMIT;

--==========================================================================================

-- ============================================================
-- VERIFICAÇÃO DA MIGRATION 007
-- ============================================================

-- 1. Tabelas
SELECT table_name
FROM information_schema.tables
WHERE
    table_schema = 'public'
    AND table_name IN (
        'documentos_contratuais',
        'assinatura_documentos'
    )
ORDER BY table_name;

-- 2. Colunas de documentos_contratuais
SELECT
    ordinal_position,
    column_name,
    data_type,
    is_nullable,
    column_default
FROM information_schema.columns
WHERE
    table_schema = 'public'
    AND table_name = 'documentos_contratuais'
ORDER BY ordinal_position;

-- 3. Colunas de assinatura_documentos
SELECT
    ordinal_position,
    column_name,
    data_type,
    is_nullable,
    column_default
FROM information_schema.columns
WHERE
    table_schema = 'public'
    AND table_name = 'assinatura_documentos'
ORDER BY ordinal_position;

-- 4. Constraints
SELECT
    conrelid::regclass AS tabela,
    conname AS constraint_name,
    contype AS tipo
FROM pg_constraint
WHERE conrelid IN (
    'public.documentos_contratuais'::regclass,
    'public.assinatura_documentos'::regclass
)
ORDER BY
    conrelid::regclass::text,
    conname;

-- 5. Índices
SELECT tablename, indexname, indexdef
FROM pg_indexes
WHERE
    schemaname = 'public'
    AND tablename IN (
        'documentos_contratuais',
        'assinatura_documentos'
    )
ORDER BY tablename, indexname;

-- 6. Triggers
SELECT
    event_object_table AS tabela,
    trigger_name,
    event_manipulation AS evento
FROM information_schema.triggers
WHERE
    trigger_schema = 'public'
    AND event_object_table IN (
        'documentos_contratuais',
        'assinatura_documentos'
    )
ORDER BY
    event_object_table,
    trigger_name,
    event_manipulation;