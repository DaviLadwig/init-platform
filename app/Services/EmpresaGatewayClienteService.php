<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Repositories\EmpresaGatewayClienteRepository;
use App\Repositories\IntegrationAuditLogRepository;
use RuntimeException;

final class EmpresaGatewayClienteService
{
    public function __construct(
        private readonly EmpresaGatewayClienteRepository $repository,
        private readonly IntegrationAuditLogRepository $audit,
        private readonly PaymentGatewayInterface $gateway,
        private readonly string $ambiente
    ) {}

    /**
     * Cria ou recupera o customer do gateway para uma EMPRESA.
     *
     * O vínculo é por empresa, não por assinatura:
     * uma empresa pode contratar vários produtos, mas continua
     * sendo o mesmo pagador no Asaas.
     */
    public function vincularEmpresa(
        int $empresaId
    ): array {
        if ($empresaId <= 0) {
            throw new RuntimeException(
                'Identificador de empresa inválido.'
            );
        }

        if (
            !$this->repository
                ->tryAcquireCompanyGatewayLock(
                    $empresaId
                )
        ) {
            return [
                'ok' => false,
                'motivo' =>
                    'OUTRA_EXECUCAO_EM_ANDAMENTO',
            ];
        }

        try {
            $existing =
                $this->repository
                    ->findMapping(
                        $empresaId,
                        $this->gateway
                            ->provider(),
                        $this->ambiente
                    );

            if ($existing !== null) {
                return [
                    'ok' => true,
                    'empresa_id' =>
                        $empresaId,
                    'provider' =>
                        $this->gateway
                            ->provider(),
                    'environment' =>
                        $this->ambiente,
                    'gateway_customer_id' =>
                        $existing[
                            'gateway_customer_id'
                        ],
                    'origem' =>
                        'JA_VINCULADO',
                ];
            }

            $empresa =
                $this->repository
                    ->findActiveCompanyById(
                        $empresaId
                    );

            if ($empresa === null) {
                throw new RuntimeException(
                    'Empresa ativa não encontrada.'
                );
            }

            $cnpj = $this->normalizeCnpj(
                $empresa['cnpj']
                ?? null
            );

            if (
                !$this->isValidCnpj(
                    $cnpj
                )
            ) {
                throw new RuntimeException(
                    'A empresa precisa possuir um CNPJ matematicamente válido para integração com o gateway.'
                );
            }

            $externalReference =
                'init_empresa_'
                . $empresaId;

            /*
             * 1. Procura pela referência estável da Init.
             * Isso é preferível ao nome/e-mail.
             */
            $byExternalReference =
                $this->gateway
                    ->findCustomersByExternalReference(
                        $externalReference
                    );

            if (
                count(
                    $byExternalReference
                ) > 1
            ) {
                throw new RuntimeException(
                    'Há mais de um cliente no gateway com a mesma referência externa.'
                );
            }

            if (
                count(
                    $byExternalReference
                ) === 1
            ) {
                return $this->persistRemoteCustomer(
                    $empresaId,
                    $externalReference,
                    $byExternalReference[0],
                    'LOCALIZADO_EXTERNAL_REFERENCE'
                );
            }

            /*
             * 2. Procura pelo CNPJ antes de criar.
             * O Asaas permite duplicidade, então esta checagem
             * é obrigatória para nosso fluxo.
             */
            $byDocument =
                $this->gateway
                    ->findCustomersByDocument(
                        $cnpj
                    );

            if (
                count(
                    $byDocument
                ) > 1
            ) {
                throw new RuntimeException(
                    'Há mais de um cliente no gateway com o mesmo CNPJ. A vinculação exige revisão manual.'
                );
            }

            if (
                count(
                    $byDocument
                ) === 1
            ) {
                return $this->persistRemoteCustomer(
                    $empresaId,
                    $externalReference,
                    $byDocument[0],
                    'LOCALIZADO_DOCUMENTO'
                );
            }

            /*
             * 3. Só cria quando as duas buscas retornam vazio.
             */
            $created =
                $this->gateway
                    ->createCustomer(
                        $this->buildCustomerPayload(
                            $empresa,
                            $cnpj,
                            $externalReference
                        )
                    );

            return $this->persistRemoteCustomer(
                $empresaId,
                $externalReference,
                $created,
                'CRIADO'
            );
        } finally {
            $this->repository
                ->releaseCompanyGatewayLock(
                    $empresaId
                );
        }
    }

    private function persistRemoteCustomer(
        int $empresaId,
        string $externalReference,
        array $remoteCustomer,
        string $origin
    ): array {
        $customerId =
            $remoteCustomer['id']
            ?? null;

        if (
            !is_string(
                $customerId
            )
            || preg_match(
                '/^[A-Za-z0-9_-]{3,120}$/',
                $customerId
            ) !== 1
        ) {
            throw new RuntimeException(
                'O gateway retornou um identificador de cliente inválido.'
            );
        }

        /*
         * Rechecagem imediatamente antes do INSERT.
         */
        $existing =
            $this->repository
                ->findMapping(
                    $empresaId,
                    $this->gateway
                        ->provider(),
                    $this->ambiente
                );

        if ($existing !== null) {
            return [
                'ok' => true,
                'empresa_id' =>
                    $empresaId,
                'provider' =>
                    $this->gateway
                        ->provider(),
                'environment' =>
                    $this->ambiente,
                'gateway_customer_id' =>
                    $existing[
                        'gateway_customer_id'
                    ],
                'origem' =>
                    'JA_VINCULADO',
            ];
        }

        $mappingId =
            $this->repository
                ->createMapping(
                    $empresaId,
                    $this->gateway
                        ->provider(),
                    $this->ambiente,
                    $customerId,
                    $externalReference
                );

        /*
         * Auditoria mínima:
         * não registramos CNPJ, e-mail, telefone nem credenciais.
         */
        $this->audit->create(
            'EMPRESA_GATEWAY_CLIENTE_VINCULADO',
            'EMPRESA_GATEWAY_CLIENTE',
            $mappingId,
            [
                'empresa_id' =>
                    $empresaId,
                'gateway' =>
                    $this->gateway
                        ->provider(),
                'ambiente' =>
                    $this->ambiente,
                'origem_vinculo' =>
                    $origin,
            ]
        );

        return [
            'ok' => true,
            'empresa_id' =>
                $empresaId,
            'provider' =>
                $this->gateway
                    ->provider(),
            'environment' =>
                $this->ambiente,
            'gateway_customer_id' =>
                $customerId,
            'origem' =>
                $origin,
        ];
    }

    private function buildCustomerPayload(
        array $empresa,
        string $cnpj,
        string $externalReference
    ): array {
        $nomeFantasia =
            isset(
                $empresa['nome_fantasia']
            )
            && is_string(
                $empresa['nome_fantasia']
            )
                ? trim(
                    $empresa['nome_fantasia']
                )
                : '';

        $razaoSocial =
            isset(
                $empresa['razao_social']
            )
            && is_string(
                $empresa['razao_social']
            )
                ? trim(
                    $empresa['razao_social']
                )
                : '';

        $name = $nomeFantasia !== ''
            ? $nomeFantasia
            : $razaoSocial;

        if ($name === '') {
            throw new RuntimeException(
                'A empresa não possui nome válido para integração.'
            );
        }

        $payload = [
            'name' => $name,
            'cpfCnpj' => $cnpj,
            'externalReference' =>
                $externalReference,

            /*
             * Enquanto homologamos o fluxo, o Asaas não envia
             * mensagens automáticas aos clientes da Init.
             */
            'notificationDisabled' =>
                true,
        ];

        $email =
            isset($empresa['email'])
            && is_string(
                $empresa['email']
            )
                ? trim(
                    $empresa['email']
                )
                : '';

        if (
            $email !== ''
            && filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) !== false
        ) {
            $payload['email'] =
                $email;
        }

        $telefone =
            isset($empresa['telefone'])
            && is_string(
                $empresa['telefone']
            )
                ? preg_replace(
                    '/\D+/',
                    '',
                    $empresa['telefone']
                )
                : null;

        if (
            is_string($telefone)
            && in_array(
                strlen($telefone),
                [10, 11],
                true
            )
        ) {
            $payload['mobilePhone'] =
                $telefone;
        }

        return $payload;
    }

    private function normalizeCnpj(
        mixed $value
    ): string {
        if (!is_string($value)) {
            return '';
        }

        $digits = preg_replace(
            '/\D+/',
            '',
            $value
        );

        return is_string($digits)
            ? $digits
            : '';
    }

    private function isValidCnpj(
        string $cnpj
    ): bool {
        if (
            strlen($cnpj) !== 14
            || preg_match(
                '/^\d{14}$/',
                $cnpj
            ) !== 1
            || preg_match(
                '/^(\d)\1{13}$/',
                $cnpj
            ) === 1
        ) {
            return false;
        }

        $calculateDigit =
            static function (
                string $base,
                array $weights
            ): int {
                $sum = 0;

                foreach (
                    $weights
                    as $index => $weight
                ) {
                    $sum +=
                        (int) $base[$index]
                        * $weight;
                }

                $remainder =
                    $sum % 11;

                return $remainder < 2
                    ? 0
                    : 11 - $remainder;
            };

        $first =
            $calculateDigit(
                substr(
                    $cnpj,
                    0,
                    12
                ),
                [
                    5,
                    4,
                    3,
                    2,
                    9,
                    8,
                    7,
                    6,
                    5,
                    4,
                    3,
                    2,
                ]
            );

        if (
            $first
            !== (int) $cnpj[12]
        ) {
            return false;
        }

        $second =
            $calculateDigit(
                substr(
                    $cnpj,
                    0,
                    12
                )
                . $first,
                [
                    6,
                    5,
                    4,
                    3,
                    2,
                    9,
                    8,
                    7,
                    6,
                    5,
                    4,
                    3,
                    2,
                ]
            );

        return $second
            === (int) $cnpj[13];
    }
}
