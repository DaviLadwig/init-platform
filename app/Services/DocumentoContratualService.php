<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\AssinaturaDocumentoRepository;
use App\Repositories\AssinaturaRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\DocumentoContratualRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use RuntimeException;
use Throwable;

final class DocumentoContratualService
{
    private const PROVIDERS_MANUAIS = [
        'AUTENTIQUE',
        'ZAPSIGN',
        'OUTRO',
    ];

    public function __construct(
        private readonly DocumentoContratualRepository $documentos,
        private readonly AssinaturaDocumentoRepository $assinaturaDocumentos,
        private readonly AssinaturaRepository $assinaturas,
        private readonly AuditLogRepository $auditoria,
        private readonly ContratoStorageService $storage
    ) {}

    /**
     * Catálogo contratual administrativo.
     */
    public function listarCatalogo(): array
    {
        return $this->documentos->findAll();
    }

    /**
     * Documentos já vinculados a uma assinatura.
     */
    public function listarDaAssinatura(
        int $assinaturaId
    ): ?array {
        $assinatura =
            $this->assinaturas
                ->findById(
                    $assinaturaId
                );

        if ($assinatura === null) {
            return null;
        }

        return [
            'assinatura' =>
                $assinatura,

            'documentos' =>
                $this->assinaturaDocumentos
                    ->findByAssinaturaId(
                        $assinaturaId
                    ),
        ];
    }


    /**
     * Prepara dados seguros para a seção documental da tela da assinatura.
     *
     * Não expõe storage_key, hash, caminho físico ou provider_document_id.
     */
    public function prepararPainelDaAssinatura(
        int $assinaturaId
    ): ?array {
        $assinatura =
            $this->assinaturas
                ->findById(
                    $assinaturaId
                );

        if ($assinatura === null) {
            return null;
        }

        $produtoId =
            $this->positiveInt(
                $assinatura['produto_id']
                ?? null
            );

        $empresaId =
            $this->positiveInt(
                $assinatura['empresa_id']
                ?? null
            );

        if (
            $produtoId === null
            || $empresaId === null
        ) {
            throw new RuntimeException(
                'Assinatura possui vínculos inválidos para o painel documental.'
            );
        }

        $modelosRaw =
            $this->documentos
                ->findApplicableActiveByProduct(
                    $produtoId,
                    false
                );

        $documentos =
            $this->assinaturaDocumentos
                ->findByAssinaturaId(
                    $assinaturaId
                );

        $responsaveis =
            $this->assinaturaDocumentos
                ->findActiveResponsiblesByCompany(
                    $empresaId
                );

        $modelos = [];

        foreach ($modelosRaw as $modelo) {
            if (!is_array($modelo)) {
                continue;
            }

            $modeloId =
                $this->positiveInt(
                    $modelo['id']
                    ?? null
                );

            if ($modeloId === null) {
                throw new RuntimeException(
                    'Versão contratual inválida no catálogo.'
                );
            }

            $tentativaVigente = null;

            foreach ($documentos as $documento) {
                if (!is_array($documento)) {
                    continue;
                }

                if (
                    (int) (
                        $documento['documento_contratual_id']
                        ?? 0
                    ) !== $modeloId
                ) {
                    continue;
                }

                if (
                    (
                        $documento['status']
                        ?? null
                    ) === 'CANCELADO'
                ) {
                    continue;
                }

                $tentativaVigente =
                    $documento;

                break;
            }

            $modelos[] = [
                'id' =>
                    $modeloId,

                'tipo' =>
                    $this->stringValue(
                        $modelo,
                        'tipo'
                    ),

                'titulo' =>
                    $this->stringValue(
                        $modelo,
                        'titulo'
                    ),

                'versao' =>
                    $this->stringValue(
                        $modelo,
                        'versao'
                    ),

                'descricao' =>
                    $this->nullableStringValue(
                        $modelo,
                        'descricao'
                    ),

                'obrigatorio_ativacao' =>
                    $this->toBool(
                        $modelo['obrigatorio_ativacao']
                        ?? false
                    ),

                'escopo' =>
                    $modelo['produto_id'] === null
                        ? 'GLOBAL'
                        : 'PRODUTO',

                'tentativa_vigente' =>
                    $tentativaVigente,
            ];
        }

        $cancelados = [];

        foreach ($documentos as $documento) {
            if (
                is_array($documento)
                && (
                    $documento['status']
                    ?? null
                ) === 'CANCELADO'
            ) {
                $cancelados[] =
                    $documento;
            }
        }

        $requisitos =
            $this->verificarRequisitosDeAtivacao(
                $assinaturaId
            );

        if ($requisitos === null) {
            throw new RuntimeException(
                'Não foi possível avaliar os requisitos documentais.'
            );
        }

        return [
            'modelos' =>
                $modelos,

            'responsaveis' =>
                $responsaveis,

            'cancelados' =>
                $cancelados,

            'requisitos_ativacao' =>
                $requisitos,
        ];
    }

    /**
     * Retorna a situação documental necessária para ativação.
     *
     * IMPORTANTE:
     * nesta etapa este método ainda NÃO está conectado ao
     * AssinaturaService::ativar(). A conexão será feita em uma
     * etapa posterior, depois de validarmos este backend.
     */
    public function verificarRequisitosDeAtivacao(
        int $assinaturaId
    ): ?array {
        $assinatura =
            $this->assinaturas
                ->findById(
                    $assinaturaId
                );

        if ($assinatura === null) {
            return null;
        }

        $produtoId =
            $this->positiveInt(
                $assinatura['produto_id']
                ?? null
            );

        if ($produtoId === null) {
            throw new RuntimeException(
                'Produto inválido vinculado à assinatura.'
            );
        }

        $obrigatorios =
            $this->documentos
                ->findApplicableActiveByProduct(
                    $produtoId,
                    true
                );

        /*
         * Se ainda não houver termo obrigatório cadastrado,
         * não bloqueamos nada. O bloqueio só existirá quando
         * existir uma versão ativa e obrigatória.
         */
        if ($obrigatorios === []) {
            return [
                'pronto' => true,
                'total_obrigatorios' => 0,
                'total_assinados' => 0,
                'pendencias' => [],
            ];
        }

        $assinados =
            $this->assinaturaDocumentos
                ->findSignedDocumentModelIds(
                    $assinaturaId
                );

        $assinadosLookup =
            array_fill_keys(
                $assinados,
                true
            );

        $pendencias = [];
        $totalAssinados = 0;

        foreach ($obrigatorios as $documento) {
            if (!is_array($documento)) {
                continue;
            }

            $documentoId =
                $this->positiveInt(
                    $documento['id']
                    ?? null
                );

            if ($documentoId === null) {
                throw new RuntimeException(
                    'Documento obrigatório inválido no catálogo.'
                );
            }

            if (
                isset(
                    $assinadosLookup[
                        $documentoId
                    ]
                )
            ) {
                $totalAssinados++;
                continue;
            }

            $pendencias[] = [
                'documento_contratual_id' =>
                    $documentoId,

                'tipo' =>
                    $this->stringValue(
                        $documento,
                        'tipo'
                    ),

                'titulo' =>
                    $this->stringValue(
                        $documento,
                        'titulo'
                    ),

                'versao' =>
                    $this->stringValue(
                        $documento,
                        'versao'
                    ),
            ];
        }

        return [
            'pronto' =>
                $pendencias === [],

            'total_obrigatorios' =>
                count($obrigatorios),

            'total_assinados' =>
                $totalAssinados,

            'pendencias' =>
                $pendencias,
        ];
    }

    /**
     * Recebe um PDF original por upload HTTP e executa o fluxo completo:
     *
     * upload -> storage privado -> SHA-256 -> registro no banco.
     *
     * O navegador fornece apenas o arquivo e os IDs de contexto.
     * storage_key, hash, tamanho e MIME são produzidos pelo backend.
     */
    public function registrarOriginalUpload(
        int $assinaturaId,
        int $documentoContratualId,
        ?int $responsavelId,
        array $file,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $contexto =
            $this->resolverContextoDeStorageDaAssinatura(
                $assinaturaId
            );

        if ($contexto === null) {
            return [
                'success' => false,
                'not_found' => true,
                'message' =>
                    'Assinatura não encontrada.',
            ];
        }

        if ($contexto['status'] === 'CANCELADA') {
            return [
                'success' => false,
                'not_found' => false,
                'message' =>
                    'Não é possível gerar documento para uma assinatura cancelada.',
            ];
        }

        $stored =
            $this->storage
                ->storeUploadedPdf(
                    $file,
                    $contexto['empresa_id'],
                    $assinaturaId,
                    ContratoStorageService::TIPO_ORIGINAL
                );

        try {
            $result =
                $this->registrarGerado(
                    $assinaturaId,
                    $documentoContratualId,
                    $responsavelId,
                    $stored['storage_key'],
                    $stored['sha256'],
                    $stored['size_bytes'],
                    $usuarioId,
                    $ip,
                    $userAgent
                );

            if (
                ($result['success'] ?? false)
                !== true
            ) {
                $this->storage
                    ->removeUncommitted(
                        $stored['storage_key']
                    );
            }

            return $result;
        } catch (Throwable $exception) {
            $this->storage
                ->removeUncommitted(
                    $stored['storage_key']
                );

            throw $exception;
        }
    }

    /**
     * Registra um PDF original criado pelo próprio backend.
     *
     * Útil para o futuro gerador automático do Termo de Contratação.
     */
    public function registrarOriginalGeradoPeloBackend(
        int $assinaturaId,
        int $documentoContratualId,
        ?int $responsavelId,
        string $sourcePath,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $contexto =
            $this->resolverContextoDeStorageDaAssinatura(
                $assinaturaId
            );

        if ($contexto === null) {
            return [
                'success' => false,
                'not_found' => true,
                'message' =>
                    'Assinatura não encontrada.',
            ];
        }

        if ($contexto['status'] === 'CANCELADA') {
            return [
                'success' => false,
                'not_found' => false,
                'message' =>
                    'Não é possível gerar documento para uma assinatura cancelada.',
            ];
        }

        $stored =
            $this->storage
                ->storeBackendPdf(
                    $sourcePath,
                    $contexto['empresa_id'],
                    $assinaturaId,
                    ContratoStorageService::TIPO_ORIGINAL
                );

        try {
            $result =
                $this->registrarGerado(
                    $assinaturaId,
                    $documentoContratualId,
                    $responsavelId,
                    $stored['storage_key'],
                    $stored['sha256'],
                    $stored['size_bytes'],
                    $usuarioId,
                    $ip,
                    $userAgent
                );

            if (
                ($result['success'] ?? false)
                !== true
            ) {
                $this->storage
                    ->removeUncommitted(
                        $stored['storage_key']
                    );
            }

            return $result;
        } catch (Throwable $exception) {
            $this->storage
                ->removeUncommitted(
                    $stored['storage_key']
                );

            throw $exception;
        }
    }

    /**
     * Recebe o PDF final assinado e executa:
     *
     * upload -> storage privado -> SHA-256 -> validação do signatário
     * -> persistência -> ASSINADO.
     */
    public function registrarAssinadoUploadManual(
        int $assinaturaId,
        int $assinaturaDocumentoId,
        int $responsavelId,
        array $file,
        string $assinadoData,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $documento =
            $this->assinaturaDocumentos
                ->findById(
                    $assinaturaDocumentoId
                );

        if ($documento === null) {
            return [
                'success' => false,
                'not_found' => true,
                'message' =>
                    'Documento da assinatura não encontrado.',
            ];
        }

        if (
            (int) (
                $documento['assinatura_id']
                ?? 0
            ) !== $assinaturaId
        ) {
            /*
             * Não revelamos se o documento pertence a outra assinatura.
             * Para a rota contextual, o resultado é simplesmente 404.
             */
            return [
                'success' => false,
                'not_found' => true,
                'message' =>
                    'Documento da assinatura não encontrado.',
            ];
        }

        $contexto =
            $this->resolverContextoDeStorageDaAssinatura(
                $assinaturaId
            );

        if ($contexto === null) {
            return [
                'success' => false,
                'not_found' => true,
                'message' =>
                    'Assinatura não encontrada.',
            ];
        }

        if ($contexto['status'] === 'CANCELADA') {
            return [
                'success' => false,
                'not_found' => false,
                'message' =>
                    'Não é possível registrar documento assinado em uma assinatura cancelada.',
            ];
        }

        $stored =
            $this->storage
                ->storeUploadedPdf(
                    $file,
                    $contexto['empresa_id'],
                    $assinaturaId,
                    ContratoStorageService::TIPO_ASSINADO
                );

        try {
            $result =
                $this->registrarAssinadoManual(
                    $assinaturaId,
                    $assinaturaDocumentoId,
                    $responsavelId,
                    $stored['storage_key'],
                    $stored['sha256'],
                    $stored['size_bytes'],
                    $assinadoData,
                    $usuarioId,
                    $ip,
                    $userAgent
                );

            if (
                ($result['success'] ?? false)
                !== true
            ) {
                $this->storage
                    ->removeUncommitted(
                        $stored['storage_key']
                    );
            }

            return $result;
        } catch (Throwable $exception) {
            $this->storage
                ->removeUncommitted(
                    $stored['storage_key']
                );

            throw $exception;
        }
    }

