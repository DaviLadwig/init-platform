<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Env;
use App\Core\Session;
use App\Repositories\FinanceiroRepository;
use App\Services\FinanceiroService;

final class FinanceiroController
{
    private const ACTIVE_MENU = 'financeiro';

    private const PAGE_STYLES = [
        'financeiro.css',
    ];

    private FinanceiroService $service;

    public function __construct()
    {
        $this->service = new FinanceiroService(
            new FinanceiroRepository()
        );
    }

    /**
     * Visão financeira consolidada.
     *
     * Esta página é deliberadamente somente leitura.
     * Nenhuma operação financeira é executada por GET.
     */
    public function index(): void
    {
        $financeiro = $this->service
            ->obterVisaoGeral(
                12
            );

        $this->render(
            'financeiro/index.php',
            'Financeiro',
            self::ACTIVE_MENU,
            [
                'financeiro' => $financeiro,
                'pageStyles' => self::PAGE_STYLES,
            ]
        );
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

        $userName =
            is_array($auth)
            && isset($auth['name'])
            && is_string($auth['name'])
                ? $auth['name']
                : 'Usuário';

        $roles =
            is_array($auth)
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

        /*
         * O layout principal usa o token também no logout.
         * Financeiro em si não possui POST nesta etapa.
         */
        $csrfToken = \App\Core\Csrf::token();

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
}
