BEGIN;

-- ============================================================
-- 008 - VERSÃO INICIAL DO TERMO DE CONTRATAÇÃO SAAS
--
-- Cria somente o registro de catálogo/versionamento.
-- O conteúdo jurídico/PDF não é inserido no banco.
--
-- O PDF original será armazenado de forma privada em
-- storage/contracts/ quando o admin registrar o termo
-- para uma assinatura.
-- ============================================================

DO $$
BEGIN
    /*
     * Se já existir qualquer versão global ATIVA deste tipo,
     * não criamos outra e evitamos conflito com o índice
     * uq_documentos_contratuais_ativo_escopo_tipo.
     */
    IF NOT EXISTS (
        SELECT 1
        FROM public.documentos_contratuais
        WHERE produto_id IS NULL
          AND tipo = 'TERMO_CONTRATACAO_SAAS'
          AND ativo = TRUE
    ) THEN
        INSERT INTO public.documentos_contratuais (
            produto_id,
            tipo,
            titulo,
            versao,
            descricao,
            obrigatorio_ativacao,
            storage_key_modelo,
            hash_modelo,
            ativo
        )
        VALUES (
            NULL,
            'TERMO_CONTRATACAO_SAAS',
            'Termo de Contratação SaaS',
            '1.0',
            'Termo geral de contratação dos produtos SaaS da Init Sistemas.',
            TRUE,
            NULL,
            NULL,
            TRUE
        );
    END IF;
END
$$;

COMMIT;
