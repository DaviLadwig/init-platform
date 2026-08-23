<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use JsonException;

final class AuditLogRepository
{
    public function create(
        ?int $usuarioId,
        string $acao,
        string $modulo,
        string $entidade,
        ?int $entidadeId,
        ?string $ip,
        ?string $userAgent,
        ?array $dadosAnteriores,
        ?array $dadosNovos
    ): void {
        $pdo = Database::connection();

        try {
            $dadosAnterioresJson =
                $dadosAnteriores !== null
                ? json_encode(
                    $dadosAnteriores,
                    JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                )
                : null;

            $dadosNovosJson =
                $dadosNovos !== null
                ? json_encode(
                    $dadosNovos,
                    JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                )
                : null;
        } catch (JsonException $exception) {
            throw new \RuntimeException(
                'Não foi possível serializar os dados de auditoria.',
                0,
                $exception
            );
        }

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
                dados_novos
            )
            VALUES (
                :usuario_id,
                :acao,
                :modulo,
                :entidade,
                :entidade_id,
                :ip,
                :user_agent,
                CAST(:dados_anteriores AS JSONB),
                CAST(:dados_novos AS JSONB)
            )
            '
        );

        $statement->execute([
            'usuario_id' => $usuarioId,
            'acao' => $acao,
            'modulo' => $modulo,
            'entidade' => $entidade,
            'entidade_id' => $entidadeId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'dados_anteriores' => $dadosAnterioresJson,
            'dados_novos' => $dadosNovosJson,
        ]);
    }
}
