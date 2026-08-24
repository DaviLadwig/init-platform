<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ClienteController;
use App\Controllers\HomeController;
use App\Controllers\PlanoController;
use App\Controllers\PlanoLimiteController;
use App\Controllers\ProdutoController;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;


/*
|--------------------------------------------------------------------------
| Rotas públicas
|--------------------------------------------------------------------------
*/

$router->get(
    '/login',
    [
        AuthController::class,
        'showLogin',
    ]
);

$router->post(
    '/login',
    [
        AuthController::class,
        'login',
    ]
);


/*
|--------------------------------------------------------------------------
| Rotas autenticadas
|--------------------------------------------------------------------------
*/

$router->post(
    '/logout',
    [
        AuthController::class,
        'logout',
    ],
    [
        AuthMiddleware::class,
    ]
);


/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

$router->get(
    '/',
    [
        HomeController::class,
        'index',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


/*
|--------------------------------------------------------------------------
| Produtos
|--------------------------------------------------------------------------
*/

$router->get(
    '/produtos',
    [
        ProdutoController::class,
        'index',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->get(
    '/produtos/novo',
    [
        ProdutoController::class,
        'create',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->post(
    '/produtos',
    [
        ProdutoController::class,
        'store',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->get(
    '/produtos/{id}/editar',
    [
        ProdutoController::class,
        'edit',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->post(
    '/produtos/{id}',
    [
        ProdutoController::class,
        'update',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->post(
    '/produtos/{id}/ativar',
    [
        ProdutoController::class,
        'activate',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->post(
    '/produtos/{id}/desativar',
    [
        ProdutoController::class,
        'deactivate',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


/*
|--------------------------------------------------------------------------
| Planos
|--------------------------------------------------------------------------
*/

$router->get(
    '/planos',
    [
        PlanoController::class,
        'index',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->get(
    '/planos/novo',
    [
        PlanoController::class,
        'create',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->post(
    '/planos',
    [
        PlanoController::class,
        'store',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->get(
    '/planos/{id}/editar',
    [
        PlanoController::class,
        'edit',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->post(
    '/planos/{id}',
    [
        PlanoController::class,
        'update',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->post(
    '/planos/{id}/ativar',
    [
        PlanoController::class,
        'activate',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->post(
    '/planos/{id}/desativar',
    [
        PlanoController::class,
        'deactivate',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


/*
|--------------------------------------------------------------------------
| Limites dos planos
|--------------------------------------------------------------------------
*/

$router->get(
    '/planos/{id}/limites',
    [
        PlanoLimiteController::class,
        'index',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->get(
    '/planos/{id}/limites/novo',
    [
        PlanoLimiteController::class,
        'create',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->post(
    '/planos/{id}/limites',
    [
        PlanoLimiteController::class,
        'store',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->get(
    '/planos/{id}/limites/{limiteId}/editar',
    [
        PlanoLimiteController::class,
        'edit',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->post(
    '/planos/{id}/limites/{limiteId}',
    [
        PlanoLimiteController::class,
        'update',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->get(
    '/planos/{id}/limites/{limiteId}/remover',
    [
        PlanoLimiteController::class,
        'confirmDelete',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->post(
    '/planos/{id}/limites/{limiteId}/remover',
    [
        PlanoLimiteController::class,
        'destroy',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


/*
|--------------------------------------------------------------------------
| Clientes
|--------------------------------------------------------------------------
*/

/*
 * Lista de clientes.
 */
$router->get(
    '/clientes',
    [
        ClienteController::class,
        'index',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


/*
 * Formulário de cadastro.
 */
$router->get(
    '/clientes/novo',
    [
        ClienteController::class,
        'create',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


/*
 * Cadastro de cliente.
 */
$router->post(
    '/clientes',
    [
        ClienteController::class,
        'store',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


/*
 * Formulário de edição.
 */
$router->get(
    '/clientes/{id}/editar',
    [
        ClienteController::class,
        'edit',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


/*
 * Atualização cadastral.
 */
$router->post(
    '/clientes/{id}',
    [
        ClienteController::class,
        'update',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);

/*
 * Ficha cadastral do cliente.
 */
$router->get(
    '/clientes/{id}',
    [
        ClienteController::class,
        'show',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);
