<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Repositories\AssinaturaGatewayRepository;
use App\Repositories\IntegrationAuditLogRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class AssinaturaGatewayService
{
    private const BILLING_TYPE =
        'UNDEFINED';

    public function __construct(
        private readonly AssinaturaGatewayRepository $repository,
        private readonly IntegrationAuditLogRepository $audit,
        private readonly PaymentGatewayInterface $gateway,
        private readonly string $ambiente
    ) {}

    public function vincularAssinatura(
        int $assinaturaId
    ): array {
        if ($assinaturaId <= 0) {
            throw new RuntimeException(
                'Identificador de assinatura inválido.'
            );
        }

        if (
            !$this->repository
                ->tryAcquireSubscriptionGatewayLock(
                    $assinaturaId
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
                        $assinaturaId,
                        $this->gateway
                            ->provider(),
                        $this->ambiente
                    );

            if ($existing !== null) {
                return $this->buildResultFromExisting(
                    $existing
                );
            }

            $context =
                $this->repository
                    ->findActiveSubscriptionContext(
                        $assinaturaId,
                        $this->gateway
                            ->provider(),
                        $this->ambiente
                    );

            if ($context === null) {
                throw new RuntimeException(
                    'A assinatura precisa estar ATIVA e a empresa precisa estar vinculada ao Asaas neste ambiente.'
                );
            }

            $currency =
                isset($context['moeda'])
                && is_string(
                    $context['moeda']
                )
                    ? strtoupper(
                        trim(
                            $context['moeda']
                        )
                    )
                    : '';

            if ($currency !== 'BRL') {
                throw new RuntimeException(
                    'A integração Asaas desta versão aceita apenas contratos em BRL.'
                );
            }

            $value =
                $this->normalizeMoney(
                    $context[
                        'valor_contratado'
                    ]
                    ?? null
                );

            $cycle =
                $this->mapCycle(
                    $context[
                        'periodicidade'
                    ]
                    ?? null
                );

            $nextDueDate =
                $this->normalizeDueDate(
                    $context[
                        'proxima_cobranca_em'
                    ]
                    ?? null
                );

            $this->assertDueDateIsNotPast(
                $nextDueDate
            );

            $customerId =
                isset(
                    $context[
                        'gateway_customer_id'
                    ]
                )
                && is_string(
                    $context[
                        'gateway_customer_id'
                    ]
                )
                    ? trim(
                        $context[
                            'gateway_customer_id'
                        ]
                    )
                    : '';

            if (
                preg_match(
                    '/^cus_[A-Za-z0-9_-]{3,116}$/',
                    $customerId
                ) !== 1
            ) {
                throw new RuntimeException(
                    'Customer Asaas da empresa inválido.'
                );
            }

            $externalReference =
                'init_assinatura_'
                . $assinaturaId;

            $remote =
                $this->gateway
                    ->findSubscriptionsByExternalReference(
                        $externalReference
                    );

            if (count($remote) > 1) {
                throw new RuntimeException(
                    'Há mais de uma assinatura no gateway com a mesma referência externa.'
                );
            }

            if (count($remote) === 1) {
                $this->assertRemoteSubscriptionMatches(
                    $remote[0],
                    $customerId,
                    $externalReference,
                    $cycle,
                    $value
                );

                return $this->persistRemoteSubscription(
                    $assinaturaId,
                    $externalReference,
                    $remote[0],
                    $cycle,
                    'LOCALIZADA_EXTERNAL_REFERENCE'
                );
            }

            $created =
                $this->gateway
                    ->createSubscription(
                        [
                            'customer' =>
                                $customerId,
                            'billingType' =>
                                self::BILLING_TYPE,
                            'value' =>
                                $value,
                            'nextDueDate' =>
                                $nextDueDate,
                            'cycle' =>
                                $cycle,
                            'description' =>
                                $this->buildDescription(
                                    $context,
                                    $assinaturaId
                                ),
                            'externalReference' =>
                                $externalReference,
                        ]
                    );

            $this->assertRemoteSubscriptionMatches(
                $created,
                $customerId,
                $externalReference,
                $cycle,
                $value
            );

            return $this->persistRemoteSubscription(
                $assinaturaId,
                $externalReference,
                $created,
                $cycle,
                'CRIADA'
            );
        } finally {
            $this->repository
                ->releaseSubscriptionGatewayLock(
                    $assinaturaId
                );
        }
    }

    private function buildResultFromExisting(
        array $existing
    ): array {
        $gatewaySubscriptionId =
            isset(
                $existing[
                    'gateway_subscription_id'
                ]
            )
            && is_string(
                $existing[
                    'gateway_subscription_id'
                ]
            )
                ? $existing[
                    'gateway_subscription_id'
                ]
                : '';

        $payments =
            $this->gateway
                ->listSubscriptionPayments(
                    $gatewaySubscriptionId
                );

        return [
            'ok' => true,
            'assinatura_id' =>
                (int) (
                    $existing[
                        'assinatura_id'
                    ]
                    ?? 0
                ),
            'provider' =>
                $existing['gateway']
                ?? $this->gateway
                    ->provider(),
            'environment' =>
                $existing['ambiente']
                ?? $this->ambiente,
            'gateway_subscription_id' =>
                $gatewaySubscriptionId,
            'billing_type' =>
                $existing['billing_type']
                ?? self::BILLING_TYPE,
            'cycle' =>
                $existing['cycle']
                ?? null,
            'cobrancas_geradas' =>
                count(
                    $payments
                ),
            'origem' =>
                'JA_VINCULADA',
        ];
    }

    private function persistRemoteSubscription(
        int $assinaturaId,
        string $externalReference,
        array $remoteSubscription,
        string $cycle,
        string $origin
    ): array {
        $subscriptionId =
            $remoteSubscription['id']
            ?? null;

        if (
            !is_string(
                $subscriptionId
            )
            || preg_match(
                '/^sub_[A-Za-z0-9_-]{3,116}$/',
                $subscriptionId
            ) !== 1
        ) {
            throw new RuntimeException(
                'O gateway retornou um identificador de assinatura inválido.'
            );
        }

        $existing =
            $this->repository
                ->findMapping(
                    $assinaturaId,
                    $this->gateway
                        ->provider(),
                    $this->ambiente
                );

        if ($existing !== null) {
            return $this->buildResultFromExisting(
                $existing
            );
        }

        $mappingId =
            $this->repository
                ->createMapping(
                    $assinaturaId,
                    $this->gateway
                        ->provider(),
                    $this->ambiente,
                    $subscriptionId,
                    $externalReference,
                    self::BILLING_TYPE,
                    $cycle
                );

        $this->audit->create(
            'ASSINATURA_GATEWAY_VINCULADA',
            'ASSINATURA_GATEWAY',
            $mappingId,
            [
                'assinatura_id' =>
                    $assinaturaId,
                'gateway' =>
                    $this->gateway
                        ->provider(),
                'ambiente' =>
                    $this->ambiente,
                'billing_type' =>
                    self::BILLING_TYPE,
                'cycle' =>
                    $cycle,
                'origem_vinculo' =>
                    $origin,
            ]
        );

        $payments =
            $this->gateway
                ->listSubscriptionPayments(
                    $subscriptionId
                );

        return [
            'ok' => true,
            'assinatura_id' =>
                $assinaturaId,
            'provider' =>
                $this->gateway
                    ->provider(),
            'environment' =>
                $this->ambiente,
            'gateway_subscription_id' =>
                $subscriptionId,
            'billing_type' =>
                self::BILLING_TYPE,
            'cycle' =>
                $cycle,
            'cobrancas_geradas' =>
                count(
                    $payments
                ),
            'origem' =>
                $origin,
        ];
    }

    private function assertRemoteSubscriptionMatches(
        array $remote,
        string $customerId,
        string $externalReference,
        string $cycle,
        string $value
    ): void {
        $remoteCustomer =
            $remote['customer']
            ?? null;

        if (
            is_string(
                $remoteCustomer
            )
            && $remoteCustomer !== ''
            && $remoteCustomer !== $customerId
        ) {
            throw new RuntimeException(
                'A assinatura localizada no gateway pertence a outro customer.'
            );
        }

        $remoteReference =
            $remote['externalReference']
            ?? null;

        if (
            is_string(
                $remoteReference
            )
            && $remoteReference !== ''
            && $remoteReference
                !== $externalReference
        ) {
            throw new RuntimeException(
                'A assinatura localizada no gateway possui outra referência externa.'
            );
        }

        $remoteCycle =
            $remote['cycle']
            ?? null;

        if (
            is_string(
                $remoteCycle
            )
            && $remoteCycle !== ''
            && strtoupper(
                $remoteCycle
            ) !== $cycle
        ) {
            throw new RuntimeException(
                'A periodicidade da assinatura remota diverge do contrato local.'
            );
        }

        if (
            array_key_exists(
                'value',
                $remote
            )
            && !$this->moneyEquals(
                $remote['value'],
                $value
            )
        ) {
            throw new RuntimeException(
                'O valor da assinatura remota diverge do contrato local.'
            );
        }
    }

    private function buildDescription(
        array $context,
        int $assinaturaId
    ): string {
        $produto =
            isset(
                $context['produto_nome']
            )
            && is_string(
                $context['produto_nome']
            )
                ? trim(
                    $context[
                        'produto_nome'
                    ]
                )
                : 'Produto Init';

        $plano =
            isset(
                $context['plano_nome']
            )
            && is_string(
                $context['plano_nome']
            )
                ? trim(
                    $context[
                        'plano_nome'
                    ]
                )
                : 'Plano';

        $description =
            $produto
            . ' - '
            . $plano
            . ' - Assinatura Init #'
            . $assinaturaId;

        return substr(
            $description,
            0,
            500
        );
    }

    private function mapCycle(
        mixed $periodicity
    ): string {
        if (!is_string($periodicity)) {
            throw new RuntimeException(
                'Periodicidade local inválida.'
            );
        }

        return match (
            strtoupper(
                trim(
                    $periodicity
                )
            )
        ) {
            'MENSAL' =>
                'MONTHLY',
            'TRIMESTRAL' =>
                'QUARTERLY',
            'SEMESTRAL' =>
                'SEMIANNUALLY',
            'ANUAL' =>
                'YEARLY',
            default =>
                throw new RuntimeException(
                    'Periodicidade local não suportada pelo gateway.'
                ),
        };
    }

    private function normalizeMoney(
        mixed $value
    ): string {
        if (
            !is_string($value)
            && !is_int($value)
        ) {
            throw new RuntimeException(
                'Valor contratado inválido.'
            );
        }

        $value = trim(
            (string) $value
        );

        if (
            preg_match(
                '/^\d{1,10}(?:\.\d+)?$/',
                $value
            ) !== 1
        ) {
            throw new RuntimeException(
                'Valor contratado inválido.'
            );
        }

        [$integer, $fraction] =
            array_pad(
                explode(
                    '.',
                    $value,
                    2
                ),
                2,
                ''
            );

        $fraction = str_pad(
            $fraction,
            2,
            '0'
        );

        if (
            strlen(
                $fraction
            ) > 2
            && trim(
                substr(
                    $fraction,
                    2
                ),
                '0'
            ) !== ''
        ) {
            throw new RuntimeException(
                'O valor contratado possui precisão incompatível com cobrança em centavos.'
            );
        }

        $fraction = substr(
            $fraction,
            0,
            2
        );

        $normalized =
            ltrim(
                $integer,
                '0'
            );

        if ($normalized === '') {
            $normalized = '0';
        }

        $normalized .= '.'
            . $fraction;

        if ($normalized === '0.00') {
            throw new RuntimeException(
                'O valor contratado deve ser maior que zero.'
            );
        }

        return $normalized;
    }

    private function normalizeDueDate(
        mixed $value
    ): string {
        if (
            !is_string($value)
            || preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $value
            ) !== 1
        ) {
            throw new RuntimeException(
                'A assinatura não possui próxima cobrança válida.'
            );
        }

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $value
            );

        if (
            !$date
            || $date->format(
                'Y-m-d'
            ) !== $value
        ) {
            throw new RuntimeException(
                'A próxima cobrança possui data inválida.'
            );
        }

        return $value;
    }

    private function assertDueDateIsNotPast(
        string $dueDate
    ): void {
        $timezone =
            new DateTimeZone(
                date_default_timezone_get()
            );

        $today =
            new DateTimeImmutable(
                'today',
                $timezone
            );

        $due =
            new DateTimeImmutable(
                $dueDate,
                $timezone
            );

        if ($due < $today) {
            throw new RuntimeException(
                'A próxima cobrança está vencida. Regularize a assinatura antes de criar a recorrência no gateway.'
            );
        }
    }

    private function moneyEquals(
        mixed $remoteValue,
        string $localValue
    ): bool {
        if (
            !is_string(
                $remoteValue
            )
            && !is_int(
                $remoteValue
            )
            && !is_float(
                $remoteValue
            )
        ) {
            return false;
        }

        $remoteString =
            number_format(
                (float) $remoteValue,
                2,
                '.',
                ''
            );

        /*
         * Float é usado apenas para NORMALIZAR o valor recebido
         * da API para comparação de transporte. A regra de preço
         * local continua vindo do NUMERIC do PostgreSQL.
         */
        return $remoteString
            === $localValue;
    }
}
