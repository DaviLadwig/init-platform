<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Session;
use App\Repositories\AssinaturaDocumentoRepository;
use App\Repositories\AssinaturaRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\DocumentoContratualRepository;
use App\Services\ContratoStorageService;
use App\Services\DocumentoContratualService;
use RuntimeException;

final class DocumentoContratualController
{
    private const ORIGINAL_UPLOAD_FIELD =
        'documento_pdf';

    private const SIGNED_UPLOAD_FIELD =
        'documento_assinado_pdf';

    private DocumentoContratualService $service;

    public function __construct()
    {
        $this->service =
            new DocumentoContratualService(
                new DocumentoContratualRepository(),
                new AssinaturaDocumentoRepository(),
                new AssinaturaRepository(),
                new AuditLogRepository(),
                new ContratoStorageService()
            );
    }

    /**
     * Registra o PDF original de uma versão contratual.
     *
     * O formulário futuro enviará somente:
     * - documento_contratual_id
     * - responsavel_id
     * - documento_pdf
     *
     * storage key, SHA-256, MIME e tamanho são produzidos no backend.
     */
    public function storeOriginal(
        string $id
    ): void {
        $assinaturaId =
            $this->validateId(
                $id,
                'Assinatura não encontrada.'
            );

        $this->enforceCsrf();

        $documentoContratualId =
            $this->postPositiveInt(
                'documento_contratual_id'
            );

        $responsavelId =
            $this->postPositiveInt(
                'responsavel_id'
            );

        $file =
            $_FILES[
                self::ORIGINAL_UPLOAD_FIELD
            ]
            ?? null;

        if (!is_array($file)) {
            Session::set(
                '_flash_error',
                'Selecione um documento PDF válido.'
            );

            $this->redirectToSubscription(
                $assinaturaId
            );
        }

        $context =
            $this->requestContext();

        $result =
            $this->service
                ->registrarOriginalUpload(
                    $assinaturaId,
                    $documentoContratualId,
                    $responsavelId,
                    $file,
                    $context['usuario_id'],
                    $context['ip'],
                    $context['user_agent']
                );

        $this->handleMutationResult(
            $assinaturaId,
            $result,
            'Documento contratual registrado com sucesso.'
        );
    }

    /**
     * Marca que o documento foi enviado manualmente pelo Autentique.
     *
     * O provider é controlado pelo backend e não pelo navegador.
     */
    public function markSentToAutentique(
        string $id,
        string $documentoId
    ): void {
        $assinaturaId =
            $this->validateId(
                $id,
                'Assinatura não encontrada.'
            );

        $assinaturaDocumentoId =
            $this->validateId(
                $documentoId,
                'Documento da assinatura não encontrado.'
            );

        $this->enforceCsrf();

        $context =
            $this->requestContext();

        $result =
            $this->service
                ->marcarAguardandoAssinatura(
                    $assinaturaId,
                    $assinaturaDocumentoId,
                    'AUTENTIQUE',
                    $context['usuario_id'],
                    $context['ip'],
                    $context['user_agent']
                );

        $this->handleMutationResult(
            $assinaturaId,
            $result,
            'Documento marcado como enviado ao Autentique.'
        );
    }

    /**
     * Recebe o PDF final assinado, baixado manualmente do Autentique.
     */
    public function storeSigned(
        string $id,
        string $documentoId
    ): void {
        $assinaturaId =
            $this->validateId(
                $id,
                'Assinatura não encontrada.'
            );

        $assinaturaDocumentoId =
            $this->validateId(
                $documentoId,
                'Documento da assinatura não encontrado.'
            );

        $this->enforceCsrf();

        $responsavelId =
            $this->postPositiveInt(
                'responsavel_id'
            );

        $assinadoData =
            $_POST['assinado_data']
            ?? '';

        if (!is_string($assinadoData)) {
            $assinadoData = '';
        }

        $file =
            $_FILES[
                self::SIGNED_UPLOAD_FIELD
            ]
            ?? null;

        if (!is_array($file)) {
            Session::set(
                '_flash_error',
                'Selecione o PDF assinado.'
            );

            $this->redirectToSubscription(
                $assinaturaId
            );
        }

        $context =
            $this->requestContext();

        $result =
            $this->service
                ->registrarAssinadoUploadManual(
                    $assinaturaId,
                    $assinaturaDocumentoId,
                    $responsavelId,
                    $file,
                    $assinadoData,
                    $context['usuario_id'],
                    $context['ip'],
                    $context['user_agent']
                );

        $this->handleMutationResult(
            $assinaturaId,
            $result,
            'Documento assinado registrado com sucesso.'
        );
    }

