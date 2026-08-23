<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClienteRepository;

final class ClienteService
{
    public function __construct(
        private readonly ClienteRepository $clientes
    ) {}

    public function listar(): array
    {
        $clientes = $this->clientes->all();

        foreach ($clientes as &$cliente) {
            $cliente['situacao_comercial'] =
                $this->resolverSituacao(
                    $cliente
                );
        }

        unset($cliente);

        return $clientes;
    }

    /**
     * Situação resumida para a listagem.
     *
     * Não substitui o status individual
     * de cada assinatura.
     */
    private function resolverSituacao(
        array $cliente
    ): string {
        $suspensas = (int) (
            $cliente['assinaturas_suspensas']
            ?? 0
        );

        $atrasadas = (int) (
            $cliente['assinaturas_atrasadas']
            ?? 0
        );

        $ativas = (int) (
            $cliente['assinaturas_ativas']
            ?? 0
        );

        $trial = (int) (
            $cliente['assinaturas_trial']
            ?? 0
        );

        $pendentes = (int) (
            $cliente['assinaturas_pendentes']
            ?? 0
        );

        $total = (int) (
            $cliente['total_assinaturas']
            ?? 0
        );

        if ($suspensas > 0) {
            return 'SUSPENSO';
        }

        if ($atrasadas > 0) {
            return 'ATENCAO';
        }

        if ($ativas > 0) {
            return 'ATIVO';
        }

        if ($trial > 0) {
            return 'TRIAL';
        }

        if ($pendentes > 0) {
            return 'PENDENTE';
        }

        if ($total === 0) {
            return 'SEM_ASSINATURA';
        }

        return 'SEM_ASSINATURA';
    }
}
