-- ============================================================
-- INIT SAAS PLATFORM
-- Migration: 002_create_rate_limits.sql
--
-- Controle persistente de tentativas para operações sensíveis.
--
-- Não armazenamos IP, e-mail ou identificador sensível
-- diretamente. O backend armazenará somente um HMAC.
-- ============================================================


CREATE TABLE rate_limits (
    id BIGSERIAL PRIMARY KEY,

    action VARCHAR(80) NOT NULL,

    key_hash CHAR(64) NOT NULL,

    attempts INTEGER NOT NULL DEFAULT 0,

    window_started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    blocked_until TIMESTAMPTZ,

    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT uq_rate_limits_action_key
        UNIQUE (action, key_hash),

    CONSTRAINT ck_rate_limits_action
        CHECK (
            action ~ '^[a-z0-9_.-]+$'
        ),

    CONSTRAINT ck_rate_limits_key_hash
        CHECK (
            key_hash ~ '^[a-f0-9]{64}$'
        ),

    CONSTRAINT ck_rate_limits_attempts
        CHECK (
            attempts >= 0
        )
);


CREATE INDEX idx_rate_limits_blocked_until
    ON rate_limits (blocked_until);


CREATE INDEX idx_rate_limits_updated_at
    ON rate_limits (updated_at);


-- ============================================================
-- FIM DA MIGRATION 002
-- ============================================================