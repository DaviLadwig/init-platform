<?php

declare(strict_types=1);

namespace App\Services;

use finfo;
use RuntimeException;
use Throwable;

final class ContratoStorageService
{
    public const TIPO_ORIGINAL = 'ORIGINAL';
    public const TIPO_ASSINADO = 'ASSINADO';

    /**
     * Limite inicial conservador para contratos em PDF.
     *
     * Mantido no backend para que o navegador não controle
     * a política de tamanho. Se futuramente precisarmos tornar
     * configurável, faremos isso por variável de ambiente validada.
     */
    private const MAX_UPLOAD_BYTES = 15 * 1024 * 1024;

    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/x-pdf',
    ];

    private string $projectRoot;
    private string $storageRoot;
    private string $contractsRoot;
    private string $publicRoot;

    public function __construct(
        ?string $projectRoot = null
    ) {
        $root =
            $projectRoot !== null
                ? rtrim(
                    str_replace(
                        '\\',
                        '/',
                        $projectRoot
                    ),
                    '/'
                )
                : str_replace(
                    '\\',
                    '/',
                    dirname(
                        __DIR__,
                        2
                    )
                );

        if ($root === '') {
            throw new RuntimeException(
                'Diretório raiz do projeto inválido.'
            );
        }

        $this->projectRoot =
            $root;

        $this->storageRoot =
            $this->projectRoot
            . '/storage';

        $this->contractsRoot =
            $this->storageRoot
            . '/contracts';

        $this->publicRoot =
            $this->projectRoot
            . '/public';

        $this->ensureStorageStructure();
        $this->assertStorageIsPrivate();
    }

    /**
     * Armazena PDF recebido via upload HTTP.
     *
     * Espera a estrutura de um item de $_FILES.
     *
     * Segurança:
     * - não confia em name/type enviados pelo navegador;
     * - exige upload HTTP legítimo;
     * - valida tamanho;
     * - valida MIME com Fileinfo;
     * - valida assinatura %PDF-;
     * - procura %%EOF no final do arquivo;
     * - gera nome interno aleatório;
     * - calcula SHA-256 no backend.
     */
    public function storeUploadedPdf(
        array $file,
        int $empresaId,
        int $assinaturaId,
        string $tipo
    ): array {
        $this->validateContextIds(
            $empresaId,
            $assinaturaId
        );

        $tipo =
            $this->normalizeDocumentType(
                $tipo
            );

        $error =
            isset($file['error'])
                ? (int) $file['error']
                : UPLOAD_ERR_NO_FILE;

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                $this->uploadErrorMessage(
                    $error
                )
            );
        }

        $tmpName =
            isset($file['tmp_name'])
            && is_string($file['tmp_name'])
                ? $file['tmp_name']
                : '';

        if (
            $tmpName === ''
            || !is_uploaded_file(
                $tmpName
            )
        ) {
            throw new RuntimeException(
                'O arquivo recebido não é um upload HTTP válido.'
            );
        }

        $declaredSize =
            isset($file['size'])
            && is_numeric($file['size'])
                ? (int) $file['size']
                : null;

        $metadata =
            $this->inspectPdf(
                $tmpName,
                $declaredSize
            );

        $destination =
            $this->prepareDestination(
                $empresaId,
                $assinaturaId,
                $tipo
            );

        if (
            !move_uploaded_file(
                $tmpName,
                $destination['absolute_path']
            )
        ) {
            throw new RuntimeException(
                'Não foi possível armazenar o documento enviado.'
            );
        }

        try {
            $this->applyPrivateFilePermissions(
                $destination['absolute_path']
            );

            $storedMetadata =
                $this->inspectPdf(
                    $destination['absolute_path'],
                    $metadata['size_bytes']
                );

            return $this->buildStoredResult(
                $destination['storage_key'],
                $storedMetadata
            );
        } catch (Throwable $exception) {
            $this->safeUnlink(
                $destination['absolute_path']
            );

            throw $exception;
        }
    }

    /**
     * Armazena um PDF criado pelo próprio backend.
     *
     * Útil para o Termo de Contratação gerado futuramente pela Init.
     * O arquivo-fonte não pode estar em public/.
     */
    public function storeBackendPdf(
        string $sourcePath,
        int $empresaId,
        int $assinaturaId,
        string $tipo = self::TIPO_ORIGINAL
    ): array {
        $this->validateContextIds(
            $empresaId,
            $assinaturaId
        );

        $tipo =
            $this->normalizeDocumentType(
                $tipo
            );

        $sourcePath =
            $this->normalizeAbsolutePath(
                $sourcePath
            );

        if (
            !is_file(
                $sourcePath
            )
            || !is_readable(
                $sourcePath
            )
        ) {
            throw new RuntimeException(
                'PDF de origem não encontrado ou sem permissão de leitura.'
            );
        }

        if (
            $this->pathIsInside(
                $sourcePath,
                $this->publicRoot
            )
        ) {
            throw new RuntimeException(
                'Documento contratual não pode ser importado de dentro de public/.'
            );
        }

        $metadata =
            $this->inspectPdf(
                $sourcePath,
                null
            );

        $destination =
            $this->prepareDestination(
                $empresaId,
                $assinaturaId,
                $tipo
            );

        if (
            !copy(
                $sourcePath,
                $destination['absolute_path']
            )
        ) {
            throw new RuntimeException(
                'Não foi possível armazenar o PDF gerado pelo backend.'
            );
        }

        try {
            $this->applyPrivateFilePermissions(
                $destination['absolute_path']
            );

            $storedMetadata =
                $this->inspectPdf(
                    $destination['absolute_path'],
                    $metadata['size_bytes']
                );

            return $this->buildStoredResult(
                $destination['storage_key'],
                $storedMetadata
            );
        } catch (Throwable $exception) {
            $this->safeUnlink(
                $destination['absolute_path']
            );

            throw $exception;
        }
    }

    /**
     * Resolve um arquivo privado para leitura pelo backend.
     *
     * Nunca use o retorno como URL pública.
     * O Controller de download futuro deverá autenticar/autorizar
     * o usuário e então transmitir este arquivo.
     */
    public function resolveForRead(
        string $storageKey,
        ?string $expectedSha256 = null
    ): array {
        $storageKey =
            $this->normalizeStorageKey(
                $storageKey
            );

        $absolutePath =
            $this->storageRoot
            . '/'
            . $storageKey;

        $absolutePath =
            $this->normalizeAbsolutePath(
                $absolutePath
            );

        if (
            !$this->pathIsInside(
                $absolutePath,
                $this->contractsRoot
            )
        ) {
            throw new RuntimeException(
                'Storage key fora da área privada de contratos.'
            );
        }

        if (
            !is_file(
                $absolutePath
            )
            || !is_readable(
                $absolutePath
            )
        ) {
            throw new RuntimeException(
                'Documento contratual não encontrado.'
            );
        }

        $metadata =
            $this->inspectPdf(
                $absolutePath,
                null
            );

        if ($expectedSha256 !== null) {
            $expectedSha256 =
                $this->normalizeSha256(
                    $expectedSha256
                );

            if (
                !hash_equals(
                    $expectedSha256,
                    $metadata['sha256']
                )
            ) {
                throw new RuntimeException(
                    'Falha na verificação de integridade do documento.'
                );
            }
        }

        return [
            'absolute_path' =>
                $absolutePath,

            'storage_key' =>
                $storageKey,

            'sha256' =>
                $metadata['sha256'],

            'size_bytes' =>
                $metadata['size_bytes'],

            'mime_type' =>
                $metadata['mime_type'],
        ];
    }

    /**
     * Remove apenas um arquivo ainda não consolidado no banco.
     *
     * Deve ser usado somente para rollback de uma operação que
     * salvou o PDF no disco mas falhou antes de persistir o registro.
     * Documentos já registrados/assinados não devem ser apagados.
     */
    public function removeUncommitted(
        string $storageKey
    ): void {
        $storageKey =
            $this->normalizeStorageKey(
                $storageKey
            );

        $absolutePath =
            $this->normalizeAbsolutePath(
                $this->storageRoot
                . '/'
                . $storageKey
            );

        if (
            !$this->pathIsInside(
                $absolutePath,
                $this->contractsRoot
            )
        ) {
            throw new RuntimeException(
                'Tentativa de remoção fora do storage contratual.'
            );
        }

        if (
            file_exists(
                $absolutePath
            )
            && !is_file(
                $absolutePath
            )
        ) {
            throw new RuntimeException(
                'A chave informada não representa um arquivo válido.'
            );
        }

        if (
            is_file(
                $absolutePath
            )
            && !unlink(
                $absolutePath
            )
        ) {
            throw new RuntimeException(
                'Não foi possível remover o arquivo de rollback.'
            );
        }
    }

    /**
     * Diagnóstico técnico seguro para CLI.
     *
     * Não expõe segredos, conteúdo de contrato ou nomes de clientes.
     */
    public function healthCheck(): array
    {
        $contractsRealPath =
            realpath(
                $this->contractsRoot
            );

        $publicRealPath =
            realpath(
                $this->publicRoot
            );

        return [
            'contracts_directory_exists' =>
                is_dir(
                    $this->contractsRoot
                ),

            'contracts_directory_writable' =>
                is_writable(
                    $this->contractsRoot
                ),

            'outside_public' =>
                $contractsRealPath !== false
                && (
                    $publicRealPath === false
                    || !$this->pathIsInside(
                        $contractsRealPath,
                        $publicRealPath
                    )
                ),

            'fileinfo_available' =>
                class_exists(
                    finfo::class
                ),

            'max_upload_bytes' =>
                self::MAX_UPLOAD_BYTES,

            'max_upload_mb' =>
                (int) (
                    self::MAX_UPLOAD_BYTES
                    / 1024
                    / 1024
                ),
        ];
    }

    private function inspectPdf(
        string $path,
        ?int $declaredSize
    ): array {
        if (
            !is_file(
                $path
            )
            || !is_readable(
                $path
            )
        ) {
            throw new RuntimeException(
                'Arquivo PDF inexistente ou sem permissão de leitura.'
            );
        }

        $actualSize =
            filesize(
                $path
            );

        if (
            $actualSize === false
            || $actualSize <= 0
        ) {
            throw new RuntimeException(
                'O PDF enviado está vazio ou possui tamanho inválido.'
            );
        }

        if (
            $actualSize
            > self::MAX_UPLOAD_BYTES
        ) {
            throw new RuntimeException(
                'O PDF ultrapassa o limite permitido de 15 MB.'
            );
        }

        if (
            $declaredSize !== null
            && $declaredSize > 0
            && $declaredSize !== $actualSize
        ) {
            throw new RuntimeException(
                'O tamanho do arquivo recebido não corresponde ao upload informado.'
            );
        }

        if (
            !class_exists(
                finfo::class
            )
        ) {
            throw new RuntimeException(
                'A extensão Fileinfo do PHP é necessária para validar documentos PDF.'
            );
        }

        $finfo =
            new finfo(
                FILEINFO_MIME_TYPE
            );

        $mimeType =
            $finfo->file(
                $path
            );

        if (
            !is_string(
                $mimeType
            )
            || !in_array(
                strtolower(
                    trim(
                        $mimeType
                    )
                ),
                self::ALLOWED_MIME_TYPES,
                true
            )
        ) {
            throw new RuntimeException(
                'O arquivo informado não foi reconhecido como PDF válido.'
            );
        }

        $handle =
            fopen(
                $path,
                'rb'
            );

        if ($handle === false) {
            throw new RuntimeException(
                'Não foi possível ler o documento PDF.'
            );
        }

        try {
            $header =
                fread(
                    $handle,
                    5
                );

            if ($header !== '%PDF-') {
                throw new RuntimeException(
                    'Assinatura de arquivo PDF inválida.'
                );
            }

            $tailLength =
                min(
                    4096,
                    $actualSize
                );

            if (
                fseek(
                    $handle,
                    -$tailLength,
                    SEEK_END
                ) !== 0
            ) {
                throw new RuntimeException(
                    'Não foi possível validar o encerramento do PDF.'
                );
            }

            $tail =
                fread(
                    $handle,
                    $tailLength
                );

            if (
                !is_string(
                    $tail
                )
                || strpos(
                    $tail,
                    '%%EOF'
                ) === false
            ) {
                throw new RuntimeException(
                    'Estrutura final do PDF inválida.'
                );
            }
        } finally {
            fclose(
                $handle
            );
        }

        $sha256 =
            hash_file(
                'sha256',
                $path
            );

        if (
            !is_string(
                $sha256
            )
            || preg_match(
                '/^[a-f0-9]{64}$/',
                $sha256
            ) !== 1
        ) {
            throw new RuntimeException(
                'Não foi possível calcular o SHA-256 do documento.'
            );
        }

        return [
            'sha256' =>
                $sha256,

            'size_bytes' =>
                $actualSize,

            'mime_type' =>
                strtolower(
                    trim(
                        $mimeType
                    )
                ),
        ];
    }

    private function prepareDestination(
        int $empresaId,
        int $assinaturaId,
        string $tipo
    ): array {
        $folderName =
            $tipo === self::TIPO_ASSINADO
                ? 'assinado'
                : 'original';

        $relativeDirectory =
            'contracts'
            . '/empresa-'
            . $empresaId
            . '/assinatura-'
            . $assinaturaId
            . '/'
            . $folderName;

        $absoluteDirectory =
            $this->storageRoot
            . '/'
            . $relativeDirectory;

        $this->ensureDirectory(
            $absoluteDirectory
        );

        $randomName =
            strtolower(
                $folderName
            )
            . '-'
            . bin2hex(
                random_bytes(
                    24
                )
            )
            . '.pdf';

        $storageKey =
            $relativeDirectory
            . '/'
            . $randomName;

        $absolutePath =
            $absoluteDirectory
            . '/'
            . $randomName;

        if (
            !$this->pathIsInside(
                $absolutePath,
                $this->contractsRoot
            )
        ) {
            throw new RuntimeException(
                'Destino de armazenamento contratual inválido.'
            );
        }

        if (
            file_exists(
                $absolutePath
            )
        ) {
            throw new RuntimeException(
                'Colisão inesperada ao gerar nome interno de contrato.'
            );
        }

        return [
            'storage_key' =>
                $storageKey,

            'absolute_path' =>
                $absolutePath,
        ];
    }

    private function buildStoredResult(
        string $storageKey,
        array $metadata
    ): array {
        return [
            'storage_key' =>
                $storageKey,

            'sha256' =>
                $metadata['sha256'],

            'size_bytes' =>
                $metadata['size_bytes'],

            'mime_type' =>
                $metadata['mime_type'],
        ];
    }

    private function validateContextIds(
        int $empresaId,
        int $assinaturaId
    ): void {
        if (
            $empresaId <= 0
            || $assinaturaId <= 0
        ) {
            throw new RuntimeException(
                'Contexto de empresa/assinatura inválido para armazenamento.'
            );
        }
    }

    private function normalizeDocumentType(
        string $tipo
    ): string {
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
                    self::TIPO_ORIGINAL,
                    self::TIPO_ASSINADO,
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Tipo de documento contratual inválido.'
            );
        }

        return $tipo;
    }

    private function normalizeStorageKey(
        string $storageKey
    ): string {
        $storageKey =
            trim(
                str_replace(
                    '\\',
                    '/',
                    $storageKey
                )
            );

        if (
            $storageKey === ''
            || strlen(
                $storageKey
            ) > 500
            || !str_starts_with(
                $storageKey,
                'contracts/'
            )
            || str_starts_with(
                $storageKey,
                '/'
            )
            || preg_match(
                '#^https?://#i',
                $storageKey
            ) === 1
            || preg_match(
                '#(^|/)\.\.(/|$)#',
                $storageKey
            ) === 1
        ) {
            throw new RuntimeException(
                'Storage key contratual inválida.'
            );
        }

        return $storageKey;
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
                'SHA-256 esperado inválido.'
            );
        }

        return $value;
    }

    private function normalizeAbsolutePath(
        string $path
    ): string {
        $normalized =
            str_replace(
                '\\',
                '/',
                $path
            );

        $real =
            realpath(
                $normalized
            );

        if ($real !== false) {
            return str_replace(
                '\\',
                '/',
                $real
            );
        }

        return rtrim(
            $normalized,
            '/'
        );
    }

    private function ensureStorageStructure(): void
    {
        $this->ensureDirectory(
            $this->storageRoot
        );

        $this->ensureDirectory(
            $this->contractsRoot
        );
    }

    private function ensureDirectory(
        string $directory
    ): void {
        if (
            !is_dir(
                $directory
            )
            && !mkdir(
                $directory,
                0700,
                true
            )
            && !is_dir(
                $directory
            )
        ) {
            throw new RuntimeException(
                'Não foi possível criar diretório privado de contratos.'
            );
        }

        /*
         * No Windows o chmod pode não produzir o mesmo efeito do Linux,
         * mas mantemos a intenção correta para produção.
         */
        @chmod(
            $directory,
            0700
        );
    }

    private function applyPrivateFilePermissions(
        string $path
    ): void {
        @chmod(
            $path,
            0600
        );
    }

    private function assertStorageIsPrivate(): void
    {
        $contracts =
            realpath(
                $this->contractsRoot
            );

        if ($contracts === false) {
            throw new RuntimeException(
                'Diretório privado de contratos não pôde ser resolvido.'
            );
        }

        $public =
            realpath(
                $this->publicRoot
            );

        if (
            $public !== false
            && $this->pathIsInside(
                $contracts,
                $public
            )
        ) {
            throw new RuntimeException(
                'Configuração insegura: storage/contracts está dentro de public/.'
            );
        }
    }

    private function pathIsInside(
        string $path,
        string $parent
    ): bool {
        $path =
            rtrim(
                str_replace(
                    '\\',
                    '/',
                    $path
                ),
                '/'
            );

        $parent =
            rtrim(
                str_replace(
                    '\\',
                    '/',
                    $parent
                ),
                '/'
            );

        if (
            DIRECTORY_SEPARATOR === '\\'
        ) {
            $path =
                strtolower(
                    $path
                );

            $parent =
                strtolower(
                    $parent
                );
        }

        return $path === $parent
            || str_starts_with(
                $path,
                $parent . '/'
            );
    }

    private function uploadErrorMessage(
        int $error
    ): string {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE =>
                'O arquivo enviado ultrapassa o limite permitido.',

            UPLOAD_ERR_PARTIAL =>
                'O upload do documento foi recebido parcialmente.',

            UPLOAD_ERR_NO_FILE =>
                'Nenhum documento PDF foi enviado.',

            UPLOAD_ERR_NO_TMP_DIR =>
                'Diretório temporário de upload indisponível.',

            UPLOAD_ERR_CANT_WRITE =>
                'O servidor não conseguiu gravar o upload temporário.',

            UPLOAD_ERR_EXTENSION =>
                'O upload foi interrompido por uma extensão do PHP.',

            default =>
                'Falha ao receber o documento enviado.',
        };
    }

    private function safeUnlink(
        string $path
    ): void {
        if (
            is_file(
                $path
            )
        ) {
            @unlink(
                $path
            );
        }
    }
}
