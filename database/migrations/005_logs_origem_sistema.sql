BEGIN;

 /*
  * Ações automáticas não devem ser atribuídas falsamente
  * a um administrador humano.
  */
ALTER TABLE public.logs
    ALTER COLUMN usuario_id DROP NOT NULL;

ALTER TABLE public.logs
    ADD COLUMN IF NOT EXISTS origem VARCHAR(20);

UPDATE public.logs
SET origem = 'USUARIO'
WHERE origem IS NULL;

ALTER TABLE public.logs
    ALTER COLUMN origem SET DEFAULT 'USUARIO';

ALTER TABLE public.logs
    ALTER COLUMN origem SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'ck_logs_origem'
          AND conrelid = 'public.logs'::regclass
    ) THEN
        ALTER TABLE public.logs
            ADD CONSTRAINT ck_logs_origem
            CHECK (
                origem IN (
                    'USUARIO',
                    'SISTEMA'
                )
            );
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS idx_logs_origem_criado_em
    ON public.logs (
        origem,
        criado_em DESC
    );

COMMIT;
