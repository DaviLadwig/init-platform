BEGIN;

CREATE UNIQUE INDEX IF NOT EXISTS uq_empresa_responsaveis_principal
    ON public.empresa_responsaveis (empresa_id)
    WHERE principal = true;

COMMIT;