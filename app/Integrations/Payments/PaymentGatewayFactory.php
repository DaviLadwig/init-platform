<?php

declare(strict_types=1);

namespace App\Integrations\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Core\Env;
use App\Integrations\Payments\Asaas\AsaasGateway;

final class PaymentGatewayFactory
{
    public static function asaasFromEnvironment(): PaymentGatewayInterface
    {
        $environment = strtolower(
            trim(
                Env::required(
                    'ASAAS_ENV'
                )
            )
        );

        $apiKey = trim(
            Env::required(
                'ASAAS_API_KEY'
            )
        );

        if ($environment === 'sandbox') {
            $baseUrl =
                'https://api-sandbox.asaas.com/v3';

            $expectedPrefix =
                '$aact_hmlg_';
        } elseif ($environment === 'production') {
            $baseUrl =
                'https://api.asaas.com/v3';

            $expectedPrefix =
                '$aact_prod_';
        } else {
            throw new PaymentGatewayException(
                'ASAAS_ENV deve ser sandbox ou production.'
            );
        }

        if (
            !str_starts_with(
                $apiKey,
                $expectedPrefix
            )
        ) {
            throw new PaymentGatewayException(
                'A credencial Asaas não corresponde ao ambiente configurado.'
            );
        }

        return new AsaasGateway(
            $baseUrl,
            $apiKey,
            'InitSaaSPlatform/1.0'
        );
    }

    public static function asaasEnvironment(): string
    {
        $environment = strtolower(
            trim(
                Env::required(
                    'ASAAS_ENV'
                )
            )
        );

        return match ($environment) {
            'sandbox' => 'SANDBOX',
            'production' => 'PRODUCTION',
            default => throw new PaymentGatewayException(
                'ASAAS_ENV deve ser sandbox ou production.'
            ),
        };
    }
}
