<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\AsaasWebhookRepository;
use App\Repositories\IntegrationAuditLogRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use RuntimeException;

final class AsaasWebhookService
{
    private const GATEWAY = 'ASAAS';

    private const SUPPORTED_EVENTS = [
        'PAYMENT_CONFIRMED',
        'PAYMENT_RECEIVED',
    ];

    public function __construct(
        private readonly AsaasWebhookRepository $repository,
        private readonly IntegrationAuditLogRepository $audit,
        private readonly string $ambiente
    ) {}

    /**
     * Recebe o evento e persiste SOMENTE os campos necessários.
     *
     * O endpoint pode responder HTTP 200 logo após esta etapa.
     */
    public function receive(
        array $payload
    ): array {
        $eventType = $this->requiredString(
            $payload,
            'event',
            60
        );

        if (
            !in_array(
                $eventType,
                self::SUPPORTED_EVENTS,
                true
            )
        ) {
            return [
                'ok' => true,
                'ignored' => true,
                'reason' =>
                    'EVENTO_NAO_UTILIZADO',
            ];
        }

        $eventId = $this->requiredPattern(
            $payload,
            'id',
            '/^[A-Za-z0-9_&.\-]{3,200}$/'
        );

        $payment = $payload['payment']
            ?? null;

        if (!is_array($payment)) {
            throw new RuntimeException(
                'Payload de pagamento ausente.'
            );
        }

        $paymentId = $this->requiredPattern(
            $payment,
            'id',
            '/^pay_[A-Za-z0-9_-]{3,116}$/'
        );

        $subscriptionId =
            $this->requiredPattern(
                $payment,
                'subscription',
                '/^sub_[A-Za-z0-9_-]{3,116}$/'
            );

        $dueDate = $this->requiredDate(
            $payment,
            'dueDate'
        );

        $value = $this->normalizeRemoteMoney(
            $payment['value']
            ?? null
        );

        $billingType =
            $this->requiredString(
                $payment,
                'billingType',
                30
            );

        if (
            !in_array(
                $billingType,
                [
                    'PIX',
                    'BOLETO',
                    'CREDIT_CARD',
                    'UNDEFINED',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Forma de pagamento não suportada.'
            );
        }

        $paymentStatus =
            $this->requiredString(
                $payment,
                'status',
                30
            );

        $paymentDate =
            $this->optionalPaymentDate(
                $payment
            );

        $created =
            $this->repository
                ->reserveEvent(
                    self::GATEWAY,
                    $this->ambiente,
                    $eventId,
                    $eventType,
                    $paymentId,
                    $subscriptionId,
                    $dueDate,
                    $value,
                    $billingType,
                    $paymentStatus,
                    $paymentDate
                );

        return [
            'ok' => true,
            'ignored' => false,
            'reserved' =>
                $created,
            'duplicate' =>
                !$created,
        ];
    }

    public function processPending(
        int $limit = 20
    ): array {
        $ids =
            $this->repository
                ->findPendingIds(
                    $limit
                );

        $processed = 0;
        $duplicates = 0;
        $errors = 0;

        foreach ($ids as $id) {
            try {
                $result =
                    $this->processOne(
                        $id
                    );

                if (
                    $result ===
                    'PAGAMENTO_JA_REGISTRADO'
                ) {
                    $duplicates++;
                } else {
                    $processed++;
                }
            } catch (\Throwable) {
                $errors++;
            }
        }

        return [
            'encontrados' =>
                count($ids),
            'processados' =>
                $processed,
            'duplicados' =>
                $duplicates,
            'erros' =>
                $errors,
        ];
    }

    private function processOne(
        int $eventRowId
    ): string {
        $pdo = Database::connection();

        $pdo->beginTransaction();

        try {
            $event =
                $this->repository
                    ->lockEvent(
                        $eventRowId
                    );

            if ($event === null) {
                $pdo->rollBack();

                return 'EVENTO_INEXISTENTE';
            }

            if (
                ($event['status'] ?? null)
                !== 'PENDENTE'
            ) {
                $pdo->rollBack();

                return 'EVENTO_NAO_PENDENTE';
            }

            $this->repository
                ->markProcessing(
                    $eventRowId
                );

            $subscriptionContext =
                $this->repository
                    ->findSubscriptionByGatewayId(
                        self::GATEWAY,
                        $this->ambiente,
                        (string) $event[
                            'gateway_subscription_id'
                        ]
                    );

            if ($subscriptionContext === null) {
                throw new RuntimeException(
                    'ASSINATURA_GATEWAY_NAO_MAPEADA'
                );
            }

            $assinaturaId =
                (int) $subscriptionContext[
                    'assinatura_id'
                ];

            $assinatura =
                $this->repository
                    ->lockSubscription(
                        $assinaturaId
                    );

            if ($assinatura === null) {
                throw new RuntimeException(
                    'ASSINATURA_LOCAL_NAO_ENCONTRADA'
                );
            }

            $status =
                (string) (
                    $assinatura['status']
                    ?? ''
                );

            if (
                !in_array(
                    $status,
                    [
                        'ATIVA',
                        'ATRASADA',
                        'SUSPENSA',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'STATUS_ASSINATURA_INCOMPATIVEL'
                );
            }

            if (
                strtoupper(
                    (string) (
                        $assinatura['moeda']
                        ?? ''
                    )
                ) !== 'BRL'
            ) {
                throw new RuntimeException(
                    'MOEDA_ASSINATURA_INCOMPATIVEL'
                );
            }

            $contractValue =
                $this->normalizeLocalMoney(
                    $assinatura[
                        'valor_contratado'
                    ]
                    ?? null
                );

            $eventValue =
                $this->normalizeLocalMoney(
                    $event['valor']
                    ?? null
                );

            if (
                $contractValue
                !== $eventValue
            ) {
                throw new RuntimeException(
                    'VALOR_DIVERGENTE'
                );
            }

            $paymentId =
                (string) $event[
                    'payment_id'
                ];

            if (
                $this->repository
                    ->paymentAlreadyRegistered(
                        self::GATEWAY,
                        $paymentId
                    )
            ) {
                $this->repository
                    ->markProcessed(
                        $eventRowId,
                        'PAGAMENTO_JA_REGISTRADO'
                    );

                $pdo->commit();

                return 'PAGAMENTO_JA_REGISTRADO';
            }

            $dueDate =
                (string) $event[
                    'vencimento_referencia'
                ];

            $method =
                $this->mapMethod(
                    (string) $event[
                        'billing_type'
                    ]
                );

            $paymentRecordId =
                $this->repository
                    ->createGatewayPayment(
                        $assinaturaId,
                        $dueDate,
                        $contractValue,
                        $method,
                        self::GATEWAY,
                        $paymentId,
                        isset(
                            $event[
                                'payment_date'
                            ]
                        )
                        && is_string(
                            $event[
                                'payment_date'
                            ]
                        )
                            ? $event[
                                'payment_date'
                            ]
                            : null
                    );

            $nextCharge =
                $this->calculateNextCharge(
                    $dueDate,
                    (string) (
                        $assinatura[
                            'periodicidade'
                        ]
                        ?? ''
                    )
                );

            $this->repository
                ->applyConfirmedPayment(
                    $assinaturaId,
                    $nextCharge
                );

            $this->repository
                ->markProcessed(
                    $eventRowId,
                    'PAGAMENTO_REGISTRADO'
                );

            $this->audit->create(
                'ASSINATURA_PAGAMENTO_GATEWAY_CONFIRMADO',
                'ASSINATURA_PAGAMENTO',
                $paymentRecordId,
                [
                    'assinatura_id' =>
                        $assinaturaId,
                    'gateway' =>
                        self::GATEWAY,
                    'ambiente' =>
                        $this->ambiente,
                    'evento' =>
                        $event[
                            'event_type'
                        ],
                    'vencimento_referencia' =>
                        $dueDate,
                    'metodo' =>
                        $method,
                ]
            );

            $pdo->commit();

            return 'PAGAMENTO_REGISTRADO';
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            /*
             * Marca o evento em uma transação separada,
             * mas salva apenas um código controlado.
             */
            $this->markErrorSafely(
                $eventRowId,
                $this->safeErrorCode(
                    $exception
                )
            );

            throw $exception;
        }
    }

    private function markErrorSafely(
        int $eventRowId,
        string $code
    ): void {
        try {
            $this->repository
                ->markError(
                    $eventRowId,
                    $code
                );
        } catch (\Throwable) {
            // Não mascara a falha principal.
        }
    }

    private function safeErrorCode(
        \Throwable $exception
    ): string {
        if ($exception instanceof RuntimeException) {
            $message = $exception->getMessage();

            if (
                preg_match(
                    '/^[A-Z0-9_]{3,80}$/',
                    $message
                ) === 1
            ) {
                return $message;
            }
        }

        if ($exception instanceof PDOException) {
            return 'ERRO_BANCO';
        }

        return 'ERRO_PROCESSAMENTO';
    }

    private function mapMethod(
        string $billingType
    ): string {
        return match (
            strtoupper(
                trim(
                    $billingType
                )
            )
        ) {
            'PIX' =>
                'PIX',
            'BOLETO' =>
                'BOLETO',
            'CREDIT_CARD' =>
                'CARTAO',
            default =>
                'OUTRO',
        };
    }

    private function calculateNextCharge(
        string $dueDate,
        string $periodicidade
    ): string {
        $months = match (
            strtoupper(
                trim(
                    $periodicidade
                )
            )
        ) {
            'MENSAL' => 1,
            'TRIMESTRAL' => 3,
            'SEMESTRAL' => 6,
            'ANUAL' => 12,
            default => throw new RuntimeException(
                'PERIODICIDADE_NAO_SUPORTADA'
            ),
        };

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $dueDate,
                new DateTimeZone(
                    date_default_timezone_get()
                )
            );

        if (
            !$date
            || $date->format(
                'Y-m-d'
            ) !== $dueDate
        ) {
            throw new RuntimeException(
                'VENCIMENTO_INVALIDO'
            );
        }

        $year =
            (int) $date->format('Y');

        $month =
            (int) $date->format('n');

        $day =
            (int) $date->format('j');

        $zeroBased =
            (($year * 12)
            + ($month - 1))
            + $months;

        $targetYear =
            intdiv(
                $zeroBased,
                12
            );

        $targetMonth =
            ($zeroBased % 12)
            + 1;

        $firstTarget =
            DateTimeImmutable::createFromFormat(
                '!Y-n-j',
                $targetYear
                . '-'
                . $targetMonth
                . '-1',
                new DateTimeZone(
                    date_default_timezone_get()
                )
            );

        if (!$firstTarget) {
            throw new RuntimeException(
                'PROXIMO_VENCIMENTO_INVALIDO'
            );
        }

        $lastDay =
            (int) $firstTarget->format('t');

        $targetDay =
            min(
                $day,
                $lastDay
            );

        $target =
            DateTimeImmutable::createFromFormat(
                '!Y-n-j',
                $targetYear
                . '-'
                . $targetMonth
                . '-'
                . $targetDay,
                new DateTimeZone(
                    date_default_timezone_get()
                )
            );

        if (!$target) {
            throw new RuntimeException(
                'PROXIMO_VENCIMENTO_INVALIDO'
            );
        }

        return $target->format(
            'Y-m-d'
        );
    }

    private function requiredString(
        array $source,
        string $key,
        int $maxLength
    ): string {
        $value = $source[$key]
            ?? null;

        if (!is_string($value)) {
            throw new RuntimeException(
                'CAMPO_WEBHOOK_INVALIDO'
            );
        }

        $value = trim(
            $value
        );

        if (
            $value === ''
            || strlen($value)
                > $maxLength
        ) {
            throw new RuntimeException(
                'CAMPO_WEBHOOK_INVALIDO'
            );
        }

        return $value;
    }

    private function requiredPattern(
        array $source,
        string $key,
        string $pattern
    ): string {
        $value =
            $this->requiredString(
                $source,
                $key,
                200
            );

        if (
            preg_match(
                $pattern,
                $value
            ) !== 1
        ) {
            throw new RuntimeException(
                'IDENTIFICADOR_WEBHOOK_INVALIDO'
            );
        }

        return $value;
    }

    private function requiredDate(
        array $source,
        string $key
    ): string {
        $value =
            $this->requiredString(
                $source,
                $key,
                10
            );

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
                'DATA_WEBHOOK_INVALIDA'
            );
        }