    /**
     * Cancela uma tentativa ainda não assinada.
     */
    public function cancelAttempt(
        string $id,
        string $documentoId
    ): void {
        $assinaturaId =
            $this->validateId(
                $id,
                'Assinatura não encontrada.'
            );

        $assinaturaDocumentoId =
            $this->validateId(
                $documentoId,
                'Documento da assinatura não encontrado.'
            );

        $this->enforceCsrf();

        $context =
            $this->requestContext();

        $result =
            $this->service
                ->cancelarTentativa(
                    $assinaturaId,
                    $assinaturaDocumentoId,
                    $context['usuario_id'],
                    $context['ip'],
                    $context['user_agent']
                );

        $this->handleMutationResult(
            $assinaturaId,
            $result,
            'Tentativa documental cancelada com sucesso.'
        );
    }

    /**
     * Download autenticado do PDF original.
     */
    public function downloadOriginal(
        string $id,
        string $documentoId
    ): void {
        $this->download(
            $id,
            $documentoId,
            ContratoStorageService::TIPO_ORIGINAL
        );
    }

    /**
     * Download autenticado do PDF assinado.
     */
    public function downloadSigned(
        string $id,
        string $documentoId
    ): void {
        $this->download(
            $id,
            $documentoId,
            ContratoStorageService::TIPO_ASSINADO
        );
    }

    private function download(
        string $id,
        string $documentoId,
        string $tipo
    ): void {
        $assinaturaId =
            $this->validateId(
                $id,
                'Assinatura não encontrada.'
            );

        $assinaturaDocumentoId =
            $this->validateId(
                $documentoId,
                'Documento da assinatura não encontrado.'
            );

        $resolved =
            $this->service
                ->resolverDownload(
                    $assinaturaId,
                    $assinaturaDocumentoId,
                    $tipo
                );

        if ($resolved === null) {
            throw new HttpException(
                404,
                'Documento da assinatura não encontrado.'
            );
        }

        $absolutePath =
            $resolved['absolute_path']
            ?? null;

        $downloadName =
            $resolved['download_name']
            ?? null;

        $sizeBytes =
            $resolved['size_bytes']
            ?? null;

        if (
            !is_string($absolutePath)
            || !is_string($downloadName)
            || !is_int($sizeBytes)
            || $sizeBytes <= 0
        ) {
            throw new RuntimeException(
                'Metadados internos do download documental são inválidos.'
            );
        }

        $handle =
            fopen(
                $absolutePath,
                'rb'
            );

        if ($handle === false) {
            throw new RuntimeException(
                'Não foi possível abrir o documento contratual.'
            );
        }

        /*
         * Cabeçalhos específicos para documento privado.
         *
         * Não permitimos cache compartilhado e não expomos caminho físico.
         */
        header(
            'Content-Type: application/pdf'
        );

        header(
            'Content-Length: '
            . (string) $sizeBytes
        );

        header(
            'Content-Disposition: attachment; filename="'
            . $downloadName
            . '"'
        );

        header(
            'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0'
        );

        header(
            'Pragma: no-cache'
        );

        header(
            'Expires: 0'
        );

        header(
            'X-Content-Type-Options: nosniff'
        );

        try {
            fpassthru(
                $handle
            );
        } finally {
            fclose(
                $handle
            );
        }

        exit;
    }

