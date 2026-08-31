BEGIN;

-- ============================================================
-- 010 - DATA DA ASSINATURA DOCUMENTAL
-- Init SaaS Platform
--
-- No fluxo MANUAL com Autentique, a Init não deve inventar um
-- horário exato de assinatura se o administrador estiver apenas
-- registrando a data exibida no documento.
--
-- Portanto:
-- - assinado_data = data civil da assinatura (obrigatória);
-- - assinado_em   = timestamp exato opcional, reservado para
--   futura integração por API/webhook quando o provedor fornecer
--   esse dado de forma confiável.
-- ============================================================

ALTER TABLE public.assinatura_documentos
    ADD COLUMN IF NOT EXISTS assinado_data DATE NULL;


-- Backfill seguro para qualquer documento já assinado antes desta migration.
UPDATE public.assinatura_documentos
SET assinado_data =
    (assinado_em AT TIME ZONE 'America/Fortaleza')::date
WHERE assinado_data IS NULL
  AND assinado_em IS NOT NULL;


-- Recria a regra de documento assinado.
ALTER TABLE public.assinatura_documentos
    DROP CONSTRAINT IF EXISTS ck_assinatura_documentos_assinado;

ALTER TABLE public.assinatura_documentos
    ADD CONSTRAINT ck_assinatura_documentos_assinado
    CHECK (
        status <> 'ASSINADO'
        OR (
            responsavel_id IS NOT NULL
            AND provider IS NOT NULL
            AND storage_key_assinado IS NOT NULL
            AND hash_assinado IS NOT NULL
            AND signatario_nome IS NOT NULL
            AND char_length(btrim(signatario_nome)) >= 3
            AND assinado_data IS NOT NULL
        )
    );


-- Recria as regras cronológicas.
ALTER TABLE public.assinatura_documentos
    DROP CONSTRAINT IF EXISTS ck_assinatura_documentos_datas;

ALTER TABLE public.assinatura_documentos
    ADD CONSTRAINT ck_assinatura_documentos_datas
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
        AND
        (
            assinado_data IS NULL
            OR assinado_data >=
                (criado_em AT TIME ZONE 'America/Fortaleza')::date
        )
        AND
        (
            enviado_em IS NULL
            OR assinado_data IS NULL
            OR assinado_data >=
                (enviado_em AT TIME ZONE 'America/Fortaleza')::date
        )
        AND
        (
            assinado_em IS NULL
            OR assinado_data IS NULL
            OR assinado_data =
                (assinado_em AT TIME ZONE 'America/Fortaleza')::date
        )
    );


CREATE INDEX IF NOT EXISTS idx_assinatura_documentos_assinado_data
    ON public.assinatura_documentos (assinado_data DESC)
    WHERE assinado_data IS NOT NULL;


COMMIT;


-- ============================================================
-- VERIFICAÇÃO
-- Mantida no final da própria migration, conforme padrão do projeto.
-- ============================================================

SELECT
    column_name,
    data_type,
    is_nullable
FROM information_schema.columns
WHERE table_schema = 'public'
  AND table_name = 'assinatura_documentos'
  AND column_name IN (
      'assinado_data',
      'assinado_em'
  )
ORDER BY column_name;


SELECT
    conname AS constraint_name
FROM pg_constraint
WHERE conrelid = 'public.assinatura_documentos'::regclass
  AND conname IN (
      'ck_assinatura_documentos_assinado',
      'ck_assinatura_documentos_datas'
  )
ORDER BY conname;


SELECT
    indexname,
    indexdef
FROM pg_indexes
WHERE schemaname = 'public'
  AND tablename = 'assinatura_documentos'
  AND indexname = 'idx_assinatura_documentos_assinado_data';
