<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Repositories\PlanoLimiteRepository;
use App\Repositories\PlanoRepository;
use App\Services\PlanoLimiteService;
use RuntimeException;

final class PlanoLimiteController
{
    private PlanoLimiteService $service;

    public function __construct()
    {
        $this->service = new PlanoLimiteService(
            new PlanoRepository(),
            new PlanoLimiteRepository(),
            new AuditLogRepository()
        );
    }

    /**
     * Lista os limites do plano.
     */
    public function index(
        string $id
    ): void {
        $planoId = $this->validateId(
            $id
        );

        $result = $this->service->visualizar(
            $planoId
        );

        if ($result === null) {
            throw new HttpException(
                404,
                'Plano não encontrado.'
            );
        }

        $success = $this->consumeFlash(
            '_flash_success'
        );

        $error = $this->consumeFlash(
            '_flash_error'
        );

        $this->render(
            'planos/limites/index.php',
            'Limites do plano',
            'planos',
            [
                'plano' => $result['plano'],
                'limites' => $result['limites'],
                'success' => $success,
                'error' => $error,

                'pageStyles' => [
                    'plano-limites.css',
                ],
            ]
        );
    }

    /**
     * Exibe formulário para novo limite.
     */
    public function create(
        string $id
    ): void {
        $planoId = $this->validateId(
            $id
        );

        $plano = $this->service->buscarPlano(
            $planoId
        );

        if ($plano === null) {
            throw new HttpException(
                404,
                'Plano não encontrado.'
            );
        }

        $this->render(
            'planos/limites/create.php',
            'Novo limite',
            'planos',
            [
                'plano' => $plano,

                'errors' => [],

                'formData' => [
                    'chave' => '',
                    'valor' => '',
                    'unidade' => '',
                ],

                'pageStyles' => [
                    'plano-limites.css',
                ],
            ]
        );
    }

    /**
     * Processa o cadastro.
     */
    public function store(
        string $id
    ): void {
        $planoId = $this->validateId(
            $id
        );

        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->cadastrar(
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
            $plano = $this->service->buscarPlano(
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
                'planos/limites/create.php',
                'Novo limite',
                'planos',
                [
                    'plano' => $plano,

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
                        'plano-limites.css',
                    ],
                ]
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Limite cadastrado com sucesso.'
        );

        $this->redirect(
            '/planos/'
                . $planoId
                . '/limites',
            303
        );
    }

    /**
     * Exibe o formulário de edição.
     */
    public function edit(
        string $id,
        string $limiteId
    ): void {
        $planoId = $this->validateId(
            $id
        );

        $limiteIdInt = $this->validateId(
            $limiteId
        );

        $plano = $this->service->buscarPlano(
            $planoId
        );

        if ($plano === null) {
            throw new HttpException(
                404,
                'Plano não encontrado.'
            );
        }

        $limite = $this->service->buscarLimite(
            $planoId,
            $limiteIdInt
        );

        /*
     * Não revelamos se o limite existe em outro
     * plano. Para este contexto, simplesmente
     * não existe.
     */
        if ($limite === null) {
            throw new HttpException(
                404,
                'Limite não encontrado.'
            );
        }

        $this->render(
            'planos/limites/edit.php',
            'Editar limite',
            'planos',
            [
                'plano' => $plano,
                'limiteId' => $limiteIdInt,

                'errors' => [],

                'formData' => [
                    'chave' =>
                    is_string(
                        $limite['chave']
                            ?? null
                    )
                        ? $limite['chave']
                        : '',

                    'valor' =>
                    is_numeric(
                        $limite['valor']
                            ?? null
                    )
                        ? $this->formatNumericValue(
                            (string) $limite['valor']
                        )
                        : '',

                    'unidade' =>
                    is_string(
                        $limite['unidade']
                            ?? null
                    )
                        ? $limite['unidade']
                        : '',
                ],

                'pageStyles' => [
                    'plano-limites.css',
                ],
            ]
        );
    }

    /**
     * Processa a edição.
     */
    public function update(
        string $id,
        string $limiteId
    ): void {
        $planoId = $this->validateId(
            $id
        );

        $limiteIdInt = $this->validateId(
            $limiteId
        );

        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->editar(
            $planoId,
            $limiteIdInt,
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
                'Limite não encontrado.'
            );
        }

        if (
            ($result['success'] ?? false)
            !== true
        ) {
            $plano = $this->service->buscarPlano(
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
                'planos/limites/edit.php',
                'Editar limite',
                'planos',
                [
                    'plano' => $plano,

                    'limiteId' =>
                    $limiteIdInt,

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
                        'plano-limites.css',
                    ],
                ]
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Limite atualizado com sucesso.'
        );

        $this->redirect(
            '/planos/'
                . $planoId
                . '/limites',
            303
        );
    }

    /**
     * Exibe a confirmação de remoção.
     *
     * GET apenas mostra a tela.
     * Nenhum dado é alterado aqui.
     */
    public function confirmDelete(
        string $id,
        string $limiteId
    ): void {
        $planoId = $this->validateId(
            $id
        );

        $limiteIdInt = $this->validateId(
            $limiteId
        );

        $plano = $this->service->buscarPlano(
            $planoId
        );

        if ($plano === null) {
            throw new HttpException(
                404,
                'Plano não encontrado.'
            );
        }

        $limite = $this->service->buscarLimite(
            $planoId,
            $limiteIdInt
        );

        /*
     * Não revelamos que o limite possa existir
     * vinculado a outro plano.
     */
        if ($limite === null) {
            throw new HttpException(
                404,
                'Limite não encontrado.'
            );
        }

        $this->render(
            'planos/limites/delete.php',
            'Remover limite',
            'planos',
            [
                'plano' => $plano,
                'limite' => $limite,

                'pageStyles' => [
                    'plano-limites.css',
                ],
            ]
        );
    }

    /**
     * Remove definitivamente o limite.
     */
    public function destroy(
        string $id,
        string $limiteId
    ): void {
        $planoId = $this->validateId(
            $id
        );

        $limiteIdInt = $this->validateId(
            $limiteId
        );

        $this->enforceCsrf();

        $context = $this->requestContext();

        $result = $this->service->remover(
            $planoId,
            $limiteIdInt,
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
                'Limite não encontrado.'
            );
        }

        if (
            ($result['success'] ?? false)
            !== true
        ) {
            Session::set(
                '_flash_error',
                'Não foi possível remover o limite.'
            );

            $this->redirect(
                '/planos/'
                    . $planoId
                    . '/limites',
                303
            );
        }

        Session::set(
            '_flash_success',
            'Limite removido com sucesso.'
        );

        $this->redirect(
            '/planos/'
                . $planoId
                . '/limites',
            303
        );
    }

    /*FUNÇÕES PRIVADAS*/

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

    /**
     * Remove zeros decimais apenas para apresentação.
     *
     * 10.0000 -> 10
     * 10.5000 -> 10.5
     */
    private function formatNumericValue(
        string $value
    ): string {
        if (str_contains($value, '.')) {
            $value = rtrim(
                rtrim(
                    $value,
                    '0'
                ),
                '.'
            );
        }

        return $value !== ''
            ? $value
            : '0';
    }
}
