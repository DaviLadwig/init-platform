<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClienteRepository;
use App\Services\ClienteService;
use RuntimeException;

final class ClienteController
{
    private const ACTIVE_MENU = 'clientes';

    private const PAGE_STYLES = [
        'clientes.css',
    ];

    private const FORM_FIELDS = [
        'razao_social',
        'nome_fantasia',
        'cnpj',
        'email',
        'telefone',
        'slug',
    ];

    private ClienteService $service;

    public function __construct()
    {
        $this->service = new ClienteService(
            new ClienteRepository(),
            new AuditLogRepository()
        );
    }

    /**
     * Lista os clientes cadastrados na plataforma.
     */
    public function index(): void
    {
        $clientes = $this->service->listar();

        $resumo = [
            'total' => count($clientes),
            'ativos' => 0,
            'atencao' => 0,
            'suspensos' => 0,
            'sem_assinatura' => 0,
        ];

        foreach ($clientes as $cliente) {
            if (!is_array($cliente)) {
                continue;
            }

            $situacao = $cliente['situacao_comercial'] ?? '';

            if (!is_string($situacao)) {
                continue;
            }

            switch ($situacao) {
                case 'ATIVO':
                    $resumo['ativos']++;
                    break;

                case 'ATENCAO':
                    $resumo['atencao']++;
                    break;

                case 'SUSPENSO':
                    $resumo['suspensos']++;
                    break;

                case 'SEM_ASSINATURA':
                    $resumo['sem_assinatura']++;
                    break;
            }
        }

        $this->render(
            'clientes/index.php',
            'Clientes',
            self::ACTIVE_MENU,
            [
                'clientes' => $clientes,
                'resumo' => $resumo,
                'success' => $this->consumeFlash('_flash_success'),
                'error' => $this->consumeFlash('_flash_error'),
                'pageStyles' => self::PAGE_STYLES,
            ]
        );
    }

    /**
     * Exibe a ficha cadastral do cliente.
     */
    public function show(string $id): void
    {
        $clienteId = $this->validateId($id);

        $cliente = $this->service->buscar(
            $clienteId
        );

        if ($cliente === null) {
            throw new HttpException(
                404,
                'Cliente não encontrado.'
            );
        }

        $this->render(
            'clientes/show.php',
            'Cliente',
            self::ACTIVE_MENU,
            [
                'cliente' => $cliente,
                'pageStyles' => self::PAGE_STYLES,
            ]
        );
    }

    /**
     * Exibe o formulário de cadastro.
     */
    public function create(): void
    {
        $this->renderCreateForm(
            [],
            $this->emptyFormData()
        );
    }

