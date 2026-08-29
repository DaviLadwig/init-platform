BEGIN;

ALTER TABLE public.assinaturas
    ADD COLUMN IF NOT EXISTS cancelamento_motivo VARCHAR(500);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'ck_assinaturas_cancelamento_motivo'
          AND conrelid = 'public.assinaturas'::regclass
    ) THEN
        ALTER TABLE public.assinaturas
            ADD CONSTRAINT ck_assinaturas_cancelamento_motivo
            CHECK (
                cancelamento_motivo IS NULL
                OR (
                    char_length(btrim(cancelamento_motivo)) >= 3
                    AND char_length(btrim(cancelamento_motivo)) <= 500
                )
            );
    END IF;
END
$$;

COMMIT;
