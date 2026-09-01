<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FinanceiroRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class FinanceiroService
{
    public function __construct(
        private readonly FinanceiroRepository $financeiro
    ) {}

    public function obterVisaoGeral(
        int $mesesPrevisao = 12
    ): array {
        if (
            $mesesPrevisao < 1
            || $mesesPrevisao > 24
        ) {
            throw new RuntimeException(
                'Horizonte de previsão inválido.'
            );
        }

        $timezone = new DateTimeZone(
            date_default_timezone_get()
        );

        $hoje = new DateTimeImmutable(
            'today',
            $timezone
        );

        $inicioMes = $hoje
            ->modify('first day of this month')
            ->format('Y-m-d');

        $fimMes = $hoje
            ->modify('last day of this month')
            ->format('Y-m-d');

        $fimHorizonte = $hoje
            ->modify('first day of this month')
            ->modify(
                '+'
                . ($mesesPrevisao - 1)
                . ' months'
            )
            ->modify('last day of this month')
            ->format('Y-m-d');

        return [
            'periodo' => [
                'hoje' => $hoje->format('Y-m-d'),
                'inicio_mes' => $inicioMes,
                'fim_mes' => $fimMes,
                'fim_previsao' => $fimHorizonte,
            ],

            'indicadores' =>
                $this->normalizeSummary(
                    $this->financeiro
                        ->findSummary(
                            $inicioMes,
                            $fimMes,
                            $hoje->format('Y-m-d')
                        )
                ),

            'por_produto' =>
                $this->normalizeProductRows(
                    $this->financeiro
                        ->findMrrByProduct()
                ),

            'por_plano' =>
                $this->normalizePlanRows(
                    $this->financeiro
                        ->findMrrByPlan()
                ),

            'previsao' =>
                $this->normalizeForecastRows(
                    $this->financeiro
                        ->findForecast(
                            $hoje->format('Y-m-d'),
                            $fimHorizonte
                        )
                ),
        ];
    }

    private function decimal(
        mixed $value
    ): string {
        if (
            is_string($value)
            && preg_match(
                '/^-?\d+(?:\.\d+)?$/',
                $value
            ) === 1
        ) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return '0';
    }

    private function intValue(
        mixed $value
    ): int {
        if (is_int($value)) {
            return $value;
        }

        if (
            is_string($value)
            && ctype_digit($value)
        ) {
            return (int) $value;
        }

        return 0;
    }

    private function stringValue(
        array $source,
        string $key
    ): string {
        $value = $source[$key] ?? null;

        return is_string($value)
            ? $value
            : '';
    }

    private function normalizeSummary(
        array $row
    ): array {
        return [
            'recebido_mes' =>
                $this->decimal(
                    $row['recebido_mes'] ?? null
                ),

            'a_receber_mes' =>
                $this->decimal(
                    $row['a_receber_mes'] ?? null
                ),

            'vencido' =>
                $this->decimal(
                    $row['vencido'] ?? null
                ),

            'mrr_ativo' =>
                $this->decimal(
                    $row['mrr_ativo'] ?? null
                ),

            'mrr_risco' =>
                $this->decimal(
                    $row['mrr_risco'] ?? null
                ),

            'mrr_contratado' =>
                $this->decimal(
                    $row['mrr_contratado'] ?? null
                ),

            'arr_ativo' =>
                $this->decimal(
                    $row['arr_ativo'] ?? null
                ),

            'ticket_medio' =>
                $this->decimal(
                    $row['ticket_medio'] ?? null
                ),

            'inadimplencia_percentual' =>
                $this->decimal(
                    $row['inadimplencia_percentual'] ?? null
                ),

            'clientes_ativos' =>
                $this->intValue(
                    $row['clientes_ativos'] ?? null
                ),

            'clientes_inadimplentes' =>
                $this->intValue(
                    $row['clientes_inadimplentes'] ?? null
                ),
        ];
    }

    private function normalizeProductRows(
        array $rows
    ): array {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $result[] = [
                'produto_id' =>
                    $this->intValue(
                        $row['produto_id'] ?? null
                    ),

                'produto_codigo' =>
                    $this->stringValue(
                        $row,
                        'produto_codigo'
                    ),

                'produto_nome' =>
                    $this->stringValue(
                        $row,
                        'produto_nome'
                    ),

                'clientes' =>
                    $this->intValue(
                        $row['clientes'] ?? null
                    ),

                'mrr_ativo' =>
                    $this->decimal(
                        $row['mrr_ativo'] ?? null
                    ),
            ];
        }

        return $result;
    }

    private function normalizePlanRows(
        array $rows
    ): array {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $result[] = [
                'produto_id' =>
                    $this->intValue(
                        $row['produto_id'] ?? null
                    ),

                'produto_nome' =>
                    $this->stringValue(
                        $row,
                        'produto_nome'
                    ),

                'plano_id' =>
                    $this->intValue(
                        $row['plano_id'] ?? null
                    ),

                'plano_codigo' =>
                    $this->stringValue(
                        $row,
                        'plano_codigo'
                    ),

                'plano_nome' =>
                    $this->stringValue(
                        $row,
                        'plano_nome'
                    ),

                'clientes' =>
                    $this->intValue(
                        $row['clientes'] ?? null
                    ),

                'mrr_ativo' =>
                    $this->decimal(
                        $row['mrr_ativo'] ?? null
                    ),
            ];
        }

        return $result;
    }

    private function normalizeForecastRows(
        array $rows
    ): array {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $mes = $this->stringValue(
                $row,
                'mes'
            );

            if (
                preg_match(
                    '/^\d{4}-\d{2}$/',
                    $mes
                ) !== 1
            ) {
                continue;
            }

            $result[] = [
                'mes' => $mes,

                'valor_previsto' =>
                    $this->decimal(
                        $row['valor_previsto'] ?? null
                    ),

                'cobrancas_previstas' =>
                    $this->intValue(
                        $row['cobrancas_previstas'] ?? null
                    ),
            ];
        }

        return $result;
    }
}
