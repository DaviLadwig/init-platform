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

final class ProdutoController
{
    private ProdutoService $service;

    public function __construct()
    {
        $this->service = new ProdutoService(
            new ProdutoRepository(),
            new AuditLogRepository()
        );
    }

    /**
     * Lista os produtos.
     */
    public function index(): void
    {
        $produtos = $this->service->listar();

        $success = Session::get('_flash_success');

        if (is_string($success)) {
            Session::remove('_flash_success');
        } else {
            $success = null;
        }

        $error = Session::get('_flash_error');

        if (is_string($error)) {
            Session::remove('_flash_error');
        } else {
            $error = null;
        }

        $this->render(
            'produtos/index.php',
            'Produtos',
            'produtos',
            [
                'produtos' => $produtos,
                'success' => $success,
                'error' => $error,
            ]
        );
    }

    /**
     * Exibe o formulário de cadastro.
     */
    public function create(): void
    {
        $this->render(
            'produtos/create.php',
            'Novo produto',
            'produtos',
            [
                'errors' => [],
                'formData' => [
                    'codigo' => '',
                    'nome' => '',
                    'slug' => '',
                    'descricao' => '',
                ],
            ]
        );
    }

    /**
     * Processa o cadastro.
     */
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
                'produtos',
                [
                    'errors' => is_array($result['errors'] ?? null)
                        ? $result['errors']
                        : [],
                    'formData' => is_array($result['data'] ?? null)
                        ? $result['data']
                        : [],
                ]
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Produto cadastrado com sucesso.'
        );

        $this->redirect(
            '/produtos',
            303
        );
    }

    /**
     * Exibe o formulário de edição.
     */
    public function edit(string $id): void
    {
        $produtoId = $this->validateId($id);

        $produto = $this->service->buscar(
            $produtoId
        );

        if ($produto === null) {
            throw new HttpException(
                404,
                'Produto não encontrado.'
            );
        }

        $this->render(
            'produtos/edit.php',
            'Editar produto',
            'produtos',
            [
                'produtoId' => $produtoId,
                'errors' => [],
                'formData' => [
                    'codigo' => isset($produto['codigo'])
                        && is_string($produto['codigo'])
                        ? $produto['codigo']
                        : '',
                    'nome' => isset($produto['nome'])
                        && is_string($produto['nome'])
                        ? $produto['nome']
                        : '',
                    'slug' => isset($produto['slug'])
                        && is_string($produto['slug'])
                        ? $produto['slug']
                        : '',
                    'descricao' => isset($produto['descricao'])
                        && is_string($produto['descricao'])
                        ? $produto['descricao']
                        : '',
                ],
            ]
        );
    }

    /**
     * Processa a edição.
     */
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
                'produtos',
                [
                    'produtoId' => $produtoId,
                    'errors' => is_array($result['errors'] ?? null)
                        ? $result['errors']
                        : [],
                    'formData' => is_array($result['data'] ?? null)
                        ? $result['data']
                        : [],
                ]
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Produto atualizado com sucesso.'
        );

        $this->redirect(
            '/produtos',
            303
        );
    }

    /**
     * Ativa um produto.
     */
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

    /**
     * Desativa um produto.
     */
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

    /**
     * Trata o resultado das operações de status.
     */
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

        $this->redirect(
            '/produtos',
            303
        );
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
     * Retorna o contexto seguro necessário para auditoria.
     *
     * @return array{
     *     usuario_id: int,
     *     ip: ?string,
     *     user_agent: ?string
     * }
     */
    private function requestContext(): array
    {
        $auth = Session::get('auth');

        $usuarioId = is_array($auth)
            && isset($auth['user_id'])
            && is_int($auth['user_id'])
            ? $auth['user_id']
            : 0;

        if ($usuarioId <= 0) {
            throw new \RuntimeException(
                'Usuário autenticado não identificado.'
            );
        }

        /*
         * Por enquanto usamos somente REMOTE_ADDR.
         * Não confiamos em X-Forwarded-For sem proxy confiável configurado.
         */
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if (!is_string($ip)) {
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
     * Valida o ID recebido pela rota.
     */
    private function validateId(string $id): int
    {
        if (
            !ctype_digit($id)
            || (int) $id <= 0
        ) {
            throw new HttpException(
                404,
                'Produto não encontrado.'
            );
        }

        return (int) $id;
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
     * Redirecionamento interno controlado.
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
