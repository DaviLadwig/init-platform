<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\EmpresaResponsavelRepository;
use PDOException;
use Throwable;

final class EmpresaResponsavelService
{
    public function __construct(
        private readonly ClienteRepository $clientes,
        private readonly EmpresaResponsavelRepository $responsaveis,
        private readonly AuditLogRepository $auditoria
    ) {}

    /**
     * Retorna empresa + responsáveis.
     */
    public function listar(
        int $empresaId
    ): ?array {
        $empresa = $this->clientes->findById(
            $empresaId
        );

        if ($empresa === null) {
            return null;
        }

        return [
            'empresa' => $empresa,

            'responsaveis' =>
            $this->responsaveis
                ->findByEmpresaId(
                    $empresaId
                ),
        ];
    }

    /**
     * Busca apenas a empresa.
     */
    public function buscarEmpresa(
        int $empresaId
    ): ?array {
        return $this->clientes->findById(
            $empresaId
        );
    }

    /**
     * Cadastra responsável da empresa.
     */
    public function cadastrar(
        int $empresaId,
        array $input,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $empresa = $this->clientes->findById(
            $empresaId
        );

        if ($empresa === null) {
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
            !isset($errors['principal'])
            && $data['principal'] === true
            && $this->responsaveis->hasPrincipal(
                $empresaId
            )
        ) {
            $errors['principal'] =
                'Esta empresa já possui um responsável principal.';
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
             */
            if (
                $data['principal'] === true
                && $this->responsaveis->hasPrincipal(
                    $empresaId
                )
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,

                    'errors' => [
                        'principal' =>
                        'Esta empresa já possui um responsável principal.',
                    ],

                    'data' => $data,
                ];
            }

            $responsavelId =
                $this->responsaveis->create(
                    $empresaId,
                    $data['nome'],

                    $data['email'] !== ''
                        ? $data['email']
                        : null,

                    $data['telefone'] !== ''
                        ? $data['telefone']
                        : null,

                    $data['cargo'] !== ''
                        ? $data['cargo']
                        : null,

                    $data['principal']
                );

            $this->auditoria->create(
                $usuarioId,
                'RESPONSAVEL_EMPRESA_CRIADO',
                'clientes',
                'empresa_responsavel',
                $responsavelId,
                $ip,
                $userAgent,
                null,
                [
                    'empresa_id' =>
                    $empresaId,

                    'nome' =>
                    $data['nome'],

                    'email' =>
                    $data['email'],

                    'telefone' =>
                    $data['telefone'],

                    'cargo' =>
                    $data['cargo'],

                    'principal' =>
                    $data['principal'],

                    'ativo' =>
                    true,
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'not_found' => false,
                'errors' => [],
                'data' => $data,
                'responsavel_id' =>
                $responsavelId,
            ];
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $sqlState =
                $exception->errorInfo[0]
                ?? $exception->getCode();

            /*
             * Índice UNIQUE do principal.
             */
            if ($sqlState === '23505') {
                return [
                    'success' => false,
                    'not_found' => false,

                    'errors' => [
                        'principal' =>
                        'Esta empresa já possui um responsável principal.',
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
     * Normaliza dados recebidos.
     */
    private function normalize(
        array $input
    ): array {
        $nome =
            isset($input['nome'])
            && is_string($input['nome'])
            ? trim($input['nome'])
            : '';

        $email =
            isset($input['email'])
            && is_string($input['email'])
            ? mb_strtolower(
                trim($input['email']),
                'UTF-8'
            )
            : '';

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

        $cargo =
            isset($input['cargo'])
            && is_string($input['cargo'])
            ? trim($input['cargo'])
            : '';

        /*
         * Checkbox HTML:
         * somente valor explicitamente permitido
         * resulta em true.
         */
        $principal =
            isset($input['principal'])
            && is_string($input['principal'])
            && $input['principal'] === '1';

        return [
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone,
            'cargo' => $cargo,
            'principal' => $principal,
        ];
    }

    /**
     * Validação backend.
     */
    private function validate(
        array $data
    ): array {
        $errors = [];

        if ($data['nome'] === '') {
            $errors['nome'] =
                'Informe o nome do responsável.';
        } elseif (
            mb_strlen(
                $data['nome'],
                'UTF-8'
            ) > 150
        ) {
            $errors['nome'] =
                'O nome deve possuir no máximo 150 caracteres.';
        }

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
                    'Informe um e-mail válido.';
            }
        }

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

        if (
            mb_strlen(
                $data['cargo'],
                'UTF-8'
            ) > 100
        ) {
            $errors['cargo'] =
                'O cargo deve possuir no máximo 100 caracteres.';
        }

        return $errors;
    }
}
