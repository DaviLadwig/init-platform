<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\AuditLogRepository;
use App\Repositories\ProdutoRepository;
use PDOException;
use Throwable;

final class ProdutoService
{
    public function __construct(
        private readonly ProdutoRepository $produtos,
        private readonly AuditLogRepository $auditoria
    ) {}

    /**
     * Lista todos os produtos.
     */
    public function listar(): array
    {
        return $this->produtos->all();
    }

    /**
     * Busca um produto pelo ID.
     */
    public function buscar(int $id): ?array
    {
        return $this->produtos->findById($id);
    }

    /**
     * Cadastra um novo produto.
     *
     * @return array{
     *     success: bool,
     *     errors: array<string, string>,
     *     data: array<string, string>,
     *     produto_id?: int
     * }
     */
    public function cadastrar(
        array $input,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $data = $this->normalize($input);

        $errors = $this->validate($data);

        /*
         * Validações amigáveis antes do INSERT.
         *
         * O UNIQUE do PostgreSQL continua sendo
         * a proteção definitiva contra concorrência.
         */
        if (
            !isset($errors['codigo'])
            && $this->produtos->existsByCodigo(
                $data['codigo']
            )
        ) {
            $errors['codigo'] =
                'Já existe um produto com este código.';
        }

        if (
            !isset($errors['slug'])
            && $this->produtos->existsBySlug(
                $data['slug']
            )
        ) {
            $errors['slug'] =
                'Já existe um produto com este slug.';
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

            $produtoId = $this->produtos->create(
                $data['codigo'],
                $data['nome'],
                $data['slug'],
                $data['descricao'] !== ''
                    ? $data['descricao']
                    : null
            );

            /*
             * Auditoria dentro da mesma transação.
             *
             * Se a auditoria falhar, o produto
             * também não será persistido.
             */
            $this->auditoria->create(
                $usuarioId,
                'PRODUTO_CRIADO',
                'produtos',
                'produto',
                $produtoId,
                $ip,
                $userAgent,
                null,
                [
                    'codigo' =>
                    $data['codigo'],

                    'nome' =>
                    $data['nome'],

                    'slug' =>
                    $data['slug'],

                    'descricao' =>
                    $data['descricao'],

                    'ativo' =>
                    true,
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'errors' => [],
                'data' => $data,
                'produto_id' => $produtoId,
            ];
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($this->isUniqueViolation($exception)) {
                return [
                    'success' => false,

                    'errors' => [
                        'general' =>
                        'Já existe um produto com o código ou slug informado.',
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
     * Edita um produto existente.
     *
     * @return array{
     *     success: bool,
     *     not_found: bool,
     *     errors: array<string, string>,
     *     data: array<string, string>
     * }
     */
    public function editar(
        int $id,
        array $input,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $produtoAtual = $this->produtos->findById(
            $id
        );

        if ($produtoAtual === null) {
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

        /*
         * Na edição ignoramos o próprio ID.
         */
        if (
            !isset($errors['codigo'])
            && $this->produtos->existsByCodigoExceptId(
                $data['codigo'],
                $id
            )
        ) {
            $errors['codigo'] =
                'Já existe outro produto com este código.';
        }

        if (
            !isset($errors['slug'])
            && $this->produtos->existsBySlugExceptId(
                $data['slug'],
                $id
            )
        ) {
            $errors['slug'] =
                'Já existe outro produto com este slug.';
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

            $this->produtos->update(
                $id,
                $data['codigo'],
                $data['nome'],
                $data['slug'],
                $data['descricao'] !== ''
                    ? $data['descricao']
                    : null
            );

            /*
             * Auditoria antes/depois.
             */
            $this->auditoria->create(
                $usuarioId,
                'PRODUTO_EDITADO',
                'produtos',
                'produto',
                $id,
                $ip,
                $userAgent,
                [
                    'codigo' =>
                    (string) ($produtoAtual['codigo'] ?? ''),

                    'nome' =>
                    (string) ($produtoAtual['nome'] ?? ''),

                    'slug' =>
                    (string) ($produtoAtual['slug'] ?? ''),

                    'descricao' =>
                    is_string(
                        $produtoAtual['descricao'] ?? null
                    )
                        ? $produtoAtual['descricao']
                        : '',

                    'ativo' =>
                    $this->toBool(
                        $produtoAtual['ativo'] ?? false
                    ),
                ],
                [
                    'codigo' =>
                    $data['codigo'],

                    'nome' =>
                    $data['nome'],

                    'slug' =>
                    $data['slug'],

                    'descricao' =>
                    $data['descricao'],

                    /*
                     * Editar dados do produto não altera
                     * seu status.
                     */
                    'ativo' =>
                    $this->toBool(
                        $produtoAtual['ativo'] ?? false
                    ),
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

            if ($this->isUniqueViolation($exception)) {
                return [
                    'success' => false,
                    'not_found' => false,

                    'errors' => [
                        'general' =>
                        'Já existe outro produto com o código ou slug informado.',
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
     * Normaliza dados recebidos do formulário.
     *
     * @return array{
     *     codigo: string,
     *     nome: string,
     *     slug: string,
     *     descricao: string
     * }
     */
    private function normalize(
        array $input
    ): array {
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

        $slug = isset($input['slug'])
            && is_string($input['slug'])
            ? mb_strtolower(
                trim($input['slug']),
                'UTF-8'
            )
            : '';

        $descricao = isset($input['descricao'])
            && is_string($input['descricao'])
            ? trim($input['descricao'])
            : '';

        return [
            'codigo' => $codigo,
            'nome' => $nome,
            'slug' => $slug,
            'descricao' => $descricao,
        ];
    }

    /**
     * Validação principal do produto.
     *
     * @param array{
     *     codigo: string,
     *     nome: string,
     *     slug: string,
     *     descricao: string
     * } $data
     *
     * @return array<string, string>
     */
    private function validate(
        array $data
    ): array {
        $errors = [];

        /*
         * Código
         */
        if ($data['codigo'] === '') {
            $errors['codigo'] =
                'Informe o código do produto.';
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

        /*
         * Nome
         */
        if ($data['nome'] === '') {
            $errors['nome'] =
                'Informe o nome do produto.';
        } elseif (
            mb_strlen(
                $data['nome'],
                'UTF-8'
            ) > 150
        ) {
            $errors['nome'] =
                'O nome deve possuir no máximo 150 caracteres.';
        }

        /*
         * Slug
         */
        if ($data['slug'] === '') {
            $errors['slug'] =
                'Informe o slug do produto.';
        } elseif (
            mb_strlen(
                $data['slug'],
                'UTF-8'
            ) > 100
        ) {
            $errors['slug'] =
                'O slug deve possuir no máximo 100 caracteres.';
        } elseif (
            preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $data['slug']
            ) !== 1
        ) {
            $errors['slug'] =
                'Use letras minúsculas, números e hífens.';
        }

        /*
         * Descrição
         */
        if (
            mb_strlen(
                $data['descricao'],
                'UTF-8'
            ) > 2000
        ) {
            $errors['descricao'] =
                'A descrição deve possuir no máximo 2000 caracteres.';
        }

        return $errors;
    }

    /**
     * Identifica violação UNIQUE do PostgreSQL.
     */
    private function isUniqueViolation(
        PDOException $exception
    ): bool {
        $sqlState = $exception->errorInfo[0]
            ?? $exception->getCode();

        return $sqlState === '23505';
    }

    /**
     * Conversão defensiva de boolean vindo do PostgreSQL.
     */
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

    /**
     * Ativa ou desativa um produto.
     *
     * A regra crítica fica no backend:
     * produtos com assinaturas vigentes não podem
     * ser desativados.
     */
    public function alterarStatus(
        int $id,
        bool $novoStatus,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $produto = $this->produtos->findById(
            $id
        );

        if ($produto === null) {
            return [
                'success' => false,
                'not_found' => true,
                'message' => null,
            ];
        }

        $statusAtual = $this->toBool(
            $produto['ativo'] ?? false
        );

        /*
     * Evita operação desnecessária.
     */
        if ($statusAtual === $novoStatus) {
            return [
                'success' => true,
                'not_found' => false,
                'message' =>
                $novoStatus
                    ? 'O produto já está ativo.'
                    : 'O produto já está inativo.',
            ];
        }

        /*
     * REGRA DE NEGÓCIO:
     *
     * não podemos desativar um produto que
     * ainda possua contratos vigentes.
     */
        if ($novoStatus === false) {
            $assinaturasVigentes =
                $this->produtos
                ->countAssinaturasVigentes(
                    $id
                );

            if ($assinaturasVigentes > 0) {
                return [
                    'success' => false,
                    'not_found' => false,

                    'message' =>
                    'Este produto possui assinaturas vigentes e não pode ser desativado.',
                ];
            }
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $this->produtos->updateStatus(
                $id,
                $novoStatus
            );

            $this->auditoria->create(
                $usuarioId,

                $novoStatus
                    ? 'PRODUTO_ATIVADO'
                    : 'PRODUTO_DESATIVADO',

                'produtos',
                'produto',
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
                    ? 'Produto ativado com sucesso.'
                    : 'Produto desativado com sucesso.',
            ];
        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
