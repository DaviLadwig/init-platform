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

    private const PAGE_SCRIPTS = [
        'clientes.js',
    ];

    /**
     * Somente campos que realmente podem vir do navegador.
     *
     * O slug NÃO faz parte do formulário.
     * Ele é um identificador técnico gerenciado pelo backend.
     */
    private const FORM_FIELDS = [
        'razao_social',
        'nome_fantasia',
        'cnpj',
        'email',
        'telefone',
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

        $input = $this->formInput($_POST);

        /*
         * O slug é criado exclusivamente no servidor.
         * Nunca aceitamos um slug enviado pelo navegador.
         */
        $input['slug'] = $this->generateTechnicalSlug(
            $input['nome_fantasia'] !== ''
                ? $input['nome_fantasia']
                : $input['razao_social'],
            $input['cnpj']
        );

        $result = $this->service->cadastrar(
            $input,
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
     * Exibe a ficha cadastral do cliente.
     *
     * A rota /clientes/{id} já existia e apontava para show().
     * Este método resolve o cliente exclusivamente no backend.
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

        /*
         * A listagem já contém os indicadores comerciais agregados.
         * Reaproveitamos somente a linha do cliente atual para
         * enriquecer a ficha, sem confiar em parâmetros do navegador.
         */
        $resumoComercial = [];

        foreach ($this->service->listar() as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemId = $item['id'] ?? null;

            if (
                (
                    is_int($itemId)
                    && $itemId === $clienteId
                )
                || (
                    is_string($itemId)
                    && ctype_digit($itemId)
                    && (int) $itemId === $clienteId
                )
            ) {
                $resumoComercial = $item;
                break;
            }
        }

        $this->render(
            'clientes/show.php',
            'Cliente',
            self::ACTIVE_MENU,
            [
                'cliente' => $cliente,
                'resumoComercial' => $resumoComercial,
                'pageStyles' => [
                    'clientes.css',
                    'clientes-show.css',
                ],
            ]
        );
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
     * O slug técnico também permanece estável.
     */
    public function update(string $id): void
    {
        $clienteId = $this->validateId($id);

        $this->enforceCsrf();

        /*
         * Buscamos primeiro o registro atual para preservar o slug.
         * Mudanças de razão social/nome fantasia não mudam o
         * identificador técnico já persistido.
         */
        $clienteAtual = $this->service->buscar($clienteId);

        if ($clienteAtual === null) {
            throw new HttpException(
                404,
                'Cliente não encontrado.'
            );
        }

        $context = $this->requestContext();

        $input = $this->formInput($_POST);

        $slugAtual = $this->stringValue(
            $clienteAtual,
            'slug'
        );

        $input['slug'] = $slugAtual !== ''
            ? $slugAtual
            : $this->generateTechnicalSlug(
                $input['nome_fantasia'] !== ''
                    ? $input['nome_fantasia']
                    : $input['razao_social'],
                $input['cnpj']
            );

        $result = $this->service->editar(
            $clienteId,
            $input,
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
                'pageScripts' => self::PAGE_SCRIPTS,
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
                'pageScripts' => self::PAGE_SCRIPTS,
            ]
        );
    }

    /**
     * Mantém somente os campos permitidos para cadastro/edição.
     *
     * Também normaliza o CNPJ no backend. A máscara visual é apenas
     * uma conveniência da interface; o servidor trabalha com 14 dígitos.
     */
    private function formInput(array $input): array
    {
        $data = [];

        foreach (self::FORM_FIELDS as $field) {
            $value = $input[$field] ?? '';

            $data[$field] = is_string($value)
                ? trim($value)
                : '';
        }

        $cnpj = preg_replace(
            '/\D+/',
            '',
            $data['cnpj']
        );

        $data['cnpj'] = is_string($cnpj)
            ? substr($cnpj, 0, 14)
            : '';

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
     * Converte os dados persistidos do cliente para o formato da view.
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
     * Obtém os erros retornados pelo Service de forma segura.
     */
    private function resultErrors(array $result): array
    {
        $errors = $result['errors'] ?? null;

        if (!is_array($errors)) {
            return [];
        }

        /*
         * Slug é interno. Mesmo que uma defesa do Service retorne
         * esse erro, não exibimos um campo técnico para o usuário.
         */
        unset($errors['slug']);

        return $errors;
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
     * Gera um slug técnico estável para novos clientes.
     *
     * Formato:
     *   nome-legivel-<hash-do-cnpj>
     *
     * O hash reduz colisões sem expor o CNPJ inteiro no identificador.
     * Depois de criado, o slug é preservado mesmo se o nome mudar.
     */
    private function generateTechnicalSlug(
        string $name,
        string $cnpj
    ): string {
        $base = mb_strtolower(
            trim($name),
            'UTF-8'
        );

        $base = strtr(
            $base,
            [
                'á' => 'a',
                'à' => 'a',
                'â' => 'a',
                'ã' => 'a',
                'ä' => 'a',
                'é' => 'e',
                'è' => 'e',
                'ê' => 'e',
                'ë' => 'e',
                'í' => 'i',
                'ì' => 'i',
                'î' => 'i',
                'ï' => 'i',
                'ó' => 'o',
                'ò' => 'o',
                'ô' => 'o',
                'õ' => 'o',
                'ö' => 'o',
                'ú' => 'u',
                'ù' => 'u',
                'û' => 'u',
                'ü' => 'u',
                'ç' => 'c',
                'ñ' => 'n',
            ]
        );

        $base = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $base
        );

        $base = is_string($base)
            ? trim($base, '-')
            : '';

        if ($base === '') {
            $base = 'empresa';
        }

        /*
         * Mantém o slug abaixo do limite atual de VARCHAR(150).
         */
        $base = rtrim(
            mb_substr(
                $base,
                0,
                130,
                'UTF-8'
            ),
            '-'
        );

        $cnpjDigits = preg_replace(
            '/\D+/',
            '',
            $cnpj
        );

        $cnpjDigits = is_string($cnpjDigits)
            ? $cnpjDigits
            : '';

        $suffix = substr(
            hash(
                'sha256',
                $cnpjDigits
            ),
            0,
            12
        );

        return $base
            . '-'
            . $suffix;
    }

    /**
     * Recupera uma string de um array sem confiar no tipo recebido.
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
         * Não confiamos em X-Forwarded-For sem uma camada de proxy
         * confiável explicitamente configurada.
         */
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if (
            !is_string($ip)
            || filter_var($ip, FILTER_VALIDATE_IP) === false
        ) {
            $ip = null;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

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
                static fn (mixed $role): bool =>
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
     * Redireciona utilizando somente a APP_URL configurada no servidor.
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
