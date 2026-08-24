<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClienteRepository;
use PDOException;
use Throwable;

final class ClienteService
{
    public function __construct(
        private readonly ClienteRepository $clientes,
        private readonly AuditLogRepository $auditoria
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
     * Cadastra uma empresa contratante.
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

        /*
         * Validações amigáveis.
         *
         * As constraints UNIQUE do PostgreSQL
         * continuam sendo a defesa definitiva.
         */
        if (
            !isset($errors['cnpj'])
            && $this->clientes->existsByCnpj(
                $data['cnpj']
            )
        ) {
            $errors['cnpj'] =
                'Já existe um cliente cadastrado com este CNPJ.';
        }

        if (
            !isset($errors['slug'])
            && $this->clientes->existsBySlug(
                $data['slug']
            )
        ) {
            $errors['slug'] =
                'Já existe um cliente utilizando este slug.';
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

            $clienteId = $this->clientes->create(
                $data['razao_social'],

                $data['nome_fantasia'] !== ''
                    ? $data['nome_fantasia']
                    : null,

                $data['cnpj'],

                $data['email'] !== ''
                    ? $data['email']
                    : null,

                $data['telefone'] !== ''
                    ? $data['telefone']
                    : null,

                $data['slug']
            );

            /*
             * Auditoria explícita.
             *
             * Não enviamos $_POST inteiro.
             */
            $this->auditoria->create(
                $usuarioId,
                'CLIENTE_CRIADO',
                'clientes',
                'empresa',
                $clienteId,
                $ip,
                $userAgent,
                null,
                [
                    'razao_social' =>
                    $data['razao_social'],

                    'nome_fantasia' =>
                    $data['nome_fantasia'],

                    'cnpj' =>
                    $data['cnpj'],

                    'email' =>
                    $data['email'],

                    'telefone' =>
                    $data['telefone'],

                    'slug' =>
                    $data['slug'],

                    'status' =>
                    'ATIVA',
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'errors' => [],
                'data' => $data,
                'cliente_id' => $clienteId,
            ];
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $sqlState =
                $exception->errorInfo[0]
                ?? $exception->getCode();

            /*
             * UNIQUE violation.
             */
            if ($sqlState === '23505') {
                return [
                    'success' => false,

                    'errors' => [
                        'general' =>
                        'Já existe um cliente utilizando o CNPJ ou slug informado.',
                    ],

                    'data' => $data,
                ];
            }

            /*
             * CHECK violation.
             */
            if ($sqlState === '23514') {
                return [
                    'success' => false,

                    'errors' => [
                        'general' =>
                        'Os dados informados não atendem às regras permitidas para clientes.',
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
        $razaoSocial =
            isset($input['razao_social'])
            && is_string($input['razao_social'])
            ? trim($input['razao_social'])
            : '';

        $nomeFantasia =
            isset($input['nome_fantasia'])
            && is_string($input['nome_fantasia'])
            ? trim($input['nome_fantasia'])
            : '';

        /*
         * CNPJ armazenado somente com números.
         */
        $cnpj =
            isset($input['cnpj'])
            && is_string($input['cnpj'])
            ? preg_replace(
                '/\D+/',
                '',
                $input['cnpj']
            )
            : '';

        if (!is_string($cnpj)) {
            $cnpj = '';
        }

        $email =
            isset($input['email'])
            && is_string($input['email'])
            ? mb_strtolower(
                trim($input['email']),
                'UTF-8'
            )
            : '';

        /*
         * Telefone armazenado apenas com números.
         */
        $telefone =
            isset($input['telefone'])
            && is_string($input['telefone'])
            ? preg_replace(
                '/\D+/',
                '',
                $input['telefone']
            )
            : '';

        if (!is_string($telefone)) {
            $telefone = '';
        }

        $slug =
            isset($input['slug'])
            && is_string($input['slug'])
            ? mb_strtolower(
                trim($input['slug']),
                'UTF-8'
            )
            : '';

        return [
            'razao_social' => $razaoSocial,
            'nome_fantasia' => $nomeFantasia,
            'cnpj' => $cnpj,
            'email' => $email,
            'telefone' => $telefone,
            'slug' => $slug,
        ];
    }

    private function validate(
        array $data
    ): array {
        $errors = [];

        /*
         * Razão social.
         */
        if ($data['razao_social'] === '') {
            $errors['razao_social'] =
                'Informe a razão social.';
        } elseif (
            mb_strlen(
                $data['razao_social'],
                'UTF-8'
            ) > 200
        ) {
            $errors['razao_social'] =
                'A razão social deve possuir no máximo 200 caracteres.';
        }

        /*
         * Nome fantasia.
         */
        if (
            mb_strlen(
                $data['nome_fantasia'],
                'UTF-8'
            ) > 200
        ) {
            $errors['nome_fantasia'] =
                'O nome fantasia deve possuir no máximo 200 caracteres.';
        }

        /*
         * CNPJ.
         */
        if ($data['cnpj'] === '') {
            $errors['cnpj'] =
                'Informe o CNPJ.';
        } elseif (
            strlen($data['cnpj']) !== 14
        ) {
            $errors['cnpj'] =
                'O CNPJ deve possuir 14 dígitos.';
        } elseif (
            !$this->isValidCnpj(
                $data['cnpj']
            )
        ) {
            $errors['cnpj'] =
                'Informe um CNPJ válido.';
        }

        /*
         * E-mail.
         */
        if ($data['email'] !== '') {
            if (
                mb_strlen(
                    $data['email'],
                    'UTF-8'
                ) > 255
            ) {
                $errors['email'] =
                    'O e-mail deve possuir no máximo 255 caracteres.';
            } elseif (
                filter_var(
                    $data['email'],
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                $errors['email'] =
                    'Informe um endereço de e-mail válido.';
            }
        }

        /*
         * Telefone.
         *
         * Para o cadastro atual consideramos
         * telefones brasileiros com 10 ou 11 dígitos.
         */
        if ($data['telefone'] !== '') {
            $length = strlen(
                $data['telefone']
            );

            if (
                $length < 10
                || $length > 11
            ) {
                $errors['telefone'] =
                    'Informe um telefone com DDD válido.';
            }
        }

        /*
         * Slug.
         */
        if ($data['slug'] === '') {
            $errors['slug'] =
                'Informe o slug do cliente.';
        } elseif (
            mb_strlen(
                $data['slug'],
                'UTF-8'
            ) > 150
        ) {
            $errors['slug'] =
                'O slug deve possuir no máximo 150 caracteres.';
        } elseif (
            preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $data['slug']
            ) !== 1
        ) {
            $errors['slug'] =
                'Use apenas letras minúsculas, números e hífens.';
        }

        return $errors;
    }

    /**
     * Valida os dígitos verificadores do CNPJ.
     */
    private function isValidCnpj(
        string $cnpj
    ): bool {
        if (
            preg_match(
                '/^\d{14}$/',
                $cnpj
            ) !== 1
        ) {
            return false;
        }

        /*
         * Rejeita sequências repetidas:
         * 00000000000000, 11111111111111 etc.
         */
        if (
            preg_match(
                '/^(\d)\1{13}$/',
                $cnpj
            ) === 1
        ) {
            return false;
        }

        $calcularDigito = static function (
            string $base,
            array $pesos
        ): int {
            $soma = 0;

            foreach ($pesos as $index => $peso) {
                $soma +=
                    ((int) $base[$index])
                    * $peso;
            }

            $resto = $soma % 11;

            return $resto < 2
                ? 0
                : 11 - $resto;
        };

        $primeiroDigito =
            $calcularDigito(
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
            $primeiroDigito
            !== (int) $cnpj[12]
        ) {
            return false;
        }

        $segundoDigito =
            $calcularDigito(
                substr(
                    $cnpj,
                    0,
                    13
                ),
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

        return $segundoDigito
            === (int) $cnpj[13];
    }

    /**
     * Situação resumida da empresa.
     *
     * Não substitui o status real das assinaturas.
     */
    private function resolverSituacao(
        array $cliente
    ): string {
        if (
            isset($cliente['status'])
            && $cliente['status'] === 'INATIVA'
        ) {
            return 'INATIVO';
        }

        $suspensas =
            (int) (
                $cliente['assinaturas_suspensas']
                ?? 0
            );

        $atrasadas =
            (int) (
                $cliente['assinaturas_atrasadas']
                ?? 0
            );

        $ativas =
            (int) (
                $cliente['assinaturas_ativas']
                ?? 0
            );

        $trial =
            (int) (
                $cliente['assinaturas_trial']
                ?? 0
            );

        $pendentes =
            (int) (
                $cliente['assinaturas_pendentes']
                ?? 0
            );

        $total =
            (int) (
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

    public function buscar(
        int $id
    ): ?array {
        return $this->clientes->findById(
            $id
        );
    }

    /**
     * Edita os dados cadastrais do cliente.
     */
    public function editar(
        int $clienteId,
        array $input,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $clienteAtual =
            $this->clientes->findById(
                $clienteId
            );

        if ($clienteAtual === null) {
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
            !isset($errors['cnpj'])
            && $this->clientes
            ->existsByCnpjExceptId(
                $data['cnpj'],
                $clienteId
            )
        ) {
            $errors['cnpj'] =
                'Já existe outro cliente cadastrado com este CNPJ.';
        }

        if (
            !isset($errors['slug'])
            && $this->clientes
            ->existsBySlugExceptId(
                $data['slug'],
                $clienteId
            )
        ) {
            $errors['slug'] =
                'Já existe outro cliente utilizando este slug.';
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
         * Revalida o registro dentro
         * da transação.
         */
            $clienteAtual =
                $this->clientes->findById(
                    $clienteId
                );

            if ($clienteAtual === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'errors' => [],
                    'data' => [],
                ];
            }

            $this->clientes->update(
                $clienteId,
                $data['razao_social'],

                $data['nome_fantasia'] !== ''
                    ? $data['nome_fantasia']
                    : null,

                $data['cnpj'],

                $data['email'] !== ''
                    ? $data['email']
                    : null,

                $data['telefone'] !== ''
                    ? $data['telefone']
                    : null,

                $data['slug']
            );

            /*
         * Auditoria com campos explícitos.
         *
         * Status não aparece em dados_novos
         * porque esta operação não o altera.
         */
            $this->auditoria->create(
                $usuarioId,
                'CLIENTE_EDITADO',
                'clientes',
                'empresa',
                $clienteId,
                $ip,
                $userAgent,

                [
                    'razao_social' =>
                    (string) $clienteAtual['razao_social'],

                    'nome_fantasia' =>
                    is_string(
                        $clienteAtual['nome_fantasia'] ?? null
                    )
                        ? $clienteAtual['nome_fantasia']
                        : '',

                    'cnpj' =>
                    (string) $clienteAtual['cnpj'],

                    'email' =>
                    is_string(
                        $clienteAtual['email']
                            ?? null
                    )
                        ? $clienteAtual['email']
                        : '',

                    'telefone' =>
                    is_string(
                        $clienteAtual['telefone']
                            ?? null
                    )
                        ? $clienteAtual['telefone']
                        : '',

                    'slug' =>
                    (string) $clienteAtual['slug'],
                ],

                [
                    'razao_social' =>
                    $data['razao_social'],

                    'nome_fantasia' =>
                    $data['nome_fantasia'],

                    'cnpj' =>
                    $data['cnpj'],

                    'email' =>
                    $data['email'],

                    'telefone' =>
                    $data['telefone'],

                    'slug' =>
                    $data['slug'],
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
                        'Já existe outro cliente utilizando o CNPJ ou slug informado.',
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
                        'Os dados informados não atendem às regras permitidas para clientes.',
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
}
