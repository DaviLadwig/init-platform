-- ============================================================
-- INIT SAAS PLATFORM
-- Migration: 001_create_init_saas.sql
--
-- Objetivo:
-- Criar a estrutura inicial do banco central init_saas.
--
-- Este banco contém somente informações administrativas
-- da plataforma. Dados internos do Init RH, Init Clinic ou
-- futuros produtos NÃO devem ser armazenados aqui.
-- ============================================================


-- ============================================================
-- 1. ROLES
-- ============================================================

CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,

    codigo VARCHAR(50) NOT NULL,
    nome VARCHAR(100) NOT NULL,

    ativo BOOLEAN NOT NULL DEFAULT TRUE,

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_roles_codigo UNIQUE (codigo),

    CONSTRAINT ck_roles_codigo
        CHECK (codigo ~ '^[A-Z0-9_]+$')
);


-- ============================================================
-- 2. USUARIOS
--
-- Usuários administrativos do Init SaaS Platform.
-- Não representa usuários dos produtos SaaS.
-- ============================================================

CREATE TABLE usuarios (
    id BIGSERIAL PRIMARY KEY,

    nome VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'ATIVO',

    ultimo_login_em TIMESTAMPTZ,

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT ck_usuarios_status
        CHECK (
            status IN (
                'ATIVO',
                'INATIVO',
                'BLOQUEADO'
            )
        )
);

-- Evita duplicidade de e-mail ignorando maiúsculas/minúsculas.
CREATE UNIQUE INDEX uq_usuarios_email_lower
    ON usuarios (LOWER(email));


-- ============================================================
-- 3. USUARIO_ROLES
-- ============================================================

CREATE TABLE usuario_roles (
    usuario_id BIGINT NOT NULL,
    role_id BIGINT NOT NULL,

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    PRIMARY KEY (usuario_id, role_id),

    CONSTRAINT fk_usuario_roles_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_usuario_roles_role
        FOREIGN KEY (role_id)
        REFERENCES roles(id)
        ON DELETE CASCADE
);


-- ============================================================
-- 4. EMPRESAS
-- ============================================================

