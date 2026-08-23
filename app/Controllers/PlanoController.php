<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;
use App\Core\HttpException;
use App\Repositories\AuditLogRepository;
use App\Repositories\PlanoRepository;
use App\Services\PlanoService;
use RuntimeException;


final class PlanoController
{
    private PlanoService $service;

    public function __construct()
    {
        $this->service = new PlanoService(
            new PlanoRepository(),
            new AuditLogRepository()
        );
    }

    /**
     * Lista o catálogo de produtos e seus planos.
     */
    public function index(): void
    {
        $catalogo = $this->service->listar();

        $success = $this->consumeFlash(
            '_flash_success'
        );

        $error = $this->consumeFlash(
            '_flash_error'
        );

        $this->render(
            'planos/index.php',
            'Planos',
            'planos',
            [
                'produtos' => $catalogo,
                'success' => $success,
                'error' => $error,

                'pageStyles' => [
                    'planos.css',
                ],
            ]
        );
    }

    /**
     * Exibe formulário de cadastro.
     *
     * Permite:
     * /planos/novo?produto=1
     *
     * para pré-selecionar um produto ativo.
     */
    public function create(): void
    {
        $produtos = $this->service
            ->produtosAtivos();

        $produtoSelecionado =
            $this->resolveSelectedProduct(
                $produtos
            );

        $this->render(
            'planos/create.php',
            'Novo plano',
            'planos',
            [
                'produtos' => $produtos,

                'errors' => [],

                'formData' => [
                    'produto_id' =>
                    $produtoSelecionado,

                    'codigo' => '',
                    'nome' => '',
                    'descricao' => '',
                    'valor' => '',
                    'periodicidade' => 'MENSAL',
                ],

                'pageStyles' => [
                    'planos.css',
                ],
            ]
        );
    }

    /**
     * Processa o cadastro de um plano.
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
                'planos/create.php',
                'Novo plano',
                'planos',
                [
                    /*
                     * Recarregamos do banco.
                     *
                     * Nunca confiamos nos produtos
                     * recebidos pelo formulário.
                     */
                    'produtos' =>
                    $this->service
                        ->produtosAtivos(),

                    'errors' =>
                    is_array(
                        $result['errors']
                            ?? null
                    )
                        ? $result['errors']
                        : [],

                    'formData' =>
                    is_array(
                        $result['data']
                            ?? null
                    )
                        ? $result['data']
                        : [],

