<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use JsonException;
use PDO;
use RuntimeException;

final class SystemAuditLogRepository
{
    /**
     * Registra ações executadas automaticamente pela própria plataforma.
     *
     * usuario_id permanece NULL e origem = SISTEMA.
     * Isso evita atribuir a um administrador humano uma ação que foi
     * executada pelo scheduler.
     */
    public function create(
        string $acao,
        string $modulo,
        string $entidade,
        ?int $entidadeId,
        ?array $dadosAnteriores,
        ?array $dadosNovos
    ): void {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            INSERT INTO public.logs (
                usuario_id,
                acao,
                modulo,
                entidade,
                entidade_id,
                ip,
                user_agent,
                dados_anteriores,
                dados_novos,
                origem
            )
            VALUES (
                NULL,
                :acao,
                :modulo,
                :entidade,
                :entidade_id,
                NULL,
                :user_agent,
                CAST(:dados_anteriores AS jsonb),
                CAST(:dados_novos AS jsonb),
                \'SISTEMA\'
            )
            '
        );

        $statement->bindValue(
            ':acao',
            $acao,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':modulo',
            $modulo,
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':entidade',
            $entidade,
            PDO::PARAM_STR
        );

        if ($entidadeId === null) {
            $statement->bindValue(
                ':entidade_id',
                null,
                PDO::PARAM_NULL
            );
        } else {
            $statement->bindValue(
                ':entidade_id',
                $entidadeId,
                PDO::PARAM_INT
            );
        }

        $statement->bindValue(
            ':user_agent',
            'Init SaaS Platform Scheduler',
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':dados_anteriores',
            $this->encodeJson(
                $dadosAnteriores
            ),
            PDO::PARAM_STR
        );

        $statement->bindValue(
            ':dados_novos',
            $this->encodeJson(
                $dadosNovos
            ),
            PDO::PARAM_STR
        );

        $statement->execute();
    }

    private function encodeJson(
        ?array $value
    ): string {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Não foi possível serializar os dados da auditoria automática.',
                0,
                $exception
            );
        }
    }
}
