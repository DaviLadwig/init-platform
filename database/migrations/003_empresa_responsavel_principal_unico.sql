BEGIN;

/*
 * Defesa final no banco: uma empresa não pode possuir
 * dois responsáveis marcados como principal ao mesmo tempo.
 *
 * IF NOT EXISTS torna a migration segura caso o índice
 * já tenha sido criado anteriormente.
 */
CREATE UNIQUE INDEX IF NOT EXISTS uq_empresa_responsaveis_principal
    ON public.empresa_responsaveis (empresa_id)
    WHERE principal = true;

COMMIT;