    /**
     * Resolve um documento para download autenticado.
     *
     * O método não envia bytes e não cria URL pública.
     * Ele apenas devolve o caminho interno depois de:
     * - conferir documento;
     * - conferir vínculo com a assinatura;
     * - escolher ORIGINAL/ASSINADO;
     * - validar novamente SHA-256 no storage.
     */
    public function resolverDownload(
        int $assinaturaId,
        int $assinaturaDocumentoId,
        string $tipo
    ): ?array {
        $documento =
            $this->assinaturaDocumentos
                ->findById(
                    $assinaturaDocumentoId
                );

        if ($documento === null) {
            return null;
        }

        if (
            (int) (
                $documento['assinatura_id']
                ?? 0
            ) !== $assinaturaId
        ) {
            return null;
        }

        $tipo =
            strtoupper(
                trim(
                    $tipo
                )
            );

        if (
            !in_array(
                $tipo,
                [
                    ContratoStorageService::TIPO_ORIGINAL,
                    ContratoStorageService::TIPO_ASSINADO,
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Tipo de download documental inválido.'
            );
        }

        if (
            $tipo
            === ContratoStorageService::TIPO_ORIGINAL
        ) {
            $storageKey =
                $this->nullableStringValue(
                    $documento,
                    'storage_key_original'
                );

            $sha256 =
                $this->nullableStringValue(
                    $documento,
                    'hash_original'
                );
        } else {
            $storageKey =
                $this->nullableStringValue(
                    $documento,
                    'storage_key_assinado'
                );

            $sha256 =
                $this->nullableStringValue(
                    $documento,
                    'hash_assinado'
                );
        }

        if (
            $storageKey === null
            || $sha256 === null
        ) {
            return null;
        }

        $resolved =
            $this->storage
                ->resolveForRead(
                    $storageKey,
                    $sha256
                );

        return [
            'absolute_path' =>
                $resolved['absolute_path'],

            'storage_key' =>
                $resolved['storage_key'],

            'sha256' =>
                $resolved['sha256'],

            'size_bytes' =>
                $resolved['size_bytes'],

            'mime_type' =>
                $resolved['mime_type'],

            'download_name' =>
                $this->buildDownloadName(
                    $documento,
                    $tipo
                ),
        ];
    }

    /**
     * Registra um PDF original que já foi armazenado pelo backend.
     *
     * O navegador nunca deverá informar hash ou storage key
     * diretamente. Na futura Controller, estes valores virão do
     * serviço de armazenamento após validar o arquivo.
     */
    public function registrarGerado(
        int $assinaturaId,
        int $documentoContratualId,
        ?int $responsavelId,
        string $storageKeyOriginal,
        string $hashOriginal,
        ?int $tamanhoOriginalBytes,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $storageKeyOriginal =
            $this->normalizeStorageKey(
                $storageKeyOriginal
            );

        $hashOriginal =
            $this->normalizeSha256(
                $hashOriginal
            );

        $this->validateFileSize(
            $tamanhoOriginalBytes
        );

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $assinatura =
                $this->assinaturas
                    ->lockById(
                        $assinaturaId
                    );

            if ($assinatura === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'message' =>
                        'Assinatura não encontrada.',
                ];
            }

            $statusAssinatura =
                $this->stringValue(
                    $assinatura,
                    'status'
                );

            if ($statusAssinatura === 'CANCELADA') {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Não é possível gerar documento para uma assinatura cancelada.',
                ];
            }

            $produtoId =
                $this->positiveInt(
                    $assinatura['produto_id']
                    ?? null
                );

            $empresaId =
                $this->positiveInt(
                    $assinatura['empresa_id']
                    ?? null
                );

            if (
                $produtoId === null
                || $empresaId === null
            ) {
                throw new RuntimeException(
                    'Assinatura possui vínculos inválidos.'
                );
            }

            $documento =
                $this->documentos
                    ->lockById(
                        $documentoContratualId
                    );

