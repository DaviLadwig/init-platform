<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\AuditLogRepository;
use App\Repositories\PlanoLimiteRepository;
use App\Repositories\PlanoRepository;
use PDOException;
use Throwable;

final class PlanoLimiteService
{
    public function __construct(
        private readonly PlanoRepository $planos,
        private readonly PlanoLimiteRepository $limites,
        private readonly AuditLogRepository $auditoria
    ) {}

    public function visualizar(
        int $planoId
    ): ?array {
        $plano = $this->planos->findById(
            $planoId
        );

        if ($plano === null) {
            return null;
        }

        return [
            'plano' => $plano,

            'limites' =>
            $this->limites->findByPlanoId(
                $planoId
            ),
        ];
    }

    /**
     * Busca somente o plano para telas auxiliares.
     */
    public function buscarPlano(
        int $planoId
    ): ?array {
        return $this->planos->findById(
            $planoId
        );
    }

    /**
     * Cadastra um limite no plano.
     */
    public function cadastrar(
        int $planoId,
        array $input,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $plano = $this->planos->findById(
            $planoId
        );

        if ($plano === null) {
            return [
                'success' => false,
                'not_found' => true,
                'errors' => [],
                'data' => [],
            ];
        }

        $data = $this->normalize(
            $input
        );

        $errors = $this->validate(
            $data
        );


        if (
            !isset($errors['chave'])
            && $this->limites
            ->existsByPlanoAndChave(
                $planoId,
                $data['chave']
            )
        ) {
            $errors['chave'] =
                'Este limite já está configurado neste plano.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'not_found' => false,
                'errors' => $errors,
                'data' => $data,
            ];
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $limiteId = $this->limites->create(
                $planoId,
                $data['chave'],
                $data['valor'],
                $data['unidade'] !== ''
                    ? $data['unidade']
                    : null
            );

            $this->auditoria->create(
                $usuarioId,
                'LIMITE_PLANO_CRIADO',
                'planos',
                'plano_limite',
                $limiteId,
                $ip,
                $userAgent,
                null,
                [
                    'plano_id' => $planoId,
                    'chave' => $data['chave'],
                    'valor' => $data['valor'],
                    'unidade' => $data['unidade'],
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'not_found' => false,
                'errors' => [],
                'data' => $data,
                'limite_id' => $limiteId,
            ];
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $sqlState =
                $exception->errorInfo[0]
                ?? $exception->getCode();

            /*
             * UNIQUE.
             */
            if ($sqlState === '23505') {
                return [
                    'success' => false,
                    'not_found' => false,

                    'errors' => [
                        'chave' =>
                        'Este limite já está configurado neste plano.',
                    ],

                    'data' => $data,
                ];
            }

            /*
             * CHECK constraint.
             *
             * O PostgreSQL continua sendo a última
             * barreira mesmo após nossa validação.
             */
            if ($sqlState === '23514') {
                return [
                    'success' => false,
                    'not_found' => false,

                    'errors' => [
                        'general' =>
                        'Os dados do limite não atendem às regras permitidas.',
                    ],

                    'data' => $data,
                ];
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function normalize(
        array $input
    ): array {
        $chave = isset($input['chave'])
            && is_string($input['chave'])
            ? mb_strtolower(
                trim($input['chave']),
                'UTF-8'
            )
            : '';

        $valor = isset($input['valor'])
            && is_string($input['valor'])
            ? trim($input['valor'])
            : '';

        $unidade = isset($input['unidade'])
            && is_string($input['unidade'])
            ? trim($input['unidade'])
            : '';

        /*
         * Permite digitar:
         * 100
         * 100,50
         * 100.50
         */
        $valor = str_replace(
            ',',
            '.',
            $valor
        );

        return [
            'chave' => $chave,
            'valor' => $valor,
            'unidade' => $unidade,
        ];
    }

    private function validate(
        array $data
    ): array {
        $errors = [];

        /*
         * Chave técnica.
         *
         * Exemplos:
         * max_usuarios
         * max_colaboradores
         * max_storage_gb
         */
        if ($data['chave'] === '') {
            $errors['chave'] =
                'Informe a chave do limite.';
        } elseif (
            mb_strlen(
                $data['chave'],
                'UTF-8'
            ) > 80
        ) {
            $errors['chave'] =
                'A chave deve possuir no máximo 80 caracteres.';
        } elseif (
            preg_match(
                '/^[a-z][a-z0-9_]*$/',
                $data['chave']
            ) !== 1
        ) {
            $errors['chave'] =
                'Use letras minúsculas, números e underline.';
        }

        /*
         * Valor numérico.
         *
         * Mantemos string até o PostgreSQL para
         * não introduzir imprecisão de float.
         */
        if ($data['valor'] === '') {
            $errors['valor'] =
                'Informe o valor do limite.';
        } elseif (
            preg_match(
                '/^\d+(?:\.\d{1,4})?$/',
                $data['valor']
            ) !== 1
        ) {
            $errors['valor'] =
                'Informe um valor numérico válido.';
        }
        if (
            mb_strlen(
                $data['unidade'],
                'UTF-8'
            ) > 50
        ) {
            $errors['unidade'] =
                'A unidade deve possuir no máximo 50 caracteres.';
        }

        return $errors;
    }

    /**
     * Busca um limite pertencente ao plano.
     */
    public function buscarLimite(
        int $planoId,
        int $limiteId
    ): ?array {
        return $this->limites
            ->findByIdAndPlanoId(
                $limiteId,
                $planoId
            );
    }

    /**
     * Edita um limite do plano.
     */
    public function editar(
        int $planoId,
        int $limiteId,
        array $input,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        /*
     * O plano precisa existir.
     */
        $plano = $this->planos->findById(
            $planoId
        );

        if ($plano === null) {
            return [
                'success' => false,
                'not_found' => true,
                'errors' => [],
                'data' => [],
            ];
        }

        /*
     * O limite precisa existir E pertencer
     * ao plano recebido na rota.
     *
     * Proteção contra IDOR.
     */
        $limiteAtual = $this->limites
            ->findByIdAndPlanoId(
                $limiteId,
                $planoId
            );

        if ($limiteAtual === null) {
            return [
                'success' => false,
                'not_found' => true,
                'errors' => [],
                'data' => [],
            ];
        }

        $data = $this->normalize(
            $input
        );

        $errors = $this->validate(
            $data
        );

        if (
            !isset($errors['chave'])
            && $this->limites
            ->existsByPlanoAndChaveExceptId(
                $planoId,
                $data['chave'],
                $limiteId
            )
        ) {
            $errors['chave'] =
                'Este limite já está configurado neste plano.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'not_found' => false,
                'errors' => $errors,
                'data' => $data,
            ];
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            /*
         * Revalidação dentro da transação.
         *
         * Evita trabalhar somente com o estado
         * consultado antes da alteração.
         */
            $limiteAtual = $this->limites
                ->findByIdAndPlanoId(
                    $limiteId,
                    $planoId
                );

            if ($limiteAtual === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'errors' => [],
                    'data' => [],
                ];
            }

            $this->limites->update(
                $limiteId,
                $planoId,
                $data['chave'],
                $data['valor'],
                $data['unidade'] !== ''
                    ? $data['unidade']
                    : null
            );

            /*
         * Auditoria explícita.
         *
         * Nunca enviamos $_POST inteiro.
         */
            $this->auditoria->create(
                $usuarioId,
                'LIMITE_PLANO_EDITADO',
                'planos',
                'plano_limite',
                $limiteId,
                $ip,
                $userAgent,

                [
                    'plano_id' =>
                    $planoId,

                    'chave' =>
                    (string) $limiteAtual['chave'],

                    'valor' =>
                    (string) $limiteAtual['valor'],

                    'unidade' =>
                    is_string(
                        $limiteAtual['unidade']
                            ?? null
                    )
                        ? $limiteAtual['unidade']
                        : '',
                ],

                [
                    'plano_id' =>
                    $planoId,

                    'chave' =>
                    $data['chave'],

                    'valor' =>
                    $data['valor'],

                    'unidade' =>
                    $data['unidade'],
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'not_found' => false,
                'errors' => [],
                'data' => $data,
            ];
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $sqlState =
                $exception->errorInfo[0]
                ?? $exception->getCode();

            if ($sqlState === '23505') {
                return [
                    'success' => false,
                    'not_found' => false,

                    'errors' => [
                        'chave' =>
                        'Este limite já está configurado neste plano.',
                    ],

                    'data' => $data,
                ];
            }

            if ($sqlState === '23514') {
                return [
                    'success' => false,
                    'not_found' => false,

                    'errors' => [
                        'general' =>
                        'Os dados informados não atendem às regras permitidas para limites.',
                    ],

                    'data' => $data,
                ];
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Remove um limite do plano.
     */
    public function remover(
        int $planoId,
        int $limiteId,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        /*
     * Primeiro validamos o plano.
     */
        $plano = $this->planos->findById(
            $planoId
        );

        if ($plano === null) {
            return [
                'success' => false,
                'not_found' => true,
                'message' => null,
            ];
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            /*
         * A própria exclusão verifica:
         *
         * limite.id
         * +
         * limite.plano_id
         *
         * Isso mantém a proteção contra IDOR
         * até o momento exato da escrita.
         */
            $limiteRemovido =
                $this->limites->deleteReturning(
                    $limiteId,
                    $planoId
                );

            if ($limiteRemovido === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'message' => null,
                ];
            }

            /*
         * Auditoria explícita.
         *
         * Nunca gravamos $_POST inteiro.
         */
            $this->auditoria->create(
                $usuarioId,
                'LIMITE_PLANO_REMOVIDO',
                'planos',
                'plano_limite',
                $limiteId,
                $ip,
                $userAgent,

                [
                    'plano_id' =>
                    (int) $limiteRemovido['plano_id'],

                    'chave' =>
                    (string) $limiteRemovido['chave'],

                    'valor' =>
                    (string) $limiteRemovido['valor'],

                    'unidade' =>
                    is_string(
                        $limiteRemovido['unidade']
                            ?? null
                    )
                        ? $limiteRemovido['unidade']
                        : '',
                ],

                null
            );

            $pdo->commit();

            return [
                'success' => true,
                'not_found' => false,
                'message' =>
                'Limite removido com sucesso.',
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
