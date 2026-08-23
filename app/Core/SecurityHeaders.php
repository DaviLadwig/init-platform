<?php

declare(strict_types=1);

namespace App\Core;

final class SecurityHeaders
{
    private function __construct() {}

    /**
     * Aplica os cabeçalhos de segurança globais
     * às respostas dinâmicas da aplicação.
     */
    public static function apply(): void
    {
        if (headers_sent()) {
            return;
        }

        /*
         * Remove identificação desnecessária do PHP
         * caso esteja presente na resposta.
         */
        header_remove('X-Powered-By');

        /*
         * Impede MIME sniffing.
         */
        header(
            'X-Content-Type-Options: nosniff'
        );

        /*
         * Não envia informações da URL atual
         * através do cabeçalho Referer.
         *
         * Como este é um painel administrativo interno,
         * adotamos uma configuração restritiva.
         */
        header(
            'Referrer-Policy: no-referrer'
        );

        /*
         * Impede que funcionalidades do navegador
         * que não fazem parte do Platform sejam utilizadas.
         *
         * Podemos liberar alguma delas futuramente
         * caso exista necessidade real.
         */
        header(
            'Permissions-Policy: '
                . 'camera=(), '
                . 'microphone=(), '
                . 'geolocation=(), '
                . 'payment=(), '
                . 'usb=()'
        );

        /*
         * Content Security Policy.
         *
         * Regra inicial:
         *
         * - scripts somente do próprio domínio
         * - CSS somente do próprio domínio
         * - nada de scripts inline
         * - nada de CSS inline
         * - nada de objects/plugins
         * - nada de frames
         * - formulários somente para o próprio sistema
         *
         * Isso combina com nossa arquitetura de manter
         * CSS e JavaScript em arquivos externos.
         */
        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "script-src 'self'",
            "style-src 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "media-src 'self'",
            "frame-src 'none'",
            "manifest-src 'self'",
        ];

        header(
            'Content-Security-Policy: '
                . implode('; ', $csp)
        );

        /*
         * Como o Platform é administrativo,
         * não queremos respostas privadas armazenadas
         * no cache do navegador/proxies.
         *
         * Arquivos estáticos servidos diretamente
         * pelo Apache não passam por esta classe.
         */
        header(
            'Cache-Control: no-store, private, max-age=0'
        );

        header(
            'Pragma: no-cache'
        );

        /*
         * HSTS deve ser utilizado somente quando estivermos
         * realmente em produção com HTTPS.
         *
         * Não ativamos includeSubDomains nem preload agora,
         * porque isso deverá ser uma decisão de infraestrutura
         * quando todos os subdomínios estiverem preparados.
         */
        $environment = Env::get(
            'APP_ENV',
            'local'
        );

        $appUrl = Env::get(
            'APP_URL',
            ''
        );

        $isProduction =
            $environment === 'production';

        $usesHttps =
            is_string($appUrl)
            && str_starts_with(
                strtolower($appUrl),
                'https://'
            );

        if ($isProduction && $usesHttps) {
            header(
                'Strict-Transport-Security: max-age=31536000'
            );
        }
    }
}