CREATE TABLE empresas (
    id BIGSERIAL PRIMARY KEY,

    razao_social VARCHAR(200) NOT NULL,
    nome_fantasia VARCHAR(200),

    cnpj VARCHAR(14) NOT NULL,

    email VARCHAR(255),
    telefone VARCHAR(20),

    slug VARCHAR(150) NOT NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'ATIVA',

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_empresas_cnpj UNIQUE (cnpj),

    CONSTRAINT uq_empresas_slug UNIQUE (slug),

    CONSTRAINT ck_empresas_cnpj
        CHECK (cnpj ~ '^[0-9]{14}$'),

    CONSTRAINT ck_empresas_slug
        CHECK (slug ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$'),

    CONSTRAINT ck_empresas_status
        CHECK (
            status IN (
                'ATIVA',
                'INATIVA'
            )
        )
);


-- ============================================================
-- 5. EMPRESA_RESPONSAVEIS
-- ============================================================

CREATE TABLE empresa_responsaveis (
    id BIGSERIAL PRIMARY KEY,

    empresa_id BIGINT NOT NULL,

    nome VARCHAR(150) NOT NULL,
    email VARCHAR(255),
    telefone VARCHAR(20),
    cargo VARCHAR(100),

    principal BOOLEAN NOT NULL DEFAULT FALSE,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_empresa_responsaveis_empresa
        FOREIGN KEY (empresa_id)
        REFERENCES empresas(id)
        ON DELETE CASCADE
);

CREATE INDEX idx_empresa_responsaveis_empresa
    ON empresa_responsaveis (empresa_id);

-- Uma empresa pode ter vários responsáveis,
-- mas apenas um marcado como principal.
CREATE UNIQUE INDEX uq_empresa_responsavel_principal
    ON empresa_responsaveis (empresa_id)
    WHERE principal = TRUE
      AND ativo = TRUE;


-- ============================================================
-- 6. PRODUTOS
-- ============================================================

CREATE TABLE produtos (
    id BIGSERIAL PRIMARY KEY,

    codigo VARCHAR(50) NOT NULL,
    nome VARCHAR(150) NOT NULL,

    slug VARCHAR(100) NOT NULL,

    descricao TEXT,

    ativo BOOLEAN NOT NULL DEFAULT TRUE,

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_produtos_codigo UNIQUE (codigo),

    CONSTRAINT uq_produtos_slug UNIQUE (slug),

    CONSTRAINT ck_produtos_codigo
        CHECK (codigo ~ '^[A-Z0-9_]+$'),

    CONSTRAINT ck_produtos_slug
        CHECK (slug ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$')
);


-- ============================================================
-- 7. PLANOS
-- ============================================================

CREATE TABLE planos (
    id BIGSERIAL PRIMARY KEY,

    produto_id BIGINT NOT NULL,

    codigo VARCHAR(50) NOT NULL,
    nome VARCHAR(150) NOT NULL,

    descricao TEXT,

    valor NUMERIC(12, 2) NOT NULL,

    moeda VARCHAR(3) NOT NULL DEFAULT 'BRL',

    periodicidade VARCHAR(20) NOT NULL DEFAULT 'MENSAL',

    ativo BOOLEAN NOT NULL DEFAULT TRUE,

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_planos_produto
        FOREIGN KEY (produto_id)
        REFERENCES produtos(id)
        ON DELETE RESTRICT,

    CONSTRAINT uq_planos_produto_codigo
        UNIQUE (produto_id, codigo),

    -- Necessário para permitir que assinaturas garantam
    -- que plano_id realmente pertence ao produto_id informado.
    CONSTRAINT uq_planos_id_produto
        UNIQUE (id, produto_id),

    CONSTRAINT ck_planos_codigo
        CHECK (codigo ~ '^[A-Z0-9_]+$'),

    CONSTRAINT ck_planos_valor
        CHECK (valor >= 0),

    CONSTRAINT ck_planos_moeda
        CHECK (moeda ~ '^[A-Z]{3}$'),

    CONSTRAINT ck_planos_periodicidade
        CHECK (
            periodicidade IN (
                'MENSAL',
                'TRIMESTRAL',
                'SEMESTRAL',
                'ANUAL'
            )
        )
);

CREATE INDEX idx_planos_produto
    ON planos (produto_id);


-- ============================================================
-- 8. PLANO_LIMITES
--
-- Evitamos criar colunas específicas como:
-- max_funcionarios, max_pacientes, max_profissionais etc.
--
-- Cada produto poderá possuir limites diferentes.
-- ============================================================

CREATE TABLE plano_limites (
    id BIGSERIAL PRIMARY KEY,

    plano_id BIGINT NOT NULL,

    chave VARCHAR(100) NOT NULL,

    valor NUMERIC(18, 4) NOT NULL,

    unidade VARCHAR(30),

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_plano_limites_plano
        FOREIGN KEY (plano_id)
        REFERENCES planos(id)
        ON DELETE CASCADE,

    CONSTRAINT uq_plano_limites_chave
        UNIQUE (plano_id, chave),

    CONSTRAINT ck_plano_limites_chave
        CHECK (chave ~ '^[a-z0-9_]+$'),

    CONSTRAINT ck_plano_limites_valor
        CHECK (valor >= 0)
);

CREATE INDEX idx_plano_limites_plano
    ON plano_limites (plano_id);


-- ============================================================
-- 9. ASSINATURAS
-- ============================================================

CREATE TABLE assinaturas (
    id BIGSERIAL PRIMARY KEY,

    empresa_id BIGINT NOT NULL,
    produto_id BIGINT NOT NULL,
    plano_id BIGINT NOT NULL,

    status VARCHAR(30) NOT NULL DEFAULT 'PENDENTE_ATIVACAO',

    valor_contratado NUMERIC(12, 2) NOT NULL,

    moeda VARCHAR(3) NOT NULL DEFAULT 'BRL',

    periodicidade VARCHAR(20) NOT NULL DEFAULT 'MENSAL',

    inicio_em DATE NOT NULL,

    trial_fim_em DATE,

    vencimento_em DATE,
    proxima_cobranca_em DATE,
    ultimo_pagamento_em TIMESTAMPTZ,

    dias_tolerancia SMALLINT NOT NULL DEFAULT 5,

    gateway VARCHAR(50),
    gateway_customer_id VARCHAR(255),
    gateway_subscription_id VARCHAR(255),

    suspenso_em TIMESTAMPTZ,
    cancelado_em TIMESTAMPTZ,

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_assinaturas_empresa
        FOREIGN KEY (empresa_id)
        REFERENCES empresas(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_assinaturas_produto
        FOREIGN KEY (produto_id)
        REFERENCES produtos(id)
        ON DELETE RESTRICT,

    -- Garante no próprio PostgreSQL que o plano
    -- realmente pertence ao produto informado.
    CONSTRAINT fk_assinaturas_plano_produto
        FOREIGN KEY (plano_id, produto_id)
        REFERENCES planos(id, produto_id)
        ON DELETE RESTRICT,

    CONSTRAINT ck_assinaturas_status
        CHECK (
            status IN (
                'PENDENTE_ATIVACAO',
                'TRIAL',
                'ATIVA',
                'ATRASADA',
                'SUSPENSA',
                'CANCELADA'
            )
        ),

    CONSTRAINT ck_assinaturas_valor
        CHECK (valor_contratado >= 0),

    CONSTRAINT ck_assinaturas_moeda
        CHECK (moeda ~ '^[A-Z]{3}$'),

    CONSTRAINT ck_assinaturas_periodicidade
        CHECK (
            periodicidade IN (
                'MENSAL',
                'TRIMESTRAL',
                'SEMESTRAL',
                'ANUAL'
            )
        ),

    CONSTRAINT ck_assinaturas_dias_tolerancia
        CHECK (
            dias_tolerancia >= 0
            AND dias_tolerancia <= 90
        ),

    CONSTRAINT ck_assinaturas_trial
        CHECK (
            trial_fim_em IS NULL
            OR trial_fim_em >= inicio_em
        )
);

CREATE INDEX idx_assinaturas_empresa
    ON assinaturas (empresa_id);

CREATE INDEX idx_assinaturas_produto
    ON assinaturas (produto_id);

CREATE INDEX idx_assinaturas_plano
    ON assinaturas (plano_id);

CREATE INDEX idx_assinaturas_status
    ON assinaturas (status);

CREATE INDEX idx_assinaturas_proxima_cobranca
    ON assinaturas (proxima_cobranca_em);

-- Uma empresa não pode possuir duas assinaturas vigentes
-- do mesmo produto ao mesmo tempo.
--
-- Uma assinatura CANCELADA permanece como histórico e
-- permite posteriormente uma nova contratação.
CREATE UNIQUE INDEX uq_assinatura_vigente_empresa_produto
    ON assinaturas (empresa_id, produto_id)
    WHERE status <> 'CANCELADA';


-- ============================================================
-- 10. TENANTS
-- ============================================================

CREATE TABLE tenants (
    id BIGSERIAL PRIMARY KEY,

    assinatura_id BIGINT NOT NULL,

    tenant_code VARCHAR(100) NOT NULL,

    tenant_slug VARCHAR(150),

    database_name VARCHAR(150) NOT NULL,

    database_host_reference VARCHAR(255),
    secret_reference VARCHAR(255),

    status VARCHAR(30) NOT NULL DEFAULT 'PENDENTE',

    versao_schema VARCHAR(50),

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    atualizado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_tenants_assinatura
        UNIQUE (assinatura_id),

    CONSTRAINT uq_tenants_code
        UNIQUE (tenant_code),

    CONSTRAINT uq_tenants_database_name
        UNIQUE (database_name),

    CONSTRAINT fk_tenants_assinatura
        FOREIGN KEY (assinatura_id)
        REFERENCES assinaturas(id)
        ON DELETE RESTRICT,

    CONSTRAINT ck_tenants_code
        CHECK (tenant_code ~ '^[A-Z0-9_-]+$'),

    CONSTRAINT ck_tenants_slug
        CHECK (
            tenant_slug IS NULL
            OR tenant_slug ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$'
        ),

    CONSTRAINT ck_tenants_database_name
        CHECK (database_name ~ '^[a-z0-9_]+$'),

    CONSTRAINT ck_tenants_status
        CHECK (
            status IN (
                'PENDENTE',
                'ATIVO',
                'MANUTENCAO',
                'DESATIVADO'
            )
        )
);

CREATE INDEX idx_tenants_status
    ON tenants (status);


-- ============================================================
-- 11. LOGS
--
-- Auditoria administrativa do Init SaaS Platform.
--
-- IMPORTANTE:
-- O backend nunca deverá registrar senhas, tokens,
-- cookies, segredos ou credenciais nesta tabela.
-- ============================================================

CREATE TABLE logs (
    id BIGSERIAL PRIMARY KEY,

    usuario_id BIGINT,

    acao VARCHAR(100) NOT NULL,
    modulo VARCHAR(100),

    entidade VARCHAR(100),
    entidade_id BIGINT,

    ip INET,
    user_agent TEXT,

    dados_anteriores JSONB,
    dados_novos JSONB,

    criado_em TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT fk_logs_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE SET NULL
);

CREATE INDEX idx_logs_usuario
    ON logs (usuario_id);

CREATE INDEX idx_logs_modulo
    ON logs (modulo);

CREATE INDEX idx_logs_entidade
    ON logs (entidade, entidade_id);

CREATE INDEX idx_logs_criado_em
    ON logs (criado_em DESC);


-- ============================================================
-- FIM DA MIGRATION 001
-- ============================================================