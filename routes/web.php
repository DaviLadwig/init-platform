<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\AssinaturaController;
use App\Controllers\ClienteController;
use App\Controllers\DocumentoContratualController;
use App\Controllers\EmpresaResponsavelController;
use App\Controllers\FinanceiroController;
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
| Financeiro
|--------------------------------------------------------------------------
|
| Primeira versão somente leitura.
| Nenhuma alteração financeira é executada por GET.
|
*/

$router->get(
    '/financeiro',
    [
        FinanceiroController::class,
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
| Assinaturas
|--------------------------------------------------------------------------
*/

$router->get(
    '/assinaturas',
    [
        AssinaturaController::class,
        'index',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);



$router->post(
    '/assinaturas/processar-inadimplencia',
    [
        AssinaturaController::class,
        'processDelinquency',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);

$router->get(
    '/assinaturas/vencimentos',
    [
        AssinaturaController::class,
        'vencimentos',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);

$router->get(
    '/assinaturas/novo',
    [
        AssinaturaController::class,
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
    '/assinaturas',
    [
        AssinaturaController::class,
        'store',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);




$router->post(
    '/assinaturas/{id}/lembrete-whatsapp',
    [
        AssinaturaController::class,
        'whatsappReminder',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);

$router->get(
    '/assinaturas/{id}/pagamento',
    [
        AssinaturaController::class,
        'payment',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);

$router->post(
    '/assinaturas/{id}/pagamento',
    [
        AssinaturaController::class,
        'storePayment',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->post(
    '/assinaturas/{id}/cancelar',
    [
        AssinaturaController::class,
        'cancel',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);

$router->post(
    '/assinaturas/{id}/ativar',
    [
        AssinaturaController::class,
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
    '/assinaturas/{id}/documentos',
    [
        DocumentoContratualController::class,
        'storeOriginal',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);

$router->post(
    '/assinaturas/{id}/documentos/{documentoId}/enviar-autentique',
    [
        DocumentoContratualController::class,
        'markSentToAutentique',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);

$router->post(
    '/assinaturas/{id}/documentos/{documentoId}/assinado',
    [
        DocumentoContratualController::class,
        'storeSigned',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);

$router->post(
    '/assinaturas/{id}/documentos/{documentoId}/cancelar',
    [
        DocumentoContratualController::class,
        'cancelAttempt',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);

$router->get(
    '/assinaturas/{id}/documentos/{documentoId}/original',
    [
        DocumentoContratualController::class,
        'downloadOriginal',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);

$router->get(
    '/assinaturas/{id}/documentos/{documentoId}/assinado',
    [
        DocumentoContratualController::class,
        'downloadSigned',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);


$router->get(
    '/assinaturas/{id}',
    [
        AssinaturaController::class,
        'show',
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
|--------------------------------------------------------------------------
| Responsáveis dos clientes
|--------------------------------------------------------------------------
|
| As rotas mais específicas ficam antes das rotas genéricas
| /clientes/{id}. Isso evita colisões no roteamento dinâmico.
|
*/

$router->get(
    '/clientes/{id}/responsaveis',
    [
        EmpresaResponsavelController::class,
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
    '/clientes/{id}/responsaveis/novo',
    [
        EmpresaResponsavelController::class,
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
    '/clientes/{id}/responsaveis',
    [
        EmpresaResponsavelController::class,
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
    '/clientes/{id}/responsaveis/{responsavelId}/editar',
    [
        EmpresaResponsavelController::class,
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
    '/clientes/{id}/responsaveis/{responsavelId}/ativar',
    [
        EmpresaResponsavelController::class,
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
    '/clientes/{id}/responsaveis/{responsavelId}/desativar',
    [
        EmpresaResponsavelController::class,
        'deactivate',
    ],
    [
        AuthMiddleware::class,

        new RoleMiddleware([
            'SUPER_ADMIN',
        ]),
    ]
);

$router->post(
    '/clientes/{id}/responsaveis/{responsavelId}',
    [
        EmpresaResponsavelController::class,
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
|--------------------------------------------------------------------------
| Dados cadastrais do cliente
|--------------------------------------------------------------------------
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
