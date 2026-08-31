<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class AssinaturaDocumentoRepository
{
    /**
     * Lista documentos vinculados à assinatura.
     *
     * Não retorna storage keys para a camada de interface.
     * Downloads futuros deverão passar por endpoint autenticado.
     */
    public function findByAssinaturaId(
        int $assinaturaId
    ): array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                ad.id,
                ad.assinatura_id,
                ad.documento_contratual_id,
                ad.responsavel_id,
                ad.titulo_snapshot,
                ad.versao_snapshot,
                ad.status,
                ad.provider,
                ad.origem_registro,
                ad.signatario_nome,
                ad.signatario_email,
                ad.enviado_em,
                ad.assinado_em,
                ad.assinado_data,
                ad.cancelado_em,
                ad.criado_em,
                ad.atualizado_em,

                dc.tipo,
                dc.obrigatorio_ativacao,

                er.nome AS responsavel_nome,
                er.email AS responsavel_email

            FROM public.assinatura_documentos AS ad

            INNER JOIN public.documentos_contratuais AS dc
                ON dc.id = ad.documento_contratual_id

            LEFT JOIN public.empresa_responsaveis AS er
                ON er.id = ad.responsavel_id

            WHERE ad.assinatura_id = :assinatura_id

            ORDER BY
                CASE ad.status
                    WHEN \'AGUARDANDO_ASSINATURA\' THEN 1
                    WHEN \'GERADO\' THEN 2
                    WHEN \'ASSINADO\' THEN 3
                    WHEN \'CANCELADO\' THEN 4
                    ELSE 5
                END,
                ad.id DESC
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    /**
     * Busca um documento com metadados internos.
     *
     * Este método é exclusivo de backend e pode retornar storage keys
     * e hashes necessários ao download/validação.
     */
    public function findById(
        int $assinaturaDocumentoId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                ad.id,
                ad.assinatura_id,
                ad.documento_contratual_id,
                ad.responsavel_id,
                ad.titulo_snapshot,
                ad.versao_snapshot,
                ad.status,
                ad.provider,
                ad.provider_document_id,
                ad.origem_registro,
                ad.storage_key_original,
                ad.hash_original,
                ad.tamanho_original_bytes,
                ad.storage_key_assinado,
                ad.hash_assinado,
                ad.tamanho_assinado_bytes,
                ad.signatario_nome,
                ad.signatario_email,
                ad.ip_assinatura,
                ad.user_agent_assinatura,
                ad.enviado_em,
                ad.assinado_em,
                ad.assinado_data,
                ad.cancelado_em,
                ad.criado_em,
                ad.atualizado_em,
                dc.tipo,
                dc.obrigatorio_ativacao
            FROM public.assinatura_documentos AS ad
            INNER JOIN public.documentos_contratuais AS dc
                ON dc.id = ad.documento_contratual_id
            WHERE ad.id = :assinatura_documento_id
            LIMIT 1
            '
        );

        $statement->bindValue(
            ':assinatura_documento_id',
            $assinaturaDocumentoId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * Bloqueia o documento para alteração de estado.
     */
    public function lockById(
        int $assinaturaDocumentoId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                assinatura_id,
                documento_contratual_id,
                responsavel_id,
                titulo_snapshot,
                versao_snapshot,
                status,
                provider,
                provider_document_id,
                origem_registro,
                storage_key_original,
                hash_original,
                tamanho_original_bytes,
                storage_key_assinado,
                hash_assinado,
                tamanho_assinado_bytes,
                signatario_nome,
                signatario_email,
                ip_assinatura,
                user_agent_assinatura,
                enviado_em,
                assinado_em,
                assinado_data,
                cancelado_em,
                criado_em,
                atualizado_em
            FROM public.assinatura_documentos
            WHERE id = :assinatura_documento_id
            FOR UPDATE
            '
        );

        $statement->bindValue(
            ':assinatura_documento_id',
            $assinaturaDocumentoId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * Retorna os IDs de versões que já possuem documento ASSINADO
     * na assinatura.
     */
    public function findSignedDocumentModelIds(
        int $assinaturaId
    ): array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT DISTINCT documento_contratual_id
            FROM public.assinatura_documentos
            WHERE assinatura_id = :assinatura_id
              AND status = \'ASSINADO\'
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $values = $statement->fetchAll(
            PDO::FETCH_COLUMN
        );

        if (!is_array($values)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn (mixed $value): int =>
                        (int) $value,
                    $values
                ),
                static fn (int $value): bool =>
                    $value > 0
            )
        );
    }

    /**
     * Verifica tentativa vigente da mesma versão.
     */
    public function findCurrentAttempt(
        int $assinaturaId,
        int $documentoContratualId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                status,
                responsavel_id,
                provider,
                enviado_em,
                assinado_em,
                assinado_data,
                criado_em
            FROM public.assinatura_documentos
            WHERE assinatura_id = :assinatura_id
              AND documento_contratual_id = :documento_contratual_id
              AND status <> \'CANCELADO\'
            ORDER BY id DESC
            LIMIT 1
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':documento_contratual_id',
            $documentoContratualId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }


    /**
     * Lista responsáveis ativos da empresa para seleção documental.
     *
     * O principal aparece primeiro, mas o backend continua validando
     * o vínculo no momento de cada mutação.
     */
    public function findActiveResponsiblesByCompany(
        int $empresaId
    ): array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                empresa_id,
                nome,
                email,
                cargo,
                principal
            FROM public.empresa_responsaveis
            WHERE empresa_id = :empresa_id
              AND ativo = TRUE
            ORDER BY
                principal DESC,
                nome ASC,
                id ASC
            '
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    /**
     * Confirma que um responsável ATIVO pertence à empresa
     * da assinatura.
     */
    public function findActiveResponsibleForCompany(
        int $responsavelId,
        int $empresaId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                empresa_id,
                nome,
                email,
                telefone,
                cargo,
                principal,
                ativo
            FROM public.empresa_responsaveis
            WHERE id = :responsavel_id
              AND empresa_id = :empresa_id
              AND ativo = TRUE
            LIMIT 1
            '
        );

        $statement->bindValue(
            ':responsavel_id',
            $responsavelId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * Registra o documento original já armazenado de forma privada.
     *
     * O Repository não recebe arquivo bruto. A camada de storage
     * será responsável por persistir o PDF e calcular o SHA-256.
     */
    public function createGenerated(
        int $assinaturaId,
        int $documentoContratualId,
        ?int $responsavelId,
        string $tituloSnapshot,
        string $versaoSnapshot,
        string $storageKeyOriginal,
        string $hashOriginal,
        ?int $tamanhoOriginalBytes
    ): int {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            INSERT INTO public.assinatura_documentos (
                assinatura_id,
                documento_contratual_id,
                responsavel_id,
                titulo_snapshot,
                versao_snapshot,
                status,
                provider,
                provider_document_id,
                origem_registro,
                storage_key_original,
                hash_original,
                tamanho_original_bytes
            )
            VALUES (
                :assinatura_id,
                :documento_contratual_id,
                :responsavel_id,
                :titulo_snapshot,
                :versao_snapshot,
                \'GERADO\',
                NULL,
                NULL,
                \'MANUAL\',
                :storage_key_original,
                :hash_original,
                :tamanho_original_bytes
            )
            RETURNING id
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':documento_contratual_id',
            $documentoContratualId,
            PDO::PARAM_INT
        );

        if ($responsavelId === null) {
            $statement->bindValue(
                ':responsavel_id',
                null,
                PDO::PARAM_NULL
            );
        } else {
            $statement->bindValue(
                ':responsavel_id',
                $responsavelId,
                PDO::PARAM_INT
            );
        }

        $statement->bindValue(
            ':titulo_snapshot',
            $tituloSnapshot,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':versao_snapshot',
            $versaoSnapshot,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':storage_key_original',
            $storageKeyOriginal,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':hash_original',
            $hashOriginal,
            PDO::PARAM_STR
        );

        if ($tamanhoOriginalBytes === null) {
            $statement->bindValue(
                ':tamanho_original_bytes',
                null,
                PDO::PARAM_NULL
            );
        } else {
            $statement->bindValue(
                ':tamanho_original_bytes',
                $tamanhoOriginalBytes,
                PDO::PARAM_INT
            );
        }

        $statement->execute();

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException(
                'Não foi possível obter o ID do documento gerado.'
            );
        }

        $assinaturaDocumentoId = (int) $id;

        if ($assinaturaDocumentoId <= 0) {
            throw new RuntimeException(
                'ID inválido retornado ao registrar documento gerado.'
            );
        }

        return $assinaturaDocumentoId;
    }

    /**
     * Marca o envio manual para assinatura.
     *
     * provider é definido pelo backend a partir de whitelist.
     */
    public function markWaitingManual(
        int $assinaturaDocumentoId,
        string $provider
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.assinatura_documentos
            SET
                status = \'AGUARDANDO_ASSINATURA\',
                provider = :provider,
                provider_document_id = NULL,
                origem_registro = \'MANUAL\',
                enviado_em = NOW(),
                atualizado_em = NOW()
            WHERE id = :assinatura_documento_id
              AND status = \'GERADO\'
            '
        );

        $statement->bindValue(
            ':provider',
            $provider,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':assinatura_documento_id',
            $assinaturaDocumentoId,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->rowCount() === 1;
    }

    /**
     * Registra o PDF assinado no fluxo manual.
     *
     * Hash e storage key devem ser calculados/gerados pelo backend.
     */
    public function markSignedManual(
        int $assinaturaDocumentoId,
        int $responsavelId,
        string $storageKeyAssinado,
        string $hashAssinado,
        ?int $tamanhoAssinadoBytes,
        string $signatarioNome,
        ?string $signatarioEmail,
        string $assinadoData
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.assinatura_documentos
            SET
                status = \'ASSINADO\',
                responsavel_id = :responsavel_id,
                storage_key_assinado = :storage_key_assinado,
                hash_assinado = :hash_assinado,
                tamanho_assinado_bytes = :tamanho_assinado_bytes,
                signatario_nome = :signatario_nome,
                signatario_email = :signatario_email,
                assinado_data = :assinado_data,
                assinado_em = NULL,
                atualizado_em = NOW()
            WHERE id = :assinatura_documento_id
              AND status = \'AGUARDANDO_ASSINATURA\'
              AND provider IS NOT NULL
              AND enviado_em IS NOT NULL
            '
        );

        $statement->bindValue(
            ':responsavel_id',
            $responsavelId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':storage_key_assinado',
            $storageKeyAssinado,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':hash_assinado',
            $hashAssinado,
            PDO::PARAM_STR
        );

        if ($tamanhoAssinadoBytes === null) {
            $statement->bindValue(
                ':tamanho_assinado_bytes',
                null,
                PDO::PARAM_NULL
            );
        } else {
            $statement->bindValue(
                ':tamanho_assinado_bytes',
                $tamanhoAssinadoBytes,
                PDO::PARAM_INT
            );
        }

        $statement->bindValue(
            ':signatario_nome',
            $signatarioNome,
            PDO::PARAM_STR
        );

        if ($signatarioEmail === null) {
            $statement->bindValue(
                ':signatario_email',
                null,
                PDO::PARAM_NULL
            );
        } else {
            $statement->bindValue(
                ':signatario_email',
                $signatarioEmail,
                PDO::PARAM_STR
            );
        }

        $statement->bindValue(
            ':assinado_data',
            $assinadoData,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':assinatura_documento_id',
            $assinaturaDocumentoId,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->rowCount() === 1;
    }

    /**
     * Cancela uma tentativa ainda não assinada.
     */
    public function cancelAttempt(
        int $assinaturaDocumentoId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.assinatura_documentos
            SET
                status = \'CANCELADO\',
                cancelado_em = NOW(),
                atualizado_em = NOW()
            WHERE id = :assinatura_documento_id
              AND status IN (
                    \'GERADO\',
                    \'AGUARDANDO_ASSINATURA\'
              )
            '
        );

        $statement->bindValue(
            ':assinatura_documento_id',
            $assinaturaDocumentoId,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->rowCount() === 1;
    }
}
