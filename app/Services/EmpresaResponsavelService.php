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
     * Retorna empresa e responsáveis.
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
     * Busca a empresa.
     */
    public function buscarEmpresa(
        int $empresaId
    ): ?array {
        return $this->clientes->findById(
            $empresaId
        );
    }

    /**
     * Busca responsável garantindo que
     * pertence à empresa informada.
     */
    public function buscarResponsavel(
        int $empresaId,
        int $responsavelId
    ): ?array {
        return $this->responsaveis
            ->findByIdAndEmpresaId(
                $responsavelId,
                $empresaId
            );
    }

    /**
     * Cadastro de responsável.
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
            return $this->notFoundResult();
        }

        $data = $this->normalize(
            $input
        );

        $errors = $this->validate(
            $data
        );

        if ($errors !== []) {
            return $this->validationResult(
                $errors,
                $data
            );
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            /*
             * Serializa alterações de responsáveis
             * desta empresa.
             */
            if (
                !$this->responsaveis
                    ->lockEmpresa(
                        $empresaId
                    )
            ) {
                $pdo->rollBack();

                return $this->notFoundResult();
            }

            /*
             * Cadastro de novo responsável principal
             * não substitui automaticamente o atual.
             *
             * A substituição será feita explicitamente
             * através da edição.
             */
            if (
                $data['principal'] === true
                && $this->responsaveis
                ->hasPrincipal(
                    $empresaId
                )
            ) {
                $pdo->rollBack();

                return $this->validationResult(
                    [
                        'principal' =>
                        'Esta empresa já possui um responsável principal. Edite outro responsável para realizar a troca.',
                    ],
                    $data
                );
            }

            $responsavelId =
                $this->responsaveis->create(
                    $empresaId,
                    $data['nome'],
                    $this->nullable(
                        $data['email']
                    ),
                    $this->nullable(
                        $data['telefone']
                    ),
                    $this->nullable(
                        $data['cargo']
                    ),
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
             * Defesa final do índice UNIQUE parcial.
             */
            if ($sqlState === '23505') {
                return $this->validationResult(
                    [
                        'principal' =>
                        'Esta empresa já possui um responsável principal.',
                    ],
                    $data
                );
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
     * Edita um responsável.
     *
     * Também realiza a troca de responsável
     * principal de forma transacional.
     */
    public function editar(
        int $empresaId,
        int $responsavelId,
        array $input,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $empresa = $this->clientes->findById(
            $empresaId
        );

        if ($empresa === null) {
            return $this->notFoundResult();
        }

        $responsavelAtual =
            $this->responsaveis
            ->findByIdAndEmpresaId(
                $responsavelId,
                $empresaId
            );

        if ($responsavelAtual === null) {
            return $this->notFoundResult();
        }

        $data = $this->normalize(
            $input
        );

        $errors = $this->validate(
            $data
        );

        if ($errors !== []) {
            return $this->validationResult(
                $errors,
                $data
            );
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            /*
             * O lock da empresa garante que duas
             * operações concorrentes não troquem
             * o principal ao mesmo tempo.
             */
            if (
                !$this->responsaveis
                    ->lockEmpresa(
                        $empresaId
                    )
            ) {
                $pdo->rollBack();

                return $this->notFoundResult();
            }

            /*
             * Revalidação dentro da transação.
             */
            $responsavelAtual =
                $this->responsaveis
                ->findByIdAndEmpresaId(
                    $responsavelId,
                    $empresaId
                );

            if ($responsavelAtual === null) {
                $pdo->rollBack();

                return $this->notFoundResult();
            }

            /*
             * Um responsável inativo não pode ser promovido
             * a principal. O bloqueio é feito no backend para
             * não depender da interface.
             */
            if (
                $data['principal'] === true
                && !$this->boolValue(
                    $responsavelAtual['ativo'] ?? false
                )
            ) {
                $pdo->rollBack();

                return $this->validationResult(
                    [
                        'principal' =>
                        'Ative o responsável antes de defini-lo como principal.',
                    ],
                    $data
                );
            }

            $principalAnterior = null;

            /*
             * Se este responsável está sendo
             * promovido a principal, retiramos
             * primeiro o principal anterior.
             */
            if ($data['principal'] === true) {
                $principalAnterior =
                    $this->responsaveis
                    ->clearPrincipalExcept(
                        $empresaId,
                        $responsavelId
                    );
            }

            $this->responsaveis->update(
                $responsavelId,
                $empresaId,
                $data['nome'],
                $this->nullable(
                    $data['email']
                ),
                $this->nullable(
                    $data['telefone']
                ),
                $this->nullable(
                    $data['cargo']
                ),
                $data['principal']
            );

            /*
             * Se outro responsável perdeu o vínculo
             * principal, registramos isso também.
             */
            if ($principalAnterior !== null) {
                $principalAnteriorId =
                    isset(
                        $principalAnterior['id']
                    )
                    ? (int) $principalAnterior['id']
                    : 0;

                if ($principalAnteriorId > 0) {
                    $this->auditoria->create(
                        $usuarioId,
                        'RESPONSAVEL_EMPRESA_PRINCIPAL_REMOVIDO',
                        'clientes',
                        'empresa_responsavel',
                        $principalAnteriorId,
                        $ip,
                        $userAgent,
                        [
                            'empresa_id' =>
                            $empresaId,

                            'principal' =>
                            true,
                        ],
                        [
                            'empresa_id' =>
                            $empresaId,

                            'principal' =>
                            false,
                        ]
                    );
                }
            }

            /*
             * Auditoria da edição do responsável.
             */
            $this->auditoria->create(
                $usuarioId,
                'RESPONSAVEL_EMPRESA_EDITADO',
                'clientes',
                'empresa_responsavel',
                $responsavelId,
                $ip,
                $userAgent,
                [
                    'empresa_id' =>
                    $empresaId,

                    'nome' =>
                    $this->stringValue(
                        $responsavelAtual,
                        'nome'
                    ),

                    'email' =>
                    $this->stringValue(
                        $responsavelAtual,
                        'email'
                    ),

                    'telefone' =>
                    $this->stringValue(
                        $responsavelAtual,
                        'telefone'
                    ),

                    'cargo' =>
                    $this->stringValue(
                        $responsavelAtual,
                        'cargo'
                    ),

                    'principal' =>
                    $this->boolValue(
                        $responsavelAtual['principal'] ?? false
                    ),

                    'ativo' =>
                    $this->boolValue(
                        $responsavelAtual['ativo'] ?? false
                    ),
                ],
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

                    /*
                     * Editar dados cadastrais
                     * não altera ativo.
                     */
                    'ativo' =>
                    $this->boolValue(
                        $responsavelAtual['ativo'] ?? false
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

            $sqlState =
                $exception->errorInfo[0]
                ?? $exception->getCode();

            if ($sqlState === '23505') {
                return $this->validationResult(
                    [
                        'principal' =>
                        'Não foi possível realizar a troca do responsável principal.',
                    ],
                    $data
                );
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
     * Ativa um responsável da empresa.
     */
    public function ativar(
        int $empresaId,
        int $responsavelId,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        return $this->alterarStatus(
            $empresaId,
            $responsavelId,
            true,
            $usuarioId,
            $ip,
            $userAgent
        );
    }

    /**
     * Desativa um responsável da empresa.
     *
     * Um responsável principal não pode ser desativado
     * enquanto mantiver este vínculo.
     */
    public function desativar(
        int $empresaId,
        int $responsavelId,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        return $this->alterarStatus(
            $empresaId,
            $responsavelId,
            false,
            $usuarioId,
            $ip,
            $userAgent
        );
    }

    /**
     * Alteração transacional do status do responsável.
     *
     * O lock da empresa serializa a operação com trocas
     * de responsável principal e evita decisões baseadas
     * em estado desatualizado.
     */
    private function alterarStatus(
        int $empresaId,
        int $responsavelId,
        bool $novoStatus,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $empresa = $this->clientes->findById(
            $empresaId
        );

        if ($empresa === null) {
            return $this->notFoundResult();
        }

        $responsavel = $this->responsaveis
            ->findByIdAndEmpresaId(
                $responsavelId,
                $empresaId
            );

        if ($responsavel === null) {
            return $this->notFoundResult();
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            if (
                !$this->responsaveis
                    ->lockEmpresa(
                        $empresaId
                    )
            ) {
                $pdo->rollBack();

                return $this->notFoundResult();
            }

            /*
             * Revalidação dentro da transação.
             */
            $responsavel = $this->responsaveis
                ->findByIdAndEmpresaId(
                    $responsavelId,
                    $empresaId
                );

            if ($responsavel === null) {
                $pdo->rollBack();

                return $this->notFoundResult();
            }

            $statusAtual = $this->boolValue(
                $responsavel['ativo'] ?? false
            );

            $principal = $this->boolValue(
                $responsavel['principal'] ?? false
            );

            /*
             * Responsável principal fica protegido contra
             * desativação. A regra é autoritativa no backend.
             */
            if (
                $novoStatus === false
                && $principal === true
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'changed' => false,
                    'errors' => [
                        'status' =>
                        'O responsável principal não pode ser desativado. Remova ou transfira o vínculo principal antes de desativá-lo.',
                    ],
                    'data' => [],
                ];
            }

            /*
             * Operação idempotente: um POST repetido não gera
             * nova escrita nem log duplicado.
             */
            if ($statusAtual === $novoStatus) {
                $pdo->commit();

                return [
                    'success' => true,
                    'not_found' => false,
                    'changed' => false,
                    'errors' => [],
                    'data' => [],
                ];
            }

            $this->responsaveis->updateActiveStatus(
                $responsavelId,
                $empresaId,
                $novoStatus
            );

            $this->auditoria->create(
                $usuarioId,
                $novoStatus
                    ? 'RESPONSAVEL_EMPRESA_ATIVADO'
                    : 'RESPONSAVEL_EMPRESA_DESATIVADO',
                'clientes',
                'empresa_responsavel',
                $responsavelId,
                $ip,
                $userAgent,
                [
                    'empresa_id' => $empresaId,
                    'principal' => $principal,
                    'ativo' => $statusAtual,
                ],
                [
                    'empresa_id' => $empresaId,
                    'principal' => $principal,
                    'ativo' => $novoStatus,
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'not_found' => false,
                'changed' => true,
                'errors' => [],
                'data' => [],
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Normalização backend.
     */
    private function normalize(
        array $input
    ): array {
        $nome =
            isset($input['nome'])
            && is_string($input['nome'])
            ? trim(
                $input['nome']
            )
            : '';

        $email =
            isset($input['email'])
            && is_string($input['email'])
            ? mb_strtolower(
                trim(
                    $input['email']
                ),
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
            ? trim(
                $input['cargo']
            )
            : '';

        $principal =
            isset($input['principal'])
            && is_string(
                $input['principal']
            )
            && $input['principal'] === '1';

        return [
            'nome' =>
            $nome,

            'email' =>
            $email,

            'telefone' =>
            $telefone,

            'cargo' =>
            $cargo,

            'principal' =>
            $principal,
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
                    'Informe um endereço de e-mail válido.';
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

    /**
     * Converte string vazia em NULL
     * para persistência.
     */
    private function nullable(
        string $value
    ): ?string {
        return $value !== ''
            ? $value
            : null;
    }

    private function stringValue(
        array $source,
        string $key
    ): string {
        $value = $source[$key]
            ?? null;

        return is_string($value)
            ? $value
            : '';
    }

    /**
     * PostgreSQL/PDO pode representar boolean
     * de maneiras diferentes dependendo da configuração.
     */
    private function boolValue(
        mixed $value
    ): bool {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 't'
            || $value === 'true';
    }

    private function notFoundResult(): array
    {
        return [
            'success' => false,
            'not_found' => true,
            'errors' => [],
            'data' => [],
        ];
    }

    private function validationResult(
        array $errors,
        array $data
    ): array {
        return [
            'success' => false,
            'not_found' => false,
            'errors' => $errors,
            'data' => $data,
        ];
    }
}
