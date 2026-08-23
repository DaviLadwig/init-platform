<?php

declare(strict_types=1);

use App\Core\Env;

/*
 * Em desenvolvimento local usamos HTTP.
 * Em produção, com HTTPS, o cookie Secure será obrigatório.
 */

$isProduction = Env::get('APP_ENV', 'local') === 'production';

return [
    'name' => 'INIT_SAAS_SESSION',

    /*
     * Tempo máximo de inatividade da sessão.
     * 30 minutos inicialmente.
     */
    'lifetime' => 1800,

    /*
     * Cookie disponível para toda a aplicação.
     */
    'path' => '/',

    /*
     * Deixe null para usar o domínio atual.
     */
    'domain' => null,

    /*
     * Em produção será enviado somente via HTTPS.
     */
    'secure' => $isProduction,

    /*
     * Impede acesso ao cookie via JavaScript.
     */
    'httponly' => true,

    /*
     * Boa proteção padrão contra envio cross-site.
     */
    'samesite' => 'Lax',
];
