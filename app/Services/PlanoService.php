<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\AuditLogRepository;
use App\Repositories\PlanoRepository;
use PDOException;
use Throwable;

final class PlanoService
{
    private const PERIODICIDADES = [
        'MENSAL',
        'TRIMESTRAL',
        'SEMESTRAL',
        'ANUAL',
    ];

    public function __construct(
        private readonly PlanoRepository $planos,
        private readonly AuditLogRepository $auditoria
    ) {}

    public function listar(): array
    {
        $rows = $this->planos->catalogo();

        $catalogo = [];

        foreach ($rows as $row) {
            $produtoId = isset($row['produto_id'])
                ? (int) $row['produto_id']
                : 0;

            if ($produtoId <= 0) {
                continue;
            }

            if (!isset($catalogo[$produtoId])) {
                $catalogo[$produtoId] = [
                    'id' => $produtoId,

                    'codigo' =>
                    is_string($row['produto_codigo'] ?? null)
                        ? $row['produto_codigo']
                        : '',

                    'nome' =>
                    is_string($row['produto_nome'] ?? null)
                        ? $row['produto_nome']
                        : '',

                    'slug' =>
                    is_string($row['produto_slug'] ?? null)
                        ? $row['produto_slug']
                        : '',

                    'descricao' =>
                    is_string($row['produto_descricao'] ?? null)
                        ? $row['produto_descricao']
                        : '',

                    'ativo' =>
                    isset($row['produto_ativo'])
                        && (int) $row['produto_ativo'] === 1,

                    'planos' => [],
                ];
            }

            $planoId = isset($row['plano_id'])
                ? (int) $row['plano_id']
                : 0;

            /*
         * Produto sem plano continua no catálogo.
         */
            if ($planoId <= 0) {
                continue;
            }

            $catalogo[$produtoId]['planos'][] = [
                'id' => $planoId,

                'codigo' =>
                is_string($row['plano_codigo'] ?? null)
                    ? $row['plano_codigo']
                    : '',

                'nome' =>
                is_string($row['plano_nome'] ?? null)
                    ? $row['plano_nome']
                    : '',

                'descricao' =>
                is_string($row['plano_descricao'] ?? null)
                    ? $row['plano_descricao']
                    : '',

                'valor' =>
                is_numeric($row['plano_valor'] ?? null)
                    ? (string) $row['plano_valor']
                    : '0',

                'moeda' =>
                is_string($row['plano_moeda'] ?? null)
                    ? $row['plano_moeda']
                    : 'BRL',

                'periodicidade' =>
                is_string(
                    $row['plano_periodicidade']
                        ?? null
                )
                    ? $row['plano_periodicidade']
                    : '',

                'ativo' =>
                isset($row['plano_ativo'])
                    && (int) $row['plano_ativo'] === 1,
            ];
        }

        return array_values(
            $catalogo
        );
    }

    public function produtosAtivos(): array
    {
        return $this->planos->activeProducts();
    }