            if ($documento === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'message' =>
                        'Documento contratual não encontrado.',
                ];
            }

            if (
                !$this->toBool(
                    $documento['ativo']
                    ?? false
                )
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Esta versão contratual não está ativa.',
                ];
            }

            $escopoProdutoId =
                $this->nullablePositiveInt(
                    $documento['produto_id']
                    ?? null
                );

            if (
                $escopoProdutoId !== null
                && $escopoProdutoId !== $produtoId
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Esta versão contratual não pertence ao produto da assinatura.',
                ];
            }

            /*
             * Valida também a regra de precedência global x específica.
             * Um global não deve ser gerado se houver versão específica
             * ativa do mesmo tipo para o produto.
             */
            $aplicaveis =
                $this->documentos
                    ->findApplicableActiveByProduct(
                        $produtoId,
                        false
                    );

            $documentoEhAplicavel = false;

            foreach ($aplicaveis as $aplicavel) {
                if (
                    is_array($aplicavel)
                    && (int) (
                        $aplicavel['id']
                        ?? 0
                    ) === $documentoContratualId
                ) {
                    $documentoEhAplicavel = true;
                    break;
                }
            }

            if (!$documentoEhAplicavel) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Existe outra versão ativa aplicável a esta assinatura.',
                ];
            }

            if ($responsavelId !== null) {
                $responsavel =
                    $this->assinaturaDocumentos
                        ->findActiveResponsibleForCompany(
                            $responsavelId,
                            $empresaId
                        );

                if ($responsavel === null) {
                    $pdo->rollBack();

                    return [
                        'success' => false,
                        'not_found' => false,
                        'message' =>
                            'O responsável selecionado não está ativo ou não pertence ao cliente.',
                    ];
                }
            }

            $tentativaAtual =
                $this->assinaturaDocumentos
                    ->findCurrentAttempt(
                        $assinaturaId,
                        $documentoContratualId
                    );

            if ($tentativaAtual !== null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Esta versão já possui um documento vigente para a assinatura.',
                ];
            }

            $titulo =
                $this->stringValue(
                    $documento,
                    'titulo'
                );

            $versao =
                $this->stringValue(
                    $documento,
                    'versao'
                );

            $assinaturaDocumentoId =
                $this->assinaturaDocumentos
                    ->createGenerated(
                        $assinaturaId,
                        $documentoContratualId,
                        $responsavelId,
                        $titulo,
                        $versao,
                        $storageKeyOriginal,
                        $hashOriginal,
                        $tamanhoOriginalBytes
                    );

            $this->auditoria->create(
                $usuarioId,
                'ASSINATURA_DOCUMENTO_GERADO',
                'assinaturas',
                'assinatura_documento',
                $assinaturaDocumentoId,
                $ip,
                $userAgent,
                null,
                [
                    'assinatura_id' =>
                        $assinaturaId,

                    'documento_contratual_id' =>
                        $documentoContratualId,

                    'responsavel_id' =>
                        $responsavelId,

                    'status' =>
                        'GERADO',

                    'hash_original' =>
                        $hashOriginal,
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'not_found' => false,
                'assinatura_documento_id' =>
                    $assinaturaDocumentoId,
                'message' =>
                    'Documento contratual registrado com sucesso.',
            ];
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($exception->getCode() === '23505') {
                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Esta versão já possui um documento vigente para a assinatura.',
                ];
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Registra que o PDF foi enviado manualmente ao provedor.
     */
    public function marcarAguardandoAssinatura(
        int $assinaturaId,
        int $assinaturaDocumentoId,
        string $provider,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $provider =
            strtoupper(
                trim(
                    $provider
                )
            );

        if (
            !in_array(
                $provider,
                self::PROVIDERS_MANUAIS,
                true
            )
        ) {
            return [
                'success' => false,
                'not_found' => false,
                'message' =>
                    'Provedor de assinatura inválido.',
            ];
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $documento =
                $this->assinaturaDocumentos
                    ->lockById(
                        $assinaturaDocumentoId
                    );

            if ($documento === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'message' =>
                        'Documento da assinatura não encontrado.',
                ];
            }

            if (
                (int) (
                    $documento['assinatura_id']
                    ?? 0
                ) !== $assinaturaId
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'message' =>
                        'Documento da assinatura não encontrado.',
                ];
            }

            if (
                $this->stringValue(
                    $documento,
                    'status'
                ) !== 'GERADO'
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Somente documentos gerados podem ser enviados para assinatura.',
                ];
            }

            $assinatura =
                $this->assinaturas
                    ->lockById(
                        $assinaturaId
                    );

            if ($assinatura === null) {
                throw new RuntimeException(
                    'Assinatura vinculada ao documento não foi encontrada.'
                );
            }

            if (
                $this->stringValue(
                    $assinatura,
                    'status'
                ) === 'CANCELADA'
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Não é possível enviar documento de uma assinatura cancelada.',
                ];
            }

            $empresaId =
                $this->positiveInt(
                    $assinatura['empresa_id']
                    ?? null
                );

            $responsavelId =
                $this->positiveInt(
                    $documento['responsavel_id']
                    ?? null
                );

            if (
                $empresaId === null
                || $responsavelId === null
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Defina um responsável ativo antes de enviar o documento para assinatura.',
                ];
            }

            $responsavel =
                $this->assinaturaDocumentos
                    ->findActiveResponsibleForCompany(
                        $responsavelId,
                        $empresaId
                    );

            if ($responsavel === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'O responsável do documento não está ativo ou não pertence ao cliente.',
                ];
            }

            if (
                !$this->assinaturaDocumentos
                    ->markWaitingManual(
                        $assinaturaDocumentoId,
                        $provider
                    )
            ) {
                throw new RuntimeException(
                    'O documento não pôde ser marcado como aguardando assinatura.'
                );
            }

            $this->auditoria->create(
                $usuarioId,
                'ASSINATURA_DOCUMENTO_ENVIADO',
                'assinaturas',
                'assinatura_documento',
                $assinaturaDocumentoId,
                $ip,
                $userAgent,
                [
                    'status' =>
                        'GERADO',
                ],
                [
                    'status' =>
                        'AGUARDANDO_ASSINATURA',

                    'provider' =>
                        $provider,

                    'origem_registro' =>
                        'MANUAL',
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'not_found' => false,
                'message' =>
                    'Documento marcado como aguardando assinatura.',
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Registra manualmente o PDF final retornado pelo provedor.
     *
     * storage key e SHA-256 devem vir do backend após upload seguro.
     */
    public function registrarAssinadoManual(
        int $assinaturaId,
        int $assinaturaDocumentoId,
        int $responsavelId,
        string $storageKeyAssinado,
        string $hashAssinado,
        ?int $tamanhoAssinadoBytes,
        string $assinadoData,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $storageKeyAssinado =
            $this->normalizeStorageKey(
                $storageKeyAssinado
            );

        $hashAssinado =
            $this->normalizeSha256(
                $hashAssinado
            );

        $this->validateFileSize(
            $tamanhoAssinadoBytes
        );

        $assinadoData =
            $this->normalizeDate(
                $assinadoData
            );

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $documento =
                $this->assinaturaDocumentos
                    ->lockById(
                        $assinaturaDocumentoId
                    );

            if ($documento === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'message' =>
                        'Documento da assinatura não encontrado.',
                ];
            }

            if (
                (int) (
                    $documento['assinatura_id']
                    ?? 0
                ) !== $assinaturaId
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'message' =>
                        'Documento da assinatura não encontrado.',
                ];
            }

            if (
                $this->stringValue(
                    $documento,
                    'status'
                ) !== 'AGUARDANDO_ASSINATURA'
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'O documento não está aguardando assinatura.',
                ];
            }

            $enviadoEm =
                $this->nullableStringValue(
                    $documento,
                    'enviado_em'
                );

            if ($enviadoEm === null) {
                throw new RuntimeException(
                    'Documento aguardando assinatura sem data de envio.'
                );
            }

            $dataEnvio =
                $this->extractLocalDate(
                    $enviadoEm
                );

            if ($assinadoData < $dataEnvio) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'A data da assinatura não pode ser anterior à data de envio ao Autentique.',
                ];
            }

            $assinatura =
                $this->assinaturas
                    ->lockById(
                        $assinaturaId
                    );

            if ($assinatura === null) {
                throw new RuntimeException(
                    'Assinatura vinculada ao documento não foi encontrada.'
                );
            }

            if (
                $this->stringValue(
                    $assinatura,
                    'status'
                ) === 'CANCELADA'
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Não é possível registrar assinatura documental em contrato cancelado.',
                ];
            }

            $empresaId =
                $this->positiveInt(
                    $assinatura['empresa_id']
                    ?? null
                );

            if ($empresaId === null) {
                throw new RuntimeException(
                    'Empresa inválida vinculada à assinatura.'
                );
            }

            $responsavelDocumentoId =
                $this->positiveInt(
                    $documento['responsavel_id']
                    ?? null
                );

            if (
                $responsavelDocumentoId !== null
                && $responsavelDocumentoId !== $responsavelId
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'O signatário informado é diferente do responsável definido para este documento.',
                ];
            }

            $responsavel =
                $this->assinaturaDocumentos
                    ->findActiveResponsibleForCompany(
                        $responsavelId,
                        $empresaId
                    );

            if ($responsavel === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'O signatário não está ativo ou não pertence ao cliente.',
                ];
            }

            $signatarioNome =
                $this->stringValue(
                    $responsavel,
                    'nome'
                );

            $signatarioEmail =
                $this->nullableStringValue(
                    $responsavel,
                    'email'
                );

            if (
                !$this->assinaturaDocumentos
                    ->markSignedManual(
                        $assinaturaDocumentoId,
                        $responsavelId,
                        $storageKeyAssinado,
                        $hashAssinado,
                        $tamanhoAssinadoBytes,
                        $signatarioNome,
                        $signatarioEmail,
                        $assinadoData
                    )
            ) {
                throw new RuntimeException(
                    'O documento não pôde ser registrado como assinado.'
                );
            }

            $this->auditoria->create(
                $usuarioId,
                'ASSINATURA_DOCUMENTO_ASSINADO',
                'assinaturas',
                'assinatura_documento',
                $assinaturaDocumentoId,
                $ip,
                $userAgent,
                [
                    'status' =>
                        'AGUARDANDO_ASSINATURA',
                ],
                [
                    'status' =>
                        'ASSINADO',

                    'responsavel_id' =>
                        $responsavelId,

                    'provider' =>
                        $this->nullableStringValue(
                            $documento,
                            'provider'
                        ),

                    'hash_assinado' =>
                        $hashAssinado,

                    'assinado_data' =>
                        $assinadoData,
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'not_found' => false,
                'message' =>
                    'Documento assinado registrado com sucesso.',
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Cancela uma tentativa documental ainda não assinada.
     */
    public function cancelarTentativa(
        int $assinaturaId,
        int $assinaturaDocumentoId,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $documento =
                $this->assinaturaDocumentos
                    ->lockById(
                        $assinaturaDocumentoId
                    );

            if ($documento === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'message' =>
                        'Documento da assinatura não encontrado.',
                ];
            }

            if (
                (int) (
                    $documento['assinatura_id']
                    ?? 0
                ) !== $assinaturaId
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'message' =>
                        'Documento da assinatura não encontrado.',
                ];
            }

            $statusAnterior =
                $this->stringValue(
                    $documento,
                    'status'
                );

            if (
                !in_array(
                    $statusAnterior,
                    [
                        'GERADO',
                        'AGUARDANDO_ASSINATURA',
                    ],
                    true
                )
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Somente tentativas ainda não assinadas podem ser canceladas.',
                ];
            }

            if (
                !$this->assinaturaDocumentos
                    ->cancelAttempt(
                        $assinaturaDocumentoId
                    )
            ) {
                throw new RuntimeException(
                    'A tentativa documental não pôde ser cancelada.'
                );
            }

            $this->auditoria->create(
                $usuarioId,
                'ASSINATURA_DOCUMENTO_CANCELADO',
                'assinaturas',
                'assinatura_documento',
                $assinaturaDocumentoId,
                $ip,
                $userAgent,
                [
                    'status' =>
                        $statusAnterior,
                ],
                [
                    'status' =>
                        'CANCELADO',
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'not_found' => false,
                'message' =>
                    'Tentativa documental cancelada com sucesso.',
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Resolve somente os dados mínimos necessários para o storage.
     */
    private function resolverContextoDeStorageDaAssinatura(
        int $assinaturaId
    ): ?array {
        if ($assinaturaId <= 0) {
            return null;
        }

        $assinatura =
            $this->assinaturas
                ->findById(
                    $assinaturaId
                );

        if ($assinatura === null) {
            return null;
        }

        $empresaId =
            $this->positiveInt(
                $assinatura['empresa_id']
                ?? null
            );

        if ($empresaId === null) {
            throw new RuntimeException(
                'Empresa inválida vinculada à assinatura.'
            );
        }

        return [
            'empresa_id' =>
                $empresaId,

            'status' =>
                $this->stringValue(
                    $assinatura,
                    'status'
                ),
        ];
    }

    /**
     * Nome amigável somente para o header Content-Disposition futuro.
     * O nome físico continua aleatório e privado.
     */
    private function buildDownloadName(
        array $documento,
        string $tipo
    ): string {
        $titulo =
            $this->stringValue(
                $documento,
                'titulo_snapshot'
            );

        $versao =
            $this->stringValue(
                $documento,
                'versao_snapshot'
            );

        $suffix =
            $tipo
            === ContratoStorageService::TIPO_ASSINADO
                ? 'assinado'
                : 'original';

        $base =
            $titulo
            . '-'
            . $versao
            . '-'
            . $suffix;

        $base =
            preg_replace(
                '/[^A-Za-z0-9._-]+/u',
                '-',
                $base
            );

        if (
            !is_string(
                $base
            )
            || trim(
                $base,
                '-_.'
            ) === ''
        ) {
            $base =
                'documento-contratual-'
                . $suffix;
        }

        return substr(
            trim(
                $base,
                '-_.'
            ),
            0,
            180
        )
        . '.pdf';
    }

    private function normalizeStorageKey(
        string $value
    ): string {
        $value =
            trim(
                str_replace(
                    '\\',
                    '/',
                    $value
                )
            );

        if (
            $value === ''
            || strlen($value) > 500
            || preg_match(
                '#^https?://#i',
                $value
            ) === 1
            || str_starts_with(
                $value,
                '/'
            )
            || preg_match(
                '#(^|/)\.\.(/|$)#',
                $value
            ) === 1
        ) {
            throw new RuntimeException(
                'Storage key documental inválida.'
            );
        }

        return $value;
    }

    private function normalizeSha256(
        string $value
    ): string {
        $value =
            strtolower(
                trim(
                    $value
                )
            );

        if (
            preg_match(
                '/^[a-f0-9]{64}$/',
                $value
            ) !== 1
        ) {
            throw new RuntimeException(
                'SHA-256 documental inválido.'
            );
        }

        return $value;
    }

    private function validateFileSize(
        ?int $bytes
    ): void {
        if (
            $bytes !== null
            && $bytes <= 0
        ) {
            throw new RuntimeException(
                'Tamanho de arquivo documental inválido.'
            );
        }
    }

    /**
     * Normaliza timestamp informado no fluxo manual.
     *
     * Não aceita data futura. O timestamp permanece com offset
     * para preservar a informação fornecida pela evidência.
     */
    /**
     * Normaliza somente a data civil da assinatura no fluxo manual.
     *
     * Não inventamos horário. Um timestamp exato ficará reservado
     * para futura integração com API/webhook do provedor.
     */
    private function normalizeDate(
        string $value
    ): string {
        $value =
            trim(
                $value
            );

        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $value
            ) !== 1
        ) {
            throw new RuntimeException(
                'Data da assinatura inválida.'
            );
        }

        $timezone =
            new DateTimeZone(
                date_default_timezone_get()
            );

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $value,
                $timezone
            );

        $errors =
            DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    ($errors['warning_count'] ?? 0) > 0
                    || ($errors['error_count'] ?? 0) > 0
                )
            )
            || $date->format('Y-m-d') !== $value
        ) {
            throw new RuntimeException(
                'Data da assinatura inválida.'
            );
        }

        $today =
            new DateTimeImmutable(
                'today',
                $timezone
            );

        if ($date > $today) {
            throw new RuntimeException(
                'A data da assinatura não pode estar no futuro.'
            );
        }

        return $value;
    }

    /**
     * Extrai a data local de um TIMESTAMPTZ persistido pelo PostgreSQL.
     */
    private function extractLocalDate(
        string $value
    ): string {
        try {
            $date =
                new DateTimeImmutable(
                    $value
                );
        } catch (Throwable) {
            throw new RuntimeException(
                'Data de envio documental inválida.'
            );
        }

        $timezone =
            new DateTimeZone(
                date_default_timezone_get()
            );

        return $date
            ->setTimezone(
                $timezone
            )
            ->format(
                'Y-m-d'
            );
    }

    private function positiveInt(
        mixed $value
    ): ?int {
        if (
            is_int($value)
            && $value > 0
        ) {
            return $value;
        }

        if (
            is_string($value)
            && ctype_digit($value)
        ) {
            $normalized =
                (int) $value;

            return $normalized > 0
                ? $normalized
                : null;
        }

        return null;
    }

    private function nullablePositiveInt(
        mixed $value
    ): ?int {
        if ($value === null) {
            return null;
        }

        return $this->positiveInt(
            $value
        );
    }

    private function stringValue(
        array $row,
        string $key
    ): string {
        $value =
            $row[$key]
            ?? null;

        if (!is_string($value)) {
            throw new RuntimeException(
                'Valor textual obrigatório ausente: '
                . $key
                . '.'
            );
        }

        $value =
            trim(
                $value
            );

        if ($value === '') {
            throw new RuntimeException(
                'Valor textual obrigatório vazio: '
                . $key
                . '.'
            );
        }

        return $value;
    }

    private function nullableStringValue(
        array $row,
        string $key
    ): ?string {
        $value =
            $row[$key]
            ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new RuntimeException(
                'Valor textual inválido: '
                . $key
                . '.'
            );
        }

        $value =
            trim(
                $value
            );

        return $value !== ''
            ? $value
            : null;
    }

    private function toBool(
        mixed $value
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(
                strtolower(
                    trim(
                        $value
                    )
                ),
                [
                    '1',
                    't',
                    'true',
                ],
                true
            );
        }

        return false;
    }
}
