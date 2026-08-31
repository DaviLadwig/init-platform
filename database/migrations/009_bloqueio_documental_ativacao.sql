BEGIN;

-- ============================================================
-- 009 - BLOQUEIO DOCUMENTAL DA ATIVAÇÃO
-- Init SaaS Platform
--
-- Defesa em profundidade:
-- 1. O Service valida antes de ativar e retorna mensagem amigável.
-- 2. O PostgreSQL também impede PENDENTE_ATIVACAO -> ATIVA
--    se houver documento contratual obrigatório pendente.
--
-- Isso protege inclusive contra:
-- - chamada incorreta no backend;
-- - script administrativo;
-- - atualização SQL direta acidental.
-- ============================================================

CREATE OR REPLACE FUNCTION public.fn_validar_documentos_ativacao_assinatura()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    possui_pendencia BOOLEAN;
BEGIN
    /*
     * A regra documental é aplicada somente à primeira ativação.
     *
     * Pagamento de uma assinatura SUSPENSA/ATRASADA pode devolvê-la
     * a ATIVA sem exigir uma nova assinatura documental.
     */
    IF
        OLD.status = 'PENDENTE_ATIVACAO'
        AND NEW.status = 'ATIVA'
    THEN
        WITH documentos_aplicaveis AS (
            /*
             * Um documento específico do produto prevalece sobre
             * o global do mesmo tipo.
             *
             * Tipos diferentes continuam cumulativos.
             */
            SELECT DISTINCT ON (dc.tipo)
                dc.id,
                dc.tipo
            FROM public.documentos_contratuais AS dc
            WHERE dc.ativo = TRUE
              AND dc.obrigatorio_ativacao = TRUE
              AND (
                    dc.produto_id IS NULL
                    OR dc.produto_id = NEW.produto_id
              )
            ORDER BY
                dc.tipo ASC,
                CASE
                    WHEN dc.produto_id = NEW.produto_id
                        THEN 0
                    ELSE 1
                END ASC,
                dc.id DESC
        )
        SELECT EXISTS (
            SELECT 1
            FROM documentos_aplicaveis AS da
            WHERE NOT EXISTS (
                SELECT 1
                FROM public.assinatura_documentos AS ad
                WHERE ad.assinatura_id = NEW.id
                  AND ad.documento_contratual_id = da.id
                  AND ad.status = 'ASSINADO'
            )
        )
        INTO possui_pendencia;

        IF possui_pendencia THEN
            RAISE EXCEPTION
                USING
                    ERRCODE = 'P7501',
                    MESSAGE = 'ASSINATURA_DOCUMENTACAO_PENDENTE';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;


DROP TRIGGER IF EXISTS
    trg_validar_documentos_ativacao_assinatura
ON public.assinaturas;


CREATE TRIGGER trg_validar_documentos_ativacao_assinatura
BEFORE UPDATE OF status
ON public.assinaturas
FOR EACH ROW
EXECUTE FUNCTION public.fn_validar_documentos_ativacao_assinatura();


COMMIT;
