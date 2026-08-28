<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\EmpresaResponsavelRepository;
use App\Services\EmpresaResponsavelService;
use RuntimeException;

final class EmpresaResponsavelController
{
    /*
     * Propriedades da interface.
     *
     * Mantidas como propriedades tipadas em vez de constantes
     * para evitar falsos positivos do Intelephense com self::.
     */
    private string $activeMenu = 'clientes';

    private array $pageStyles = [
        'clientes.css',
        'responsaveis.css',
    ];

    private array $formFields = [
        'nome',
        'email',
        'telefone',
        'cargo',
        'principal',
    ];

    private EmpresaResponsavelService $service;

    public function __construct()
    {
        $this->service =
            new EmpresaResponsavelService(
                new ClienteRepository(),
                new EmpresaResponsavelRepository(),
                new AuditLogRepository()
            );
    }

    /**
     * Lista responsáveis da empresa.
     */
    public function index(
        string $id
    ): void {
        $empresaId = $this->validateId(
            $id,
            'Cliente não encontrado.'
        );

        $result = $this->service->listar(
            $empresaId
        );

        if ($result === null) {
            throw new HttpException(
                404,
                'Cliente não encontrado.'
            );
        }

        $this->render(
            'clientes/responsaveis/index.php',
            'Responsáveis',
            $this->activeMenu,
            [
                'empresa' =>
                $result['empresa'],

                'responsaveis' =>
                $result['responsaveis'],

                'success' =>
                $this->consumeFlash(
                    '_flash_success'
                ),

                'error' =>
                $this->consumeFlash(
                    '_flash_error'
                ),

                'pageStyles' =>
                $this->pageStyles,
            ]
        );
    }

    /**
     * Formulário de cadastro.
     */
    public function create(
        string $id
    ): void {
        $empresaId = $this->validateId(
            $id,
            'Cliente não encontrado.'
        );

        $empresa =
            $this->service->buscarEmpresa(
                $empresaId
            );

        if ($empresa === null) {
            throw new HttpException(
                404,
                'Cliente não encontrado.'
            );
        }

        $this->renderCreateForm(
            $empresaId,
            $empresa,
            [],
            $this->emptyFormData()
        );
    }