        return $value;
    }

    private function optionalPaymentDate(
        array $payment
    ): ?string {
        foreach (
            [
                'paymentDate',
                'clientPaymentDate',
                'confirmedDate',
            ]
            as $key
        ) {
            $value = $payment[$key]
                ?? null;

            if (
                !is_string($value)
                || $value === ''
            ) {
                continue;
            }

            $date =
                DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $value
                );

            if (
                $date
                && $date->format(
                    'Y-m-d'
                ) === $value
            ) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeRemoteMoney(
        mixed $value
    ): string {
        if (
            !is_int($value)
            && !is_float($value)
            && !is_string($value)
        ) {
            throw new RuntimeException(
                'VALOR_WEBHOOK_INVALIDO'
            );
        }

        if (
            is_string($value)
            && preg_match(
                '/^\d+(?:\.\d{1,2})?$/',
                $value
            ) === 1
        ) {
            return number_format(
                (float) $value,
                2,
                '.',
                ''
            );
        }

        if (
            is_int($value)
            || is_float($value)
        ) {
            if (
                !is_finite(
                    (float) $value
                )
                || (float) $value <= 0
            ) {
                throw new RuntimeException(
                    'VALOR_WEBHOOK_INVALIDO'
                );
            }

            /*
             * Conversão apenas na fronteira JSON externa.
             * A regra financeira interna permanece NUMERIC/string.
             */
            return number_format(
                (float) $value,
                2,
                '.',
                ''
            );
        }

        throw new RuntimeException(
            'VALOR_WEBHOOK_INVALIDO'
        );
    }

    private function normalizeLocalMoney(
        mixed $value
    ): string {
        if (
            !is_string($value)
            && !is_int($value)
        ) {
            throw new RuntimeException(
                'VALOR_LOCAL_INVALIDO'
            );
        }

        $value = (string) $value;

        if (
            preg_match(
                '/^\d+(?:\.\d+)?$/',
                $value
            ) !== 1
        ) {
            throw new RuntimeException(
                'VALOR_LOCAL_INVALIDO'
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
            strlen($fraction) > 2
            && trim(
                substr(
                    $fraction,
                    2
                ),
                '0'
            ) !== ''
        ) {
            throw new RuntimeException(
                'VALOR_LOCAL_PRECISAO_INVALIDA'
            );
        }

        $integer =
            ltrim(
                $integer,
                '0'
            );

        if ($integer === '') {
            $integer = '0';
        }

        return $integer
            . '.'
            . substr(
                $fraction,
                0,
                2
            );
    }
}