    private function handleMutationResult(
        int $assinaturaId,
        array $result,
        string $successMessage
    ): never {
        if (
            ($result['not_found'] ?? false)
            === true
        ) {
            throw new HttpException(
                404,
                'Documento ou assinatura não encontrado.'
            );
        }

        $success =
            ($result['success'] ?? false)
            === true;

        $message =
            isset($result['message'])
            && is_string($result['message'])
                ? $result['message']
                : (
                    $success
                        ? $successMessage
                        : 'A operação documental não pôde ser concluída.'
                );

        Session::set(
            $success
                ? '_flash_success'
                : '_flash_error',
            $message
        );

        $this->redirectToSubscription(
            $assinaturaId
        );
    }

    private function redirectToSubscription(
        int $assinaturaId
    ): never {
        $this->redirect(
            '/assinaturas/'
                . $assinaturaId,
            303
        );
    }

    private function postPositiveInt(
        string $field
    ): int {
        $value =
            $_POST[$field]
            ?? null;

        if (
            !is_string($value)
            && !is_int($value)
        ) {
            throw new HttpException(
                422,
                'Dados do formulário inválidos.'
            );
        }

        $validated =
            filter_var(
                $value,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

        if ($validated === false) {
            throw new HttpException(
                422,
                'Dados do formulário inválidos.'
            );
        }

        return (int) $validated;
    }

    private function postNullablePositiveInt(
        string $field
    ): ?int {
        $value =
            $_POST[$field]
            ?? null;

        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (
            !is_string($value)
            && !is_int($value)
        ) {
            throw new HttpException(
                422,
                'Dados do formulário inválidos.'
            );
        }

        $validated =
            filter_var(
                $value,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

        if ($validated === false) {
            throw new HttpException(
                422,
                'Dados do formulário inválidos.'
            );
        }

        return (int) $validated;
    }

    private function validateId(
        string $id,
        string $message
    ): int {
        $validated =
            filter_var(
                $id,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            );

        if ($validated === false) {
            throw new HttpException(
                404,
                $message
            );
        }

        return (int) $validated;
    }

    private function enforceCsrf(): void
    {
        $token =
            $_POST['_token']
            ?? null;

        Csrf::enforce(
            is_string($token)
                ? $token
                : null
        );
    }

    /**
     * Contexto mínimo para auditoria.
     *
     * REMOTE_ADDR permanece a única origem de IP até existir
     * proxy reverso confiável configurado.
     */
    private function requestContext(): array
    {
        $auth =
            Session::get(
                'auth'
            );

        if (!is_array($auth)) {
            throw new RuntimeException(
                'Usuário autenticado não identificado.'
            );
        }

        $rawUserId =
            $auth['user_id']
            ?? null;

        if (is_int($rawUserId)) {
            $usuarioId =
                $rawUserId;
        } elseif (
            is_string($rawUserId)
            && ctype_digit(
                $rawUserId
            )
        ) {
            $usuarioId =
                (int) $rawUserId;
        } else {
            $usuarioId = 0;
        }

        if ($usuarioId <= 0) {
            throw new RuntimeException(
                'Usuário autenticado não identificado.'
            );
        }

        $ip =
            $_SERVER['REMOTE_ADDR']
            ?? null;

        if (
            !is_string($ip)
            || filter_var(
                $ip,
                FILTER_VALIDATE_IP
            ) === false
        ) {
            $ip = null;
        }

        $userAgent =
            $_SERVER['HTTP_USER_AGENT']
            ?? null;

        if (is_string($userAgent)) {
            $userAgent =
                mb_substr(
                    $userAgent,
                    0,
                    1000,
                    'UTF-8'
                );
        } else {
            $userAgent = null;
        }

        return [
            'usuario_id' =>
                $usuarioId,

            'ip' =>
                $ip,

            'user_agent' =>
                $userAgent,
        ];
    }

    private function redirect(
        string $path,
        int $statusCode = 302
    ): never {
        $appUrl =
            rtrim(
                Env::required(
                    'APP_URL'
                ),
                '/'
            );

        $normalizedPath =
            '/'
            . ltrim(
                $path,
                '/'
            );

        header(
            'Location: '
            . $appUrl
            . $normalizedPath,
            true,
            $statusCode
        );

        exit;
    }
}
