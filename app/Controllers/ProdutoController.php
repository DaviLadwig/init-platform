<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Repositories\ProdutoRepository;
use App\Services\ProdutoService;
use RuntimeException;

final class ProdutoController
{
    private const ACTIVE_MENU = 'produtos';

    private const PAGE_STYLES = [
        'produtos.css',
    ];

    private ProdutoService $service;

    public function __construct()
    {
        $this->service = new ProdutoService(
            new ProdutoRepository(),
            new AuditLogRepository()
        );
    }

    public function index(): void
    {
        $produtos = $this->service->listar();

        $this->render(
            'produtos/index.php',
            'Produtos',
            self::ACTIVE_MENU,
            [
                'produtos' => $produtos,
                'success' => $this->consumeFlash('_flash_success'),
                'error' => $this->consumeFlash('_flash_error'),
                'pageStyles' => self::PAGE_STYLES,
            ]
        );
    }

    public function create(): void
    {
        $this->render(
            'produtos/create.php',
            'Novo produto',
            self::ACTIVE_MENU,
            [
                'errors' => [],
                'formData' => [
                    'codigo' => '',
                    'nome' => '',
                    'slug' => '',
                    'descricao' => '',
                ],
                'pageStyles' => self::PAGE_STYLES,
            ]
        );
    }

    public function store(): void
    {
        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->cadastrar(
            $_POST,
            $context['usuario_id'],
            $context['ip'],
            $context['user_agent']
        );

        if (($result['success'] ?? false) !== true) {
            http_response_code(422);

            $this->render(
                'produtos/create.php',
                'Novo produto',
                self::ACTIVE_MENU,
                [
                    'errors' => is_array($result['errors'] ?? null)
                        ? $result['errors']
                        : [],
                    'formData' => is_array($result['data'] ?? null)
                        ? $result['data']
                        : [],
                    'pageStyles' => self::PAGE_STYLES,
                ]
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Produto cadastrado com sucesso.'
        );

        $this->redirect('/produtos', 303);
    }

    public function edit(string $id): void
    {
        $produtoId = $this->validateId($id);
        $produto = $this->service->buscar($produtoId);

        if ($produto === null) {
            throw new HttpException(
                404,
                'Produto não encontrado.'
            );
        }

        $this->render(
            'produtos/edit.php',
            'Editar produto',
            self::ACTIVE_MENU,
            [
                'produtoId' => $produtoId,
                'errors' => [],
                'formData' => [
                    'codigo' => $this->stringValue($produto, 'codigo'),
                    'nome' => $this->stringValue($produto, 'nome'),
                    'slug' => $this->stringValue($produto, 'slug'),
                    'descricao' => $this->stringValue($produto, 'descricao'),
                ],
                'pageStyles' => self::PAGE_STYLES,
            ]
        );
    }

    public function update(string $id): void
    {
        $produtoId = $this->validateId($id);
        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->editar(
            $produtoId,
            $_POST,
            $context['usuario_id'],
            $context['ip'],
            $context['user_agent']
        );

        if (($result['not_found'] ?? false) === true) {
            throw new HttpException(
                404,
                'Produto não encontrado.'
            );
        }

        if (($result['success'] ?? false) !== true) {
            http_response_code(422);

            $this->render(
                'produtos/edit.php',
                'Editar produto',
                self::ACTIVE_MENU,
                [
                    'produtoId' => $produtoId,
                    'errors' => is_array($result['errors'] ?? null)
                        ? $result['errors']
                        : [],
                    'formData' => is_array($result['data'] ?? null)
                        ? $result['data']
                        : [],
                    'pageStyles' => self::PAGE_STYLES,
                ]
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Produto atualizado com sucesso.'
        );

        $this->redirect('/produtos', 303);
    }

    public function activate(string $id): void
    {
        $produtoId = $this->validateId($id);
        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->alterarStatus(
            $produtoId,
            true,
            $context['usuario_id'],
            $context['ip'],
            $context['user_agent']
        );

        $this->handleStatusResult($result);
    }

    public function deactivate(string $id): void
    {
        $produtoId = $this->validateId($id);
        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->alterarStatus(
            $produtoId,
            false,
            $context['usuario_id'],
            $context['ip'],
            $context['user_agent']
        );

        $this->handleStatusResult($result);
    }

    private function handleStatusResult(array $result): never
    {
        if (($result['not_found'] ?? false) === true) {
            throw new HttpException(
                404,
                'Produto não encontrado.'
            );
        }

        $message = isset($result['message'])
            && is_string($result['message'])
            ? $result['message']
            : null;

        if (($result['success'] ?? false) === true) {
            Session::set(
                '_flash_success',
                $message ?? 'Operação realizada com sucesso.'
            );
        } else {
            Session::set(
                '_flash_error',
                $message ?? 'Não foi possível realizar a operação.'
            );
        }

        $this->redirect('/produtos', 303);
    }

    private function consumeFlash(string $key): ?string
    {
        $value = Session::get($key);
        Session::remove($key);

        return is_string($value)
            ? $value
            : null;
    }

    private function stringValue(array $source, string $key): string
    {
        $value = $source[$key] ?? null;

        return is_string($value)
            ? $value
            : '';
    }

    private function enforceCsrf(): void
    {
        $token = $_POST['_token'] ?? null;

        Csrf::enforce(
            is_string($token)
                ? $token
                : null
        );
    }

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
        } elseif (is_string($rawUserId) && ctype_digit($rawUserId)) {
            $usuarioId = (int) $rawUserId;
        } else {
            $usuarioId = 0;
        }

        if ($usuarioId <= 0) {
            throw new RuntimeException(
                'Usuário autenticado não identificado.'
            );
        }

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
                'Produto não encontrado.'
            );
        }

        return $validated;
    }

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

        extract($data, EXTR_SKIP);

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

    private function redirect(
        string $path,
        int $statusCode = 302
    ): never {
        $appUrl = rtrim(
            Env::required('APP_URL'),
            '/'
        );

        $path = '/' . ltrim($path, '/');

        header(
            'Location: ' . $appUrl . $path,
            true,
            $statusCode
        );

        exit;
    }
}
