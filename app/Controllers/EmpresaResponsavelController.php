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
    private const PAGE_STYLES = [
        'clientes.css',
    ];

    private const FORM_FIELDS = [
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
            $id
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
            'clientes',
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
                self::PAGE_STYLES,
            ]
        );
    }

    /**
     * Exibe cadastro.
     */
    public function create(
        string $id
    ): void {
        $empresaId = $this->validateId(
            $id
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
     * Processa cadastro.
     */
    public function store(
        string $id
    ): void {
        $empresaId = $this->validateId(
            $id
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
            'clientes',
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
                self::PAGE_STYLES,
            ]
        );
    }

    /**
     * Whitelist dos campos recebidos.
     */
    private function formInput(
        array $input
    ): array {
        $data = [];

        foreach (
            self::FORM_FIELDS
            as $field
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
            is_string(
                $data['nome']
                    ?? null
            )
                ? $data['nome']
                : '',

            'email' =>
            is_string(
                $data['email']
                    ?? null
            )
                ? $data['email']
                : '',

            'telefone' =>
            is_string(
                $data['telefone']
                    ?? null
            )
                ? $data['telefone']
                : '',

            'cargo' =>
            is_string(
                $data['cargo']
                    ?? null
            )
                ? $data['cargo']
                : '',

            'principal' => ($data['principal'] ?? false)
                === true
                ? '1'
                : '',
        ];
    }

    private function validateId(
        string $id
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
                'Cliente não encontrado.'
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