    /**
     * Processa o cadastro de uma nova empresa cliente.
     */
    public function store(): void
    {
        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->cadastrar(
            $this->formInput($_POST),
            $context['usuario_id'],
            $context['ip'],
            $context['user_agent']
        );

        if (($result['success'] ?? false) !== true) {
            $this->renderCreateForm(
                $this->resultErrors($result),
                $this->resultFormData($result),
                422
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Cliente cadastrado com sucesso.'
        );

        $this->redirect('/clientes', 303);
    }

    /**
     * Exibe o formulário de edição cadastral.
     */
    public function edit(string $id): void
    {
        $clienteId = $this->validateId($id);
        $cliente = $this->service->buscar($clienteId);

        if ($cliente === null) {
            throw new HttpException(
                404,
                'Cliente não encontrado.'
            );
        }

        $this->renderEditForm(
            $clienteId,
            $this->stringValue($cliente, 'status'),
            [],
            $this->clienteToFormData($cliente)
        );
    }

    /**
     * Processa a edição dos dados cadastrais.
     *
     * O status da empresa não é alterado por esta operação.
     */
    public function update(string $id): void
    {
        $clienteId = $this->validateId($id);

        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->editar(
            $clienteId,
            $this->formInput($_POST),
            $context['usuario_id'],
            $context['ip'],
            $context['user_agent']
        );

        if (($result['not_found'] ?? false) === true) {
            throw new HttpException(
                404,
                'Cliente não encontrado.'
            );
        }

        if (($result['success'] ?? false) !== true) {
            $cliente = $this->service->buscar($clienteId);

            if ($cliente === null) {
                throw new HttpException(
                    404,
                    'Cliente não encontrado.'
                );
            }

            $this->renderEditForm(
                $clienteId,
                $this->stringValue($cliente, 'status'),
                $this->resultErrors($result),
                $this->resultFormData($result),
                422
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Cliente atualizado com sucesso.'
        );

        $this->redirect('/clientes', 303);
    }

    /**
     * Renderiza o formulário de cadastro.
     */
    private function renderCreateForm(
        array $errors,
        array $formData,
        int $statusCode = 200
    ): void {
        if ($statusCode !== 200) {
            http_response_code($statusCode);
        }

        $this->render(
            'clientes/create.php',
            'Novo cliente',
            self::ACTIVE_MENU,
            [
                'errors' => $errors,
                'formData' => $formData,
                'pageStyles' => self::PAGE_STYLES,
            ]
        );
    }

    /**
     * Renderiza o formulário de edição.
     */
    private function renderEditForm(
        int $clienteId,
        string $status,
        array $errors,
        array $formData,
        int $statusCode = 200
    ): void {
        if ($statusCode !== 200) {
            http_response_code($statusCode);
        }

        $this->render(
            'clientes/edit.php',
            'Editar cliente',
            self::ACTIVE_MENU,
            [
                'clienteId' => $clienteId,
                'status' => $status,
                'errors' => $errors,
                'formData' => $formData,
                'pageStyles' => self::PAGE_STYLES,
            ]
        );
    }

    /**
     * Mantém somente os campos permitidos para cadastro/edição.
     *
     * Campos extras como status, IDs internos ou outros valores
     * enviados manualmente são descartados.
     */
    private function formInput(array $input): array
    {
        $data = [];

        foreach (self::FORM_FIELDS as $field) {
            $value = $input[$field] ?? '';

            $data[$field] = is_string($value)
                ? $value
                : '';
        }

        return $data;
    }

    /**
     * Estrutura vazia padrão dos formulários.
     */
    private function emptyFormData(): array
    {
        return array_fill_keys(
            self::FORM_FIELDS,
            ''
        );
    }

    /**
     * Converte os dados persistidos do cliente
     * para o formato esperado pela view.
     */
    private function clienteToFormData(array $cliente): array
    {
        $data = [];

        foreach (self::FORM_FIELDS as $field) {
            $data[$field] = $this->stringValue(
                $cliente,
                $field
            );
        }

        return $data;
    }

    /**
     * Obtém os erros retornados pelo Service.
     */
    private function resultErrors(array $result): array
    {
        $errors = $result['errors'] ?? null;

        return is_array($errors)
            ? $errors
            : [];
    }

    /**
     * Obtém os dados normalizados retornados pelo Service.
     */
    private function resultFormData(array $result): array
    {
        $data = $result['data'] ?? null;

        if (!is_array($data)) {
            return $this->emptyFormData();
        }

        $formData = [];

        foreach (self::FORM_FIELDS as $field) {
            $formData[$field] = $this->stringValue(
                $data,
                $field
            );
        }

        return $formData;
    }

    /**
     * Recupera uma string de um array
     * sem confiar diretamente no tipo recebido.
     */
    private function stringValue(
        array $source,
        string $key
    ): string {
        $value = $source[$key] ?? null;

        return is_string($value)
            ? $value
            : '';
    }

    /**
     * Consome uma mensagem flash apenas uma vez.
     */
    private function consumeFlash(string $key): ?string
    {
        $value = Session::get($key);

        Session::remove($key);

        return is_string($value)
            ? $value
            : null;
    }

    /**
     * Valida o token CSRF das operações POST.
     */
    private function enforceCsrf(): void
    {
        $token = $_POST['_token'] ?? null;

        Csrf::enforce(
            is_string($token)
                ? $token
                : null
        );
    }

    /**
     * Retorna o contexto mínimo permitido para auditoria.
     */
    private function requestContext(): array
    {
        $auth = Session::get('auth');

        if (!is_array($auth)) {
            throw new RuntimeException(
                'Usuário autenticado não identificado.'
            );
        }

        $rawUserId = $auth['user_id'] ?? null;

        if (is_int($rawUserId)) {
            $usuarioId = $rawUserId;
        } elseif (
            is_string($rawUserId)
            && ctype_digit($rawUserId)
        ) {
            $usuarioId = (int) $rawUserId;
        } else {
            $usuarioId = 0;
        }

        if ($usuarioId <= 0) {
            throw new RuntimeException(
                'Usuário autenticado não identificado.'
            );
        }

        /*
         * Não confiamos em X-Forwarded-For sem
         * proxy confiável explicitamente configurado.
         */
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

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
            $userAgent = mb_substr(
                $userAgent,
                0,
                1000,
                'UTF-8'
            );
        } else {
            $userAgent = null;
        }

        return [
            'usuario_id' => $usuarioId,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ];
    }

    /**
     * Valida IDs recebidos pelas rotas.
     */
    private function validateId(string $id): int
    {
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

    /**
     * Renderiza uma view dentro do layout principal.
     */
    private function render(
        string $view,
        string $title,
        string $activeMenu,
        array $data = []
    ): void {
        $auth = Session::get('auth');

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
                static fn(mixed $role): bool =>
                is_string($role)
                    && $role !== ''
            )
        );

        $csrfToken = Csrf::token();

        $appUrl = rtrim(
            Env::required('APP_URL'),
            '/'
        );

        extract(
            $data,
            EXTR_SKIP
        );

        ob_start();

        require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . $view;

        $content = ob_get_clean();

        if (!is_string($content)) {
            $content = '';
        }

        require dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . 'layouts'
            . DIRECTORY_SEPARATOR
            . 'main.php';
    }

    /**
     * Redireciona utilizando somente
     * a APP_URL configurada no servidor.
     */
    private function redirect(
        string $path,
        int $statusCode = 302
    ): never {
        $appUrl = rtrim(
            Env::required('APP_URL'),
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