    /**
     * Cadastro.
     */
    public function store(
        string $id
    ): void {
        $empresaId = $this->validateId(
            $id,
            'Cliente não encontrado.'
        );

        $this->enforceCsrf();

        $context =
            $this->requestContext();

        $result =
            $this->service->cadastrar(
                $empresaId,
                $this->formInput(
                    $_POST
                ),
                $context['usuario_id'],
                $context['ip'],
                $context['user_agent']
            );

        if (
            ($result['not_found'] ?? false)
            === true
        ) {
            throw new HttpException(
                404,
                'Cliente não encontrado.'
            );
        }

        if (
            ($result['success'] ?? false)
            !== true
        ) {
            $empresa =
                $this->service
                ->buscarEmpresa(
                    $empresaId
                );

            if ($empresa === null) {
                throw new HttpException(
                    404,
                    'Cliente não encontrado.'
                );
            }

            $this->renderCreateForm(
                $empresaId,
                $empresa,
                $this->resultErrors(
                    $result
                ),
                $this->resultFormData(
                    $result
                ),
                422
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Responsável cadastrado com sucesso.'
        );

        $this->redirect(
            '/clientes/'
                . $empresaId
                . '/responsaveis',
            303
        );
    }

    /**
     * Formulário de edição.
     */
    public function edit(
        string $id,
        string $responsavelId
    ): void {
        $empresaId = $this->validateId(
            $id,
            'Cliente não encontrado.'
        );

        $responsavelIdInt =
            $this->validateId(
                $responsavelId,
                'Responsável não encontrado.'
            );

        $empresa =
            $this->service->buscarEmpresa(
                $empresaId
            );

        if ($empresa === null) {
            throw new HttpException(
                404,
                'Cliente não encontrado.'
            );
        }

        /*
         * Busca vinculada à empresa.
         *
         * Se o responsável pertencer a outra
         * empresa, retorna 404.
         */
        $responsavel =
            $this->service
            ->buscarResponsavel(
                $empresaId,
                $responsavelIdInt
            );

        if ($responsavel === null) {
            throw new HttpException(
                404,
                'Responsável não encontrado.'
            );
        }

        $this->renderEditForm(
            $empresaId,
            $responsavelIdInt,
            $empresa,
            [],
            $this->responsavelToFormData(
                $responsavel
            )
        );
    }

    /**
     * Processa edição.
     */
    public function update(
        string $id,
        string $responsavelId
    ): void {
        $empresaId = $this->validateId(
            $id,
            'Cliente não encontrado.'
        );

        $responsavelIdInt =
            $this->validateId(
                $responsavelId,
                'Responsável não encontrado.'
            );

        $this->enforceCsrf();

        $context =
            $this->requestContext();

        $result =
            $this->service->editar(
                $empresaId,
                $responsavelIdInt,
                $this->formInput(
                    $_POST
                ),
                $context['usuario_id'],
                $context['ip'],
                $context['user_agent']
            );

        if (
            ($result['not_found'] ?? false)
            === true
        ) {
            throw new HttpException(
                404,
                'Responsável não encontrado.'
            );
        }

        if (
            ($result['success'] ?? false)
            !== true
        ) {
            $empresa =
                $this->service->buscarEmpresa(
                    $empresaId
                );

            $responsavel =
                $this->service
                ->buscarResponsavel(
                    $empresaId,
                    $responsavelIdInt
                );

            if (
                $empresa === null
                || $responsavel === null
            ) {
                throw new HttpException(
                    404,
                    'Responsável não encontrado.'
                );
            }

            $this->renderEditForm(
                $empresaId,
                $responsavelIdInt,
                $empresa,
                $this->resultErrors(
                    $result
                ),
                $this->resultFormData(
                    $result
                ),
                422
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Responsável atualizado com sucesso.'
        );

        $this->redirect(
            '/clientes/'
                . $empresaId
                . '/responsaveis',
            303
        );
    }


    /**
     * Ativa um responsável.
     */
    public function activate(
        string $id,
        string $responsavelId
    ): void {
        $empresaId = $this->validateId(
            $id,
            'Cliente não encontrado.'
        );

        $responsavelIdInt = $this->validateId(
            $responsavelId,
            'Responsável não encontrado.'
        );

        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->ativar(
            $empresaId,
            $responsavelIdInt,
            $context['usuario_id'],
            $context['ip'],
            $context['user_agent']
        );

        if (($result['not_found'] ?? false) === true) {
            throw new HttpException(
                404,
                'Responsável não encontrado.'
            );
        }

        if (($result['success'] ?? false) !== true) {
            Session::set(
                '_flash_error',
                $this->firstResultError(
                    $result,
                    'Não foi possível ativar o responsável.'
                )
            );

            $this->redirect(
                '/clientes/'
                    . $empresaId
                    . '/responsaveis',
                303
            );
        }

        $changed = ($result['changed'] ?? false) === true;

        Session::set(
            '_flash_success',
            $changed
                ? 'Responsável ativado com sucesso.'
                : 'O responsável já estava ativo.'
        );

        $this->redirect(
            '/clientes/'
                . $empresaId
                . '/responsaveis',
            303
        );
    }

    /**
     * Desativa um responsável.
     */
    public function deactivate(
        string $id,
        string $responsavelId
    ): void {
        $empresaId = $this->validateId(
            $id,
            'Cliente não encontrado.'
        );

        $responsavelIdInt = $this->validateId(
            $responsavelId,
            'Responsável não encontrado.'
        );

        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->desativar(
            $empresaId,
            $responsavelIdInt,
            $context['usuario_id'],
            $context['ip'],
            $context['user_agent']
        );

        if (($result['not_found'] ?? false) === true) {
            throw new HttpException(
                404,
                'Responsável não encontrado.'
            );
        }

        if (($result['success'] ?? false) !== true) {
            Session::set(
                '_flash_error',
                $this->firstResultError(
                    $result,
                    'Não foi possível desativar o responsável.'
                )
            );

            $this->redirect(
                '/clientes/'
                    . $empresaId
                    . '/responsaveis',
                303
            );
        }

        $changed = ($result['changed'] ?? false) === true;

        Session::set(
            '_flash_success',
            $changed
                ? 'Responsável desativado com sucesso.'
                : 'O responsável já estava inativo.'
        );

        $this->redirect(
            '/clientes/'
                . $empresaId
                . '/responsaveis',
            303
        );
    }

    private function renderCreateForm(
        int $empresaId,
        array $empresa,
        array $errors,
        array $formData,
        int $statusCode = 200
    ): void {
        if ($statusCode !== 200) {
            http_response_code(
                $statusCode
            );
        }

        $this->render(
            'clientes/responsaveis/create.php',
            'Novo responsável',
            $this->activeMenu,
            [
                'empresaId' =>
                $empresaId,

                'empresa' =>
                $empresa,

                'errors' =>
                $errors,

                'formData' =>
                $formData,

                'pageStyles' =>
                $this->pageStyles,
            ]
        );
    }

    private function renderEditForm(
        int $empresaId,
        int $responsavelId,
        array $empresa,
        array $errors,
        array $formData,
        int $statusCode = 200
    ): void {
        if ($statusCode !== 200) {
            http_response_code(
                $statusCode
            );
        }

        $this->render(
            'clientes/responsaveis/edit.php',
            'Editar responsável',
            $this->activeMenu,
            [
                'empresaId' =>
                $empresaId,

                'responsavelId' =>
                $responsavelId,

                'empresa' =>
                $empresa,

                'errors' =>
                $errors,

                'formData' =>
                $formData,

                'pageStyles' =>
                $this->pageStyles,
            ]
        );
    }

    /**
     * Whitelist dos campos aceitos.
     *
     * empresa_id, ativo e qualquer outro
     * campo enviado manualmente são ignorados.
     */
    private function formInput(
        array $input
    ): array {
        $data = [];

        foreach (
            $this->formFields as $field
        ) {
            $value =
                $input[$field]
                ?? '';

            $data[$field] =
                is_string($value)
                ? $value
                : '';
        }

        return $data;
    }

    private function emptyFormData(): array
    {
        return [
            'nome' => '',
            'email' => '',
            'telefone' => '',
            'cargo' => '',
            'principal' => '',
        ];
    }

    private function responsavelToFormData(
        array $responsavel
    ): array {
        return [
            'nome' =>
            $this->stringValue(
                $responsavel,
                'nome'
            ),

            'email' =>
            $this->stringValue(
                $responsavel,
                'email'
            ),

            'telefone' =>
            $this->stringValue(
                $responsavel,
                'telefone'
            ),

            'cargo' =>
            $this->stringValue(
                $responsavel,
                'cargo'
            ),

            'principal' =>
            $this->boolValue(
                $responsavel['principal'] ?? false
            )
                ? '1'
                : '',
        ];
    }


    private function firstResultError(
        array $result,
        string $fallback
    ): string {
        $errors = $result['errors'] ?? null;

        if (!is_array($errors)) {
            return $fallback;
        }

        foreach ($errors as $message) {
            if (
                is_string($message)
                && $message !== ''
            ) {
                return $message;
            }
        }

        return $fallback;
    }

    private function resultErrors(
        array $result
    ): array {
        $errors =
            $result['errors']
            ?? null;

        return is_array($errors)
            ? $errors
            : [];
    }

    private function resultFormData(
        array $result
    ): array {
        $data =
            $result['data']
            ?? null;

        if (!is_array($data)) {
            return $this->emptyFormData();
        }

        return [
            'nome' =>
            $this->stringValue(
                $data,
                'nome'
            ),

            'email' =>
            $this->stringValue(
                $data,
                'email'
            ),

            'telefone' =>
            $this->stringValue(
                $data,
                'telefone'
            ),

            'cargo' =>
            $this->stringValue(
                $data,
                'cargo'
            ),

            'principal' => ($data['principal'] ?? false)
                === true
                ? '1'
                : '',
        ];
    }

    private function stringValue(
        array $source,
        string $key
    ): string {
        $value =
            $source[$key]
            ?? null;

        return is_string($value)
            ? $value
            : '';
    }

    private function boolValue(
        mixed $value
    ): bool {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 't'
            || $value === 'true';
    }

    private function validateId(
        string $id,
        string $message
    ): int {
        $validated = filter_var(
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

        return $validated;
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

    private function consumeFlash(
        string $key
    ): ?string {
        $value = Session::get(
            $key
        );

        Session::remove(
            $key
        );

        return is_string($value)
            ? $value
            : null;
    }

    private function requestContext(): array
    {
        $auth = Session::get(
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

        /*
         * Não utilizamos X-Forwarded-For
         * até termos proxy confiável configurado.
         */
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

    private function render(
        string $view,
        string $title,
        string $activeMenu,
        array $data = []
    ): void {
        $auth = Session::get(
            'auth'
        );

        $userName = is_array($auth)
            && isset($auth['name'])
            && is_string($auth['name'])
            ? $auth['name']
            : 'Usuário';

        $roles = is_array($auth)
            && isset($auth['roles'])
            && is_array($auth['roles'])
            ? $auth['roles']
            : [];

        $userRole = implode(
            ', ',
            array_filter(
                $roles,
                static fn(
                    mixed $role
                ): bool =>
                is_string($role)
                    && $role !== ''
            )
        );

        $csrfToken =
            Csrf::token();

        $appUrl = rtrim(
            Env::required(
                'APP_URL'
            ),
            '/'
        );

        extract(
            $data,
            EXTR_SKIP
        );

        ob_start();

        require dirname(
            __DIR__,
            2
        )
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . $view;

        $content = ob_get_clean();

        if (!is_string($content)) {
            $content = '';
        }

        require dirname(
            __DIR__,
            2
        )
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . 'layouts'
            . DIRECTORY_SEPARATOR
            . 'main.php';
    }

    private function redirect(
        string $path,
        int $statusCode = 302
    ): never {
        $appUrl = rtrim(
            Env::required(
                'APP_URL'
            ),
            '/'
        );

        $path = '/'
            . ltrim(
                $path,
                '/'
            );

        header(
            'Location: '
                . $appUrl
                . $path,
            true,
            $statusCode
        );

        exit;
    }
}