    /**
     * Cadastra novo plano.
     */
    public function cadastrar(
        array $input,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $data = $this->normalize(
            $input
        );

        $errors = $this->validate(
            $data
        );

        $produtoId = (int) $data['produto_id'];

        /*
         * O select da interface não é confiável.
         */
        if (
            !isset($errors['produto_id'])
            && !$this->planos->activeProductExists(
                $produtoId
            )
        ) {
            $errors['produto_id'] =
                'Selecione um produto ativo e válido.';
        }

        /*
         * Código é único somente dentro do produto.
         *
         * Exemplo:
         * INIT_RH / PROFESSIONAL
         * INIT_CLINIC / PROFESSIONAL
         *
         * são permitidos.
         */
        if (
            !isset($errors['codigo'])
            && !isset($errors['produto_id'])
            && $this->planos->existsByCodigoAndProduto(
                $data['codigo'],
                $produtoId
            )
        ) {
            $errors['codigo'] =
                'Já existe um plano com este código para o produto selecionado.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
                'data' => $data,
            ];
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $planoId = $this->planos->create(
                $produtoId,
                $data['codigo'],
                $data['nome'],
                $data['descricao'] !== ''
                    ? $data['descricao']
                    : null,
                $data['valor'],
                'BRL',
                $data['periodicidade']
            );

            $this->auditoria->create(
                $usuarioId,
                'PLANO_CRIADO',
                'planos',
                'plano',
                $planoId,
                $ip,
                $userAgent,
                null,
                [
                    'produto_id' => $produtoId,
                    'codigo' => $data['codigo'],
                    'nome' => $data['nome'],
                    'descricao' => $data['descricao'],
                    'valor' => $data['valor'],
                    'moeda' => 'BRL',
                    'periodicidade' =>
                    $data['periodicidade'],
                    'ativo' => true,
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'errors' => [],
                'data' => $data,
                'plano_id' => $planoId,
            ];
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $sqlState = $exception->errorInfo[0]
                ?? $exception->getCode();

            if ($sqlState === '23505') {
                return [
                    'success' => false,
                    'errors' => [
                        'general' =>
                        'Já existe um plano com este código para o produto selecionado.',
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
        $produtoId = isset($input['produto_id'])
            && is_string($input['produto_id'])
            ? trim($input['produto_id'])
            : '';

        $codigo = isset($input['codigo'])
            && is_string($input['codigo'])
            ? mb_strtoupper(
                trim($input['codigo']),
                'UTF-8'
            )
            : '';

        $nome = isset($input['nome'])
            && is_string($input['nome'])
            ? trim($input['nome'])
            : '';

        $descricao = isset($input['descricao'])
            && is_string($input['descricao'])
            ? trim($input['descricao'])
            : '';

        $periodicidade =
            isset($input['periodicidade'])
            && is_string($input['periodicidade'])
            ? mb_strtoupper(
                trim($input['periodicidade']),
                'UTF-8'
            )
            : '';

        $valor = isset($input['valor'])
            && is_string($input['valor'])
            ? trim($input['valor'])
            : '';

        /*
         * Aceita:
         * 199,90
         * 199.90
         *
         * Internamente envia 199.90 ao PostgreSQL.
         */
        $valor = str_replace(
            ',',
            '.',
            $valor
        );

        return [
            'produto_id' => $produtoId,
            'codigo' => $codigo,
            'nome' => $nome,
            'descricao' => $descricao,
            'valor' => $valor,
            'periodicidade' => $periodicidade,
        ];
    }

    private function validate(
        array $data
    ): array {
        $errors = [];

        if (
            $data['produto_id'] === ''
            || !ctype_digit($data['produto_id'])
            || (int) $data['produto_id'] <= 0
        ) {
            $errors['produto_id'] =
                'Selecione o produto do plano.';
        }

        if ($data['codigo'] === '') {
            $errors['codigo'] =
                'Informe o código do plano.';
        } elseif (
            mb_strlen(
                $data['codigo'],
                'UTF-8'
            ) > 50
        ) {
            $errors['codigo'] =
                'O código deve possuir no máximo 50 caracteres.';
        } elseif (
            preg_match(
                '/^[A-Z0-9_]+$/',
                $data['codigo']
            ) !== 1
        ) {
            $errors['codigo'] =
                'Use apenas letras maiúsculas, números e underline.';
        }

        if ($data['nome'] === '') {
            $errors['nome'] =
                'Informe o nome do plano.';
        } elseif (
            mb_strlen(
                $data['nome'],
                'UTF-8'
            ) > 150
        ) {
            $errors['nome'] =
                'O nome deve possuir no máximo 150 caracteres.';
        }

        if (
            mb_strlen(
                $data['descricao'],
                'UTF-8'
            ) > 2000
        ) {
            $errors['descricao'] =
                'A descrição deve possuir no máximo 2000 caracteres.';
        }

        if ($data['valor'] === '') {
            $errors['valor'] =
                'Informe o valor do plano.';
        } elseif (
            preg_match(
                '/^\d{1,10}(?:\.\d{1,2})?$/',
                $data['valor']
            ) !== 1
        ) {
            $errors['valor'] =
                'Informe um valor monetário válido.';
        }

        if (
            !in_array(
                $data['periodicidade'],
                self::PERIODICIDADES,
                true
            )
        ) {
            $errors['periodicidade'] =
                'Selecione uma periodicidade válida.';
        }

        return $errors;
    }

    public function buscar(
        int $id
    ): ?array {
        return $this->planos->findById(
            $id
        );
    }
    /**
     * Edita um plano existente.
     */
    public function editar(
        int $id,
        array $input,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $planoAtual = $this->planos->findById(
            $id
        );

        if ($planoAtual === null) {
            return [
                'success' => false,
                'not_found' => true,
                'errors' => [],
                'data' => [],
            ];
        }

        /*
     * O produto vem do banco.
     *
     * Não aceitamos produto_id vindo do formulário
     * durante a edição.
     */
        $produtoId = (int) $planoAtual['produto_id'];

        $data = $this->normalize(
            $input
        );

        /*
     * Sobrescrevemos com o produto real.
     */
        $data['produto_id'] =
            (string) $produtoId;

        $errors = $this->validate(
            $data
        );

        if (
            !isset($errors['codigo'])
            && $this->planos
            ->existsByCodigoAndProdutoExceptId(
                $data['codigo'],
                $produtoId,
                $id
            )
        ) {
            $errors['codigo'] =
                'Já existe outro plano com este código neste produto.';
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

            $this->planos->update(
                $id,
                $data['codigo'],
                $data['nome'],
                $data['descricao'] !== ''
                    ? $data['descricao']
                    : null,
                $data['valor'],
                $data['periodicidade']
            );

            /*
         * Auditoria antes/depois.
         */
            $this->auditoria->create(
                $usuarioId,
                'PLANO_EDITADO',
                'planos',
                'plano',
                $id,
                $ip,
                $userAgent,

                [
                    'produto_id' =>
                    $produtoId,

                    'codigo' =>
                    (string) $planoAtual['codigo'],

                    'nome' =>
                    (string) $planoAtual['nome'],

                    'descricao' =>
                    is_string(
                        $planoAtual['descricao']
                            ?? null
                    )
                        ? $planoAtual['descricao']
                        : '',

                    'valor' =>
                    (string) $planoAtual['valor'],

                    'moeda' =>
                    (string) $planoAtual['moeda'],

                    'periodicidade' =>
                    (string) $planoAtual['periodicidade'],
                ],

                [
                    'produto_id' =>
                    $produtoId,

                    'codigo' =>
                    $data['codigo'],

                    'nome' =>
                    $data['nome'],

                    'descricao' =>
                    $data['descricao'],

                    'valor' =>
                    $data['valor'],

                    'moeda' =>
                    'BRL',

                    'periodicidade' =>
                    $data['periodicidade'],
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
                        'general' =>
                        'Já existe outro plano com este código neste produto.',
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
     * Ativa ou desativa a disponibilidade comercial do plano.
     */
    public function alterarStatus(
        int $id,
        bool $novoStatus,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $plano = $this->planos->findById(
            $id
        );

        if ($plano === null) {
            return [
                'success' => false,
                'not_found' => true,
                'message' => null,
            ];
        }

        $statusAtual = $this->toBool(
            $plano['ativo'] ?? false
        );

        if ($statusAtual === $novoStatus) {
            return [
                'success' => true,
                'not_found' => false,

                'message' =>
                $novoStatus
                    ? 'O plano já está ativo.'
                    : 'O plano já está inativo.',
            ];
        }

        /*
     * Um plano não pode ser ativado dentro
     * de um produto comercialmente inativo.
     */
        if ($novoStatus === true) {
            $produtoId = isset($plano['produto_id'])
                ? (int) $plano['produto_id']
                : 0;

            if (
                $produtoId <= 0
                || !$this->planos->activeProductExists(
                    $produtoId
                )
            ) {
                return [
                    'success' => false,
                    'not_found' => false,

                    'message' =>
                    'O produto deste plano está inativo. Ative o produto antes de ativar o plano.',
                ];
            }
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $this->planos->updateStatus(
                $id,
                $novoStatus
            );

            $this->auditoria->create(
                $usuarioId,

                $novoStatus
                    ? 'PLANO_ATIVADO'
                    : 'PLANO_DESATIVADO',

                'planos',
                'plano',
                $id,
                $ip,
                $userAgent,

                [
                    'ativo' => $statusAtual,
                ],

                [
                    'ativo' => $novoStatus,
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'not_found' => false,

                'message' =>
                $novoStatus
                    ? 'Plano ativado com sucesso.'
                    : 'Plano desativado com sucesso.',
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function toBool(
        mixed $value
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(
                strtolower($value),
                [
                    '1',
                    't',
                    'true',
                    'yes',
                    'on',
                ],
                true
            );
        }

        return false;
    }
}