                    'pageStyles' => [
                        'planos.css',
                    ],
                ]
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Plano cadastrado com sucesso.'
        );

        /*
         * POST → 303 → GET
         *
         * Evita reenvio do formulário
         * ao atualizar a página.
         */
        $this->redirect(
            '/planos',
            303
        );
    }

    /**
     * Exibe formulário de edição.
     */
    public function edit(
        string $id
    ): void {
        $planoId = $this->validateId(
            $id
        );

        $plano = $this->service->buscar(
            $planoId
        );

        if ($plano === null) {
            throw new HttpException(
                404,
                'Plano não encontrado.'
            );
        }

        $this->render(
            'planos/edit.php',
            'Editar plano',
            'planos',
            [
                'planoId' => $planoId,

                'produto' => [
                    'id' =>
                    (int) $plano['produto_id'],

                    'nome' =>
                    is_string(
                        $plano['produto_nome']
                            ?? null
                    )
                        ? $plano['produto_nome']
                        : '',

                    'codigo' =>
                    is_string(
                        $plano['produto_codigo']
                            ?? null
                    )
                        ? $plano['produto_codigo']
                        : '',
                ],

                'errors' => [],

                'formData' => [
                    'codigo' =>
                    (string) $plano['codigo'],

                    'nome' =>
                    (string) $plano['nome'],

                    'descricao' =>
                    is_string(
                        $plano['descricao']
                            ?? null
                    )
                        ? $plano['descricao']
                        : '',

                    'valor' =>
                    (string) $plano['valor'],

                    'periodicidade' =>
                    (string) $plano['periodicidade'],
                ],

                'pageStyles' => [
                    'planos.css',
                ],
            ]
        );
    }

    /**
     * Processa edição do plano.
     */
    public function update(
        string $id
    ): void {
        $planoId = $this->validateId(
            $id
        );

        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->editar(
            $planoId,
            $_POST,
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
                'Plano não encontrado.'
            );
        }

        if (
            ($result['success'] ?? false)
            !== true
        ) {
            $plano = $this->service->buscar(
                $planoId
            );

            if ($plano === null) {
                throw new HttpException(
                    404,
                    'Plano não encontrado.'
                );
            }

            http_response_code(422);

            $this->render(
                'planos/edit.php',
                'Editar plano',
                'planos',
                [
                    'planoId' =>
                    $planoId,

                    'produto' => [
                        'id' =>
                        (int) $plano['produto_id'],

                        'nome' =>
                        is_string(
                            $plano['produto_nome'] ?? null
                        )
                            ? $plano['produto_nome']
                            : '',

                        'codigo' =>
                        is_string(
                            $plano['produto_codigo'] ?? null
                        )
                            ? $plano['produto_codigo']
                            : '',
                    ],

                    'errors' =>
                    is_array(
                        $result['errors']
                            ?? null
                    )
                        ? $result['errors']
                        : [],

                    'formData' =>
                    is_array(
                        $result['data']
                            ?? null
                    )
                        ? $result['data']
                        : [],

                    'pageStyles' => [
                        'planos.css',
                    ],
                ]
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Plano atualizado com sucesso.'
        );

        $this->redirect(
            '/planos',
            303
        );
    }

    /**
     * Ativa o plano.
     */
    public function activate(
        string $id
    ): void {
        $planoId = $this->validateId(
            $id
        );

        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->alterarStatus(
            $planoId,
            true,
            $context['usuario_id'],
            $context['ip'],
            $context['user_agent']
        );

        $this->handleStatusResult(
            $result
        );
    }


    /**
     * Desativa o plano.
     */
    public function deactivate(
        string $id
    ): void {
        $planoId = $this->validateId(
            $id
        );

        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->alterarStatus(
            $planoId,
            false,
            $context['usuario_id'],
            $context['ip'],
            $context['user_agent']
        );

        $this->handleStatusResult(
            $result
        );
    }

    /**
     * Trata o resultado das operações de ativação/desativação.
     */
    private function handleStatusResult(
        array $result
    ): never {
        if (
            ($result['not_found'] ?? false)
            === true
        ) {
            throw new HttpException(
                404,
                'Plano não encontrado.'
            );
        }

        $message = isset($result['message'])
            && is_string($result['message'])
            ? $result['message']
            : null;

        if (
            ($result['success'] ?? false)
            === true
        ) {
            Session::set(
                '_flash_success',
                $message
                    ?? 'Operação realizada com sucesso.'
            );
        } else {
            Session::set(
                '_flash_error',
                $message
                    ?? 'Não foi possível realizar a operação.'
            );
        }

        $this->redirect(
            '/planos',
            303
        );
    }

    /**
     * Retorna e remove uma mensagem flash.
     */
    private function consumeFlash(
        string $key
    ): ?string {
        $value = Session::get(
            $key
        );

        if (!is_string($value)) {
            return null;
        }

        Session::remove(
            $key
        );

        return $value;
    }

    /**
     * Resolve o produto passado via query string.
     *
     * A pré-seleção só acontece se o produto
     * estiver na lista de produtos ativos.
     */
    private function resolveSelectedProduct(
        array $produtos
    ): string {
        $produtoQuery = $_GET['produto']
            ?? null;

        if (
            !is_string($produtoQuery)
            || !ctype_digit($produtoQuery)
            || (int) $produtoQuery <= 0
        ) {
            return '';
        }

        $produtoId = (int) $produtoQuery;

        foreach ($produtos as $produto) {
            $id = isset($produto['id'])
                ? (int) $produto['id']
                : 0;

            if ($id === $produtoId) {
                return (string) $produtoId;
            }
        }

        return '';
    }

    /**
     * Valida o token CSRF das operações POST.
     */
    private function enforceCsrf(): void
    {
        $token = $_POST['_token']
            ?? null;

        Csrf::enforce(
            is_string($token)
                ? $token
                : null
        );
    }

    /**
     * Retorna somente informações seguras
     * necessárias para auditoria.
     *
     * @return array{
     *     usuario_id: int,
     *     ip: ?string,
     *     user_agent: ?string
     * }
     */
    private function requestContext(): array
    {
        $auth = Session::get(
            'auth'
        );

        $usuarioId = is_array($auth)
            && isset($auth['user_id'])
            && is_int($auth['user_id'])
            ? $auth['user_id']
            : 0;

        if ($usuarioId <= 0) {
            throw new RuntimeException(
                'Usuário autenticado não identificado.'
            );
        }

        /*
         * Enquanto não houver proxy reverso
         * confiável configurado, usamos somente
         * REMOTE_ADDR.
         *
         * Não confiamos diretamente em
         * X-Forwarded-For.
         */
        $ip = $_SERVER['REMOTE_ADDR']
            ?? null;

        if (!is_string($ip)) {
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
     * Valida o ID recebido pela rota.
     */
    private function validateId(
        string $id
    ): int {
        if (
            !ctype_digit($id)
            || (int) $id <= 0
        ) {
            throw new HttpException(
                404,
                'Plano não encontrado.'
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
                static fn(mixed $role): bool =>
                is_string($role)
                    && $role !== ''
            )
        );

        $csrfToken = Csrf::token();

        $appUrl = rtrim(
            Env::required(
                'APP_URL'
            ),
            '/'
        );

        /*
         * Disponibiliza somente os dados
         * explicitamente enviados pelo controller.
         *
         * EXTR_SKIP evita sobrescrever variáveis
         * internas como $title e $appUrl.
         */
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
