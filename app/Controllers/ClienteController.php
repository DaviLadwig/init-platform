<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;
use App\Repositories\ClienteRepository;
use App\Services\ClienteService;

final class ClienteController
{
    private ClienteService $service;

    public function __construct()
    {
        $this->service = new ClienteService(
            new ClienteRepository()
        );
    }

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
            $situacao = $cliente['situacao_comercial'] ?? '';

            if ($situacao === 'ATIVO') {
                $resumo['ativos']++;
            }

            if ($situacao === 'ATENCAO') {
                $resumo['atencao']++;
            }

            if ($situacao === 'SUSPENSO') {
                $resumo['suspensos']++;
            }

            if ($situacao === 'SEM_ASSINATURA') {
                $resumo['sem_assinatura']++;
            }
        }

        $this->render(
            'clientes/index.php',
            'Clientes',
            'clientes',
            [
                'clientes' => $clientes,
                'resumo' => $resumo,
            ]
        );
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
