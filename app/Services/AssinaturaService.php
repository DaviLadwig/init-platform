<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\AssinaturaRepository;
use App\Repositories\AssinaturaPagamentoRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\SystemAuditLogRepository;
use DateTimeImmutable;
use PDOException;
use RuntimeException;
use Throwable;

final class AssinaturaService
{
    public function __construct(
        private readonly AssinaturaRepository $assinaturas,
        private readonly AssinaturaPagamentoRepository $pagamentos,
        private readonly AuditLogRepository $auditoria,
        private readonly SystemAuditLogRepository $auditoriaSistema
    ) {}

    /**
     * Lista as assinaturas da plataforma.
     */
    public function listar(): array
    {
        return $this->assinaturas->findAll();
    }

    /**
     * Busca uma assinatura.
     */
    public function buscar(
        int $assinaturaId
    ): ?array {
        return $this->assinaturas->findById(
            $assinaturaId
        );
    }

    /**
     * Dados permitidos para o formulário de cadastro.
     *
     * O navegador recebe apenas opções válidas no momento
     * da renderização. Mesmo assim, tudo será revalidado
     * novamente no cadastro.
     */
    public function opcoesCadastro(): array
    {
        return [
            'empresas' =>
                $this->assinaturas
                    ->findActiveCompanies(),

            'catalogo' =>
                $this->assinaturas
                    ->findActiveCatalog(),
        ];
    }

    /**
     * Cria uma assinatura ainda não ativada.
     *
     * O formulário só pode informar:
     * - empresa_id
     * - produto_id
     * - plano_id
     * - inicio_em
     *
     * Status, preço, moeda e periodicidade são definidos
     * exclusivamente pelo backend/banco.
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

        if ($errors !== []) {
            return $this->validationResult(
                $errors,
                $data
            );
        }

        $empresaId =
            (int) $data['empresa_id'];

        $produtoId =
            (int) $data['produto_id'];

        $planoId =
            (int) $data['plano_id'];

        /*
         * Validação amigável antes da transação.
         *
         * Não confiamos na relação produto/plano vinda
         * do formulário.
         */
        $plano =
            $this->assinaturas
                ->findActivePlan(
                    $produtoId,
                    $planoId
                );

        if ($plano === null) {
            return $this->validationResult(
                [
                    'plano_id' =>
                        'O plano selecionado não está disponível para este produto.',
                ],
                $data
            );
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            /*
             * Serializa novas contratações da empresa.
             *
             * Também confirma, já dentro da transação,
             * que a empresa continua ATIVA.
             */
            if (
                !$this->assinaturas
                    ->lockActiveCompany(
                        $empresaId
                    )
            ) {
                $pdo->rollBack();

                return $this->validationResult(
                    [
                        'empresa_id' =>
                            'O cliente selecionado não está disponível para novas assinaturas.',
                    ],
                    $data
                );
            }

            /*
             * Revalidação após obter o lock.
             *
             * Isso evita trabalhar apenas com o estado
             * observado antes da transação.
             */
            $plano =
                $this->assinaturas
                    ->findActivePlan(
                        $produtoId,
                        $planoId
                    );

            if ($plano === null) {
                $pdo->rollBack();

                return $this->validationResult(
                    [
                        'plano_id' =>
                            'O plano selecionado deixou de estar disponível.',
                    ],
                    $data
                );
            }

            /*
             * Regra de negócio equivalente ao índice
             * UNIQUE parcial existente no PostgreSQL.
             */
            if (
                $this->assinaturas
                    ->hasCurrentSubscription(
                        $empresaId,
                        $produtoId
                    )
            ) {
                $pdo->rollBack();

                return $this->validationResult(
                    [
                        'produto_id' =>
                            'Este cliente já possui uma assinatura corrente para o produto selecionado.',
                    ],
                    $data
                );
            }

            /*
             * O Repository copia valor, moeda e periodicidade
             * diretamente do plano e utiliza o default
             * PENDENTE_ATIVACAO da tabela.
             */
            $assinaturaId =
                $this->assinaturas
                    ->createPending(
                        $empresaId,
                        $produtoId,
                        $planoId,
                        $data['inicio_em']
                    );

            if ($assinaturaId === null) {
                $pdo->rollBack();

                return $this->validationResult(
                    [
                        'plano_id' =>
                            'Não foi possível utilizar o plano selecionado. Atualize a página e tente novamente.',
                    ],
                    $data
                );
            }

            /*
             * Buscamos o estado realmente persistido para
             * auditar o snapshot comercial salvo no banco.
             */
            $assinatura =
                $this->assinaturas
                    ->findById(
                        $assinaturaId
                    );

            if ($assinatura === null) {
                throw new RuntimeException(
                    'Assinatura criada não pôde ser recuperada para auditoria.'
                );
            }

            $this->auditoria->create(
                $usuarioId,
                'ASSINATURA_CRIADA',
                'assinaturas',
                'assinatura',
                $assinaturaId,
                $ip,
                $userAgent,
                null,
                [
                    'empresa_id' =>
                        $empresaId,

                    'produto_id' =>
                        $produtoId,

                    'plano_id' =>
                        $planoId,

                    'status' =>
                        $this->stringValue(
                            $assinatura,
                            'status'
                        ),

                    'valor_contratado' =>
                        $this->numericStringValue(
                            $assinatura,
                            'valor_contratado'
                        ),

                    'moeda' =>
                        $this->stringValue(
                            $assinatura,
                            'moeda'
                        ),

                    'periodicidade' =>
                        $this->stringValue(
                            $assinatura,
                            'periodicidade'
                        ),

                    'inicio_em' =>
                        $this->stringValue(
                            $assinatura,
                            'inicio_em'
                        ),

                    'dias_tolerancia' =>
                        isset(
                            $assinatura['dias_tolerancia']
                        )
                            ? (int)
                                $assinatura['dias_tolerancia']
                            : null,
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'errors' => [],
                'data' => $data,
                'assinatura_id' =>
                    $assinaturaId,
            ];
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $sqlState =
                $exception->errorInfo[0]
                ?? $exception->getCode();

            /*
             * Defesa final do índice UNIQUE parcial:
             * empresa + produto enquanto status != CANCELADA.
             */
            if ($sqlState === '23505') {
                return $this->validationResult(
                    [
                        'produto_id' =>
                            'Este cliente já possui uma assinatura corrente para o produto selecionado.',
                    ],
                    $data
                );
            }

            /*
             * Produto/plano/empresa podem deixar de existir
             * entre validação e persistência. O banco continua
             * como defesa final de integridade referencial.
             */
            if ($sqlState === '23503') {
                return $this->validationResult(
                    [
                        'plano_id' =>
                            'Os dados comerciais selecionados não estão mais disponíveis.',
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
     * Ativa uma assinatura pendente.
     *
     * Regras:
     * - somente PENDENTE_ATIVACAO pode ser ativada;
     * - empresa precisa continuar ATIVA;
     * - empresa precisa possuir ao menos um responsável ativo;
     * - produto e plano precisam estar ativos no momento da ativação;
     * - a primeira cobrança vence na data inicio_em;
     * - gateway permanece nulo nesta etapa.
     */
    public function ativar(
        int $assinaturaId,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $assinaturaInicial =
            $this->assinaturas
                ->findById(
                    $assinaturaId
                );

        if ($assinaturaInicial === null) {
            return [
                'success' => false,
                'not_found' => true,
                'message' =>
                    'Assinatura não encontrada.',
            ];
        }

        $empresaId =
            isset($assinaturaInicial['empresa_id'])
                ? (int) $assinaturaInicial['empresa_id']
                : 0;

        if ($empresaId <= 0) {
            throw new RuntimeException(
                'Empresa inválida vinculada à assinatura.'
            );
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            /*
             * O lock da empresa vem primeiro.
             *
             * Responsáveis também utilizam a empresa
             * como registro de serialização, mantendo
             * uma ordem consistente para operações
             * comerciais relacionadas ao cliente.
             */
            if (
                !$this->assinaturas
                    ->lockActiveCompany(
                        $empresaId
                    )
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'O cliente está inativo e a assinatura não pode ser ativada.',
                ];
            }

            $assinatura =
                $this->assinaturas
                    ->lockById(
                        $assinaturaId
                    );

            if ($assinatura === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'message' =>
                        'Assinatura não encontrada.',
                ];
            }

            if (
                (int) (
                    $assinatura['empresa_id']
                    ?? 0
                ) !== $empresaId
            ) {
                throw new RuntimeException(
                    'Vínculo da empresa da assinatura foi alterado inesperadamente.'
                );
            }

            $statusAtual =
                $this->stringValue(
                    $assinatura,
                    'status'
                );

            if ($statusAtual !== 'PENDENTE_ATIVACAO') {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Somente assinaturas pendentes de ativação podem ser ativadas.',
                ];
            }

            if (
                !$this->assinaturas
                    ->hasActiveResponsible(
                        $empresaId
                    )
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Cadastre ou ative ao menos um responsável do cliente antes de ativar a assinatura.',
                ];
            }

            $produtoId =
                isset($assinatura['produto_id'])
                    ? (int) $assinatura['produto_id']
                    : 0;

            $planoId =
                isset($assinatura['plano_id'])
                    ? (int) $assinatura['plano_id']
                    : 0;

            if (
                $produtoId <= 0
                || $planoId <= 0
            ) {
                throw new RuntimeException(
                    'Produto ou plano inválido na assinatura.'
                );
            }

            $plano =
                $this->assinaturas
                    ->findActivePlan(
                        $produtoId,
                        $planoId
                    );

            if ($plano === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'O produto ou plano desta assinatura não está mais ativo.',
                ];
            }

            /*
             * Documentação contratual obrigatória.
             *
             * Esta é a validação amigável da aplicação.
             * A migration 009 repete a regra no PostgreSQL como
             * defesa final contra bypass ou concorrência.
             */
            $documentosPendentes =
                $this->assinaturas
                    ->findMissingRequiredDocumentsForActivation(
                        $assinaturaId,
                        $produtoId
                    );

            if ($documentosPendentes !== []) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'A assinatura não pode ser ativada enquanto houver documento contratual obrigatório pendente.',
                    'documentacao_pendente' => true,
                    'documentos_pendentes' =>
                        array_map(
                            static function (array $documento): array {
                                return [
                                    'id' =>
                                        isset($documento['id'])
                                            ? (int) $documento['id']
                                            : 0,

                                    'titulo' =>
                                        isset($documento['titulo'])
                                        && is_string($documento['titulo'])
                                            ? $documento['titulo']
                                            : 'Documento contratual',

                                    'versao' =>
                                        isset($documento['versao'])
                                        && is_string($documento['versao'])
                                            ? $documento['versao']
                                            : '',
                                ];
                            },
                            $documentosPendentes
                        ),
                ];
            }

            $inicioEm =
                $this->stringValue(
                    $assinatura,
                    'inicio_em'
                );

            if (!$this->isValidDate($inicioEm)) {
                throw new RuntimeException(
                    'Data de início inválida na assinatura.'
                );
            }

            /*
             * Nesta fase ainda não existe gateway nem
             * registro de pagamento.
             *
             * Portanto, a primeira cobrança pendente é
             * a própria data de início do contrato.
             *
             * Quando o pagamento for registrado,
             * avançaremos esta data conforme a
             * periodicidade contratada.
             */
            $proximaCobrancaEm =
                $inicioEm;

            if (
                !$this->assinaturas
                    ->activatePending(
                        $assinaturaId,
                        $proximaCobrancaEm
                    )
            ) {
                throw new RuntimeException(
                    'A assinatura não pôde ser ativada.'
                );
            }

            $assinaturaAtivada =
                $this->assinaturas
                    ->findById(
                        $assinaturaId
                    );

            if ($assinaturaAtivada === null) {
                throw new RuntimeException(
                    'Assinatura ativada não pôde ser recuperada para auditoria.'
                );
            }

            $this->auditoria->create(
                $usuarioId,
                'ASSINATURA_ATIVADA',
                'assinaturas',
                'assinatura',
                $assinaturaId,
                $ip,
                $userAgent,
                [
                    'empresa_id' =>
                        $empresaId,

                    'produto_id' =>
                        $produtoId,

                    'plano_id' =>
                        $planoId,

                    'status' =>
                        $statusAtual,

                    'proxima_cobranca_em' =>
                        $this->nullableStringValue(
                            $assinatura,
                            'proxima_cobranca_em'
                        ),
                ],
                [
                    'empresa_id' =>
                        $empresaId,

                    'produto_id' =>
                        $produtoId,

                    'plano_id' =>
                        $planoId,

                    'status' =>
                        $this->stringValue(
                            $assinaturaAtivada,
                            'status'
                        ),

                    'proxima_cobranca_em' =>
                        $this->nullableStringValue(
                            $assinaturaAtivada,
                            'proxima_cobranca_em'
                        ),
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'not_found' => false,
                'message' =>
                    'Assinatura ativada com sucesso.',
            ];
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            /*
             * Defesa de banco da migration 009.
             *
             * Em uma condição de corrida ou tentativa de bypass,
             * o PostgreSQL continua impedindo a ativação e nós
             * retornamos uma mensagem segura ao administrador.
             */
            if ($exception->getCode() === 'P7501') {
                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'A assinatura não pode ser ativada enquanto houver documento contratual obrigatório pendente.',
                    'documentacao_pendente' => true,
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
     * Carrega a assinatura e seu histórico financeiro.
     */
    public function detalhar(
        int $assinaturaId
    ): ?array {
        $assinatura =
            $this->assinaturas
                ->findById(
                    $assinaturaId
                );

        if ($assinatura === null) {
            return null;
        }

        return [
            'assinatura' =>
                $assinatura,

            'pagamentos' =>
                $this->pagamentos
                    ->findByAssinaturaId(
                        $assinaturaId
                    ),
        ];
    }

    /**
     * Cancela uma assinatura preservando todo o histórico.
     *
     * CANCELADA é um estado terminal neste MVP. Para retomar o mesmo
     * produto, a empresa deverá receber uma nova assinatura.
     */
    public function cancelar(
        int $assinaturaId,
        string $motivo,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $motivo =
            trim(
                $motivo
            );

        $motivoLength =
            mb_strlen(
                $motivo,
                'UTF-8'
            );

        if (
            $motivoLength < 3
            || $motivoLength > 500
        ) {
            return [
                'success' => false,
                'not_found' => false,
                'message' =>
                    'Informe um motivo de cancelamento entre 3 e 500 caracteres.',
            ];
        }

        $assinaturaInicial =
            $this->assinaturas
                ->findById(
                    $assinaturaId
                );

        if ($assinaturaInicial === null) {
            return [
                'success' => false,
                'not_found' => true,
                'message' =>
                    'Assinatura não encontrada.',
            ];
        }

        $empresaId =
            isset($assinaturaInicial['empresa_id'])
                ? (int) $assinaturaInicial['empresa_id']
                : 0;

        if ($empresaId <= 0) {
            throw new RuntimeException(
                'Empresa inválida vinculada à assinatura.'
            );
        }

        $pdo =
            Database::connection();

        try {
            $pdo->beginTransaction();

            /*
             * Serializa cancelamento e novas contratações
             * da mesma empresa.
             */
            if (
                !$this->assinaturas
                    ->lockCompany(
                        $empresaId
                    )
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'message' =>
                        'Cliente não encontrado.',
                ];
            }

            $assinatura =
                $this->assinaturas
                    ->lockById(
                        $assinaturaId
                    );

            if ($assinatura === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'message' =>
                        'Assinatura não encontrada.',
                ];
            }

            if (
                (int) (
                    $assinatura['empresa_id']
                    ?? 0
                ) !== $empresaId
            ) {
                throw new RuntimeException(
                    'Vínculo da empresa da assinatura foi alterado inesperadamente.'
                );
            }

            $statusAnterior =
                $this->stringValue(
                    $assinatura,
                    'status'
                );

            if ($statusAnterior === 'CANCELADA') {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'message' =>
                        'Esta assinatura já está cancelada.',
                ];
            }

            if (
                !$this->assinaturas
                    ->cancel(
                        $assinaturaId,
                        $motivo
                    )
            ) {
                throw new RuntimeException(
                    'A assinatura não pôde ser cancelada.'
                );
            }

            $assinaturaCancelada =
                $this->assinaturas
                    ->findById(
                        $assinaturaId
                    );

            if ($assinaturaCancelada === null) {
                throw new RuntimeException(
                    'Assinatura cancelada não pôde ser recuperada para auditoria.'
                );
            }

            $this->auditoria->create(
                $usuarioId,
                'ASSINATURA_CANCELADA',
                'assinaturas',
                'assinatura',
                $assinaturaId,
                $ip,
                $userAgent,
                [
                    'status' =>
                        $statusAnterior,

                    'vencimento_em' =>
                        $this->nullableStringValue(
                            $assinatura,
                            'vencimento_em'
                        ),

                    'cancelado_em' =>
                        $this->nullableStringValue(
                            $assinatura,
                            'cancelado_em'
                        ),
                ],
                [
                    'status' =>
                        'CANCELADA',

                    'vencimento_em' =>
                        $this->nullableStringValue(
                            $assinaturaCancelada,
                            'vencimento_em'
                        ),

                    'cancelado_em' =>
                        $this->nullableStringValue(
                            $assinaturaCancelada,
                            'cancelado_em'
                        ),

                    'motivo' =>
                        $motivo,
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'not_found' => false,
                'message' =>
                    'Assinatura cancelada com sucesso.',
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Retorna os dados necessários para a confirmação manual
     * de uma mensalidade.
     */
    public function prepararPagamento(
        int $assinaturaId
    ): array {
        $assinatura =
            $this->assinaturas
                ->findById(
                    $assinaturaId
                );

        if ($assinatura === null) {
            return [
                'success' => false,
                'not_found' => true,
                'message' =>
                    'Assinatura não encontrada.',
            ];
        }

        $status =
            $this->stringValue(
                $assinatura,
                'status'
            );

        if (
            !in_array(
                $status,
                [
                    'ATIVA',
                    'ATRASADA',
                    'SUSPENSA',
                ],
                true
            )
        ) {
            return [
                'success' => false,
                'not_found' => false,
                'message' =>
                    'Esta assinatura não possui uma cobrança disponível para registro manual.',
            ];
        }

        $proximaCobrancaEm =
            $this->nullableStringValue(
                $assinatura,
                'proxima_cobranca_em'
            );

        if (
            $proximaCobrancaEm === null
            || !$this->isValidDate(
                $proximaCobrancaEm
            )
        ) {
            return [
                'success' => false,
                'not_found' => false,
                'message' =>
                    'A assinatura não possui uma data de cobrança válida.',
            ];
        }

        return [
            'success' => true,
            'not_found' => false,
            'assinatura' =>
                $assinatura,
        ];
    }

    /**
     * Registra um pagamento manual e avança o ciclo da assinatura.
     *
     * O navegador informa somente o método de pagamento.
     * Valor, moeda, vencimento, status e próxima cobrança são
     * definidos exclusivamente pelo backend.
     */
    public function registrarPagamento(
        int $assinaturaId,
        array $input,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $metodo =
            $this->normalizePaymentMethod(
                $input['metodo']
                ?? null
            );

        if ($metodo === false) {
            return [
                'success' => false,
                'not_found' => false,
                'errors' => [
                    'metodo' =>
                        'Selecione um método de pagamento válido.',
                ],
                'message' =>
                    'Revise os dados do pagamento.',
            ];
        }

        $assinaturaInicial =
            $this->assinaturas
                ->findById(
                    $assinaturaId
                );

        if ($assinaturaInicial === null) {
            return [
                'success' => false,
                'not_found' => true,
                'errors' => [],
                'message' =>
                    'Assinatura não encontrada.',
            ];
        }

        $empresaId =
            isset($assinaturaInicial['empresa_id'])
                ? (int) $assinaturaInicial['empresa_id']
                : 0;

        if ($empresaId <= 0) {
            throw new RuntimeException(
                'Empresa inválida vinculada à assinatura.'
            );
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            /*
             * Mantemos a mesma ordem de lock usada nas demais
             * operações comerciais: empresa primeiro, assinatura depois.
             */
            if (
                !$this->assinaturas
                    ->lockActiveCompany(
                        $empresaId
                    )
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'errors' => [],
                    'message' =>
                        'O cliente está inativo. O pagamento não pode alterar o ciclo da assinatura.',
                ];
            }

            $assinatura =
                $this->assinaturas
                    ->lockById(
                        $assinaturaId
                    );

            if ($assinatura === null) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => true,
                    'errors' => [],
                    'message' =>
                        'Assinatura não encontrada.',
                ];
            }

            if (
                (int) (
                    $assinatura['empresa_id']
                    ?? 0
                ) !== $empresaId
            ) {
                throw new RuntimeException(
                    'Vínculo da empresa da assinatura foi alterado inesperadamente.'
                );
            }

            $statusAnterior =
                $this->stringValue(
                    $assinatura,
                    'status'
                );

            if (
                !in_array(
                    $statusAnterior,
                    [
                        'ATIVA',
                        'ATRASADA',
                    ],
                    true
                )
            ) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'not_found' => false,
                    'errors' => [],
                    'message' =>
                        'Somente assinaturas ativas, atrasadas ou suspensas por inadimplência podem registrar este pagamento.',
                ];
            }

            $vencimentoReferencia =
                $this->nullableStringValue(
                    $assinatura,
                    'proxima_cobranca_em'
                );

            if (
                $vencimentoReferencia === null
                || !$this->isValidDate(
                    $vencimentoReferencia
                )
            ) {
                throw new RuntimeException(
                    'Data de cobrança inválida na assinatura.'
                );
            }

            $valor =
                $this->numericStringValue(
                    $assinatura,
                    'valor_contratado'
                );

            if ($valor === null) {
                throw new RuntimeException(
                    'Valor contratado inválido na assinatura.'
                );
            }

            $moeda =
                $this->stringValue(
                    $assinatura,
                    'moeda'
                );

            if (
                preg_match(
                    '/^[A-Z]{3}$/',
                    $moeda
                ) !== 1
            ) {
                throw new RuntimeException(
                    'Moeda inválida na assinatura.'
                );
            }

            $periodicidade =
                $this->stringValue(
                    $assinatura,
                    'periodicidade'
                );

            $proximaCobrancaEm =
                $this->nextBillingDate(
                    $vencimentoReferencia,
                    $periodicidade
                );

            /*
             * O INSERT é executado antes da atualização da assinatura.
             * O índice UNIQUE de pagamentos confirmados garante
             * idempotência para a mesma competência financeira.
             */
            $pagamentoId =
                $this->pagamentos
                    ->createConfirmedManual(
                        $assinaturaId,
                        $vencimentoReferencia,
                        $valor,
                        $moeda,
                        $metodo,
                        $usuarioId
                    );

            if (
                !$this->assinaturas
                    ->registerConfirmedPaymentCycle(
                        $assinaturaId,
                        $proximaCobrancaEm
                    )
            ) {
                throw new RuntimeException(
                    'O ciclo da assinatura não pôde ser atualizado.'
                );
            }

            $assinaturaAtualizada =
                $this->assinaturas
                    ->findById(
                        $assinaturaId
                    );

            if ($assinaturaAtualizada === null) {
                throw new RuntimeException(
                    'Assinatura atualizada não pôde ser recuperada para auditoria.'
                );
            }

            $this->auditoria->create(
                $usuarioId,
                'ASSINATURA_PAGAMENTO_REGISTRADO',
                'assinaturas',
                'assinatura',
                $assinaturaId,
                $ip,
                $userAgent,
                [
                    'status' =>
                        $statusAnterior,

                    'proxima_cobranca_em' =>
                        $vencimentoReferencia,

                    'ultimo_pagamento_em' =>
                        $this->nullableStringValue(
                            $assinatura,
                            'ultimo_pagamento_em'
                        ),
                ],
                [
                    'pagamento_id' =>
                        $pagamentoId,

                    'status' =>
                        $this->stringValue(
                            $assinaturaAtualizada,
                            'status'
                        ),

                    'valor' =>
                        $valor,

                    'moeda' =>
                        $moeda,

                    'metodo' =>
                        $metodo,

                    'vencimento_referencia' =>
                        $vencimentoReferencia,

                    'proxima_cobranca_em' =>
                        $proximaCobrancaEm,

                    'ultimo_pagamento_em' =>
                        $this->nullableStringValue(
                            $assinaturaAtualizada,
                            'ultimo_pagamento_em'
                        ),
                ]
            );

            $pdo->commit();

            return [
                'success' => true,
                'not_found' => false,
                'errors' => [],
                'message' =>
                    'Pagamento registrado com sucesso.',
                'pagamento_id' =>
                    $pagamentoId,
            ];
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $sqlState =
                $exception->errorInfo[0]
                ?? $exception->getCode();

            /*
             * O mesmo vencimento não pode possuir dois
             * pagamentos CONFIRMADOS.
             */
            if ($sqlState === '23505') {
                return [
                    'success' => false,
                    'not_found' => false,
                    'errors' => [],
                    'message' =>
                        'Este vencimento já possui um pagamento confirmado.',
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
     * Atualiza o estado financeiro das assinaturas vencidas.
     *
     * Regras:
     * - vencimento < hoje: ATIVA -> ATRASADA;
     * - dias em atraso > dias_tolerancia:
     *   ATRASADA -> SUSPENSA;
     * - vencimento no dia permanece ATIVA;
     * - operação somente é chamada por POST administrativo
     *   ou, futuramente, por job interno autenticado.
     */
    public function processarInadimplencia(
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        return $this->processarInadimplenciaCore(
            function (
                string $acao,
                int $assinaturaId,
                array $dadosAnteriores,
                array $dadosNovos
            ) use (
                $usuarioId,
                $ip,
                $userAgent
            ): void {
                $this->auditoria->create(
                    $usuarioId,
                    $acao,
                    'assinaturas',
                    'assinatura',
                    $assinaturaId,
                    $ip,
                    $userAgent,
                    $dadosAnteriores,
                    $dadosNovos
                );
            }
        );
    }

    /**
     * Versão usada pelo scheduler/cron.
     *
     * Não existe usuário autenticado neste contexto. A auditoria é
     * registrada explicitamente com origem SISTEMA e usuario_id NULL.
     */
    public function processarInadimplenciaAutomatica(): array
    {
        return $this->processarInadimplenciaCore(
            function (
                string $acao,
                int $assinaturaId,
                array $dadosAnteriores,
                array $dadosNovos
            ): void {
                $this->auditoriaSistema->create(
                    $acao,
                    'assinaturas',
                    'assinatura',
                    $assinaturaId,
                    $dadosAnteriores,
                    $dadosNovos
                );
            }
        );
    }

    /**
     * Motor único da régua de inadimplência.
     *
     * Cada assinatura é processada em sua própria transação. Assim,
     * um registro inconsistente não impede a regularização dos demais.
     * Um advisory lock do PostgreSQL impede execuções concorrentes.
     */
    private function processarInadimplenciaCore(
        callable $audit
    ): array {
        $hoje =
            new DateTimeImmutable(
                'today'
            );

        $hojeString =
            $hoje->format(
                'Y-m-d'
            );

        if (
            !$this->assinaturas
                ->tryAcquireDelinquencyProcessLock()
        ) {
            return [
                'success' => true,
                'executado' => false,
                'motivo' =>
                    'OUTRA_EXECUCAO_EM_ANDAMENTO',
                'atrasadas' => 0,
                'suspensas' => 0,
                'falhas' => [],
            ];
        }

        $pdo = Database::connection();

        $atrasadas = 0;
        $suspensas = 0;
        $falhas = [];

        try {
            $ids =
                $this->assinaturas
                    ->findDelinquencyCandidateIds(
                        $hojeString
                    );

            foreach ($ids as $assinaturaId) {
                if (
                    !is_int($assinaturaId)
                    || $assinaturaId <= 0
                ) {
                    continue;
                }

                try {
                    $pdo->beginTransaction();

                    $assinatura =
                        $this->assinaturas
                            ->lockById(
                                $assinaturaId
                            );

                    if ($assinatura === null) {
                        $pdo->rollBack();
                        continue;
                    }

                    $status =
                        $this->stringValue(
                            $assinatura,
                            'status'
                        );

                    if (
                        !in_array(
                            $status,
                            [
                                'ATIVA',
                                'ATRASADA',
                            ],
                            true
                        )
                    ) {
                        $pdo->rollBack();
                        continue;
                    }

                    $vencimento =
                        $this->nullableStringValue(
                            $assinatura,
                            'proxima_cobranca_em'
                        );

                    if (
                        $vencimento === null
                        || !$this->isValidDate(
                            $vencimento
                        )
                    ) {
                        throw new RuntimeException(
                            'Data de cobrança inválida na assinatura.'
                        );
                    }

                    $dueDate =
                        DateTimeImmutable::createFromFormat(
                            '!Y-m-d',
                            $vencimento
                        );

                    if (
                        $dueDate === false
                        || $dueDate >= $hoje
                    ) {
                        $pdo->rollBack();
                        continue;
                    }

                    $diasAtraso =
                        (int) $dueDate
                            ->diff(
                                $hoje
                            )
                            ->format(
                                '%a'
                            );

                    $diasTolerancia =
                        isset($assinatura['dias_tolerancia'])
                            ? (int) $assinatura['dias_tolerancia']
                            : 0;

                    if (
                        $diasTolerancia < 0
                        || $diasTolerancia > 90
                    ) {
                        throw new RuntimeException(
                            'Dias de tolerância inválidos na assinatura.'
                        );
                    }

                    if ($status === 'ATIVA') {
                        if (
                            !$this->assinaturas
                                ->markOverdue(
                                    $assinaturaId
                                )
                        ) {
                            throw new RuntimeException(
                                'Não foi possível marcar a assinatura como atrasada.'
                            );
                        }

                        $audit(
                            'ASSINATURA_MARCADA_ATRASADA',
                            $assinaturaId,
                            [
                                'status' =>
                                    'ATIVA',

                                'proxima_cobranca_em' =>
                                    $vencimento,
                            ],
                            [
                                'status' =>
                                    'ATRASADA',

                                'dias_atraso' =>
                                    $diasAtraso,

                                'dias_tolerancia' =>
                                    $diasTolerancia,

                                'proxima_cobranca_em' =>
                                    $vencimento,
                            ]
                        );

                        $status =
                            'ATRASADA';

                        $atrasadas++;
                    }

                    /*
                     * A suspensão só ocorre depois de ultrapassar
                     * completamente a tolerância.
                     *
                     * Ex.: tolerância 5 -> suspende no 6º dia.
                     */
                    if (
                        $status === 'ATRASADA'
                        && $diasAtraso > $diasTolerancia
                    ) {
                        if (
                            !$this->assinaturas
                                ->suspendForDelinquency(
                                    $assinaturaId
                                )
                        ) {
                            throw new RuntimeException(
                                'Não foi possível suspender a assinatura inadimplente.'
                            );
                        }

                        $audit(
                            'ASSINATURA_SUSPENSA_INADIMPLENCIA',
                            $assinaturaId,
                            [
                                'status' =>
                                    'ATRASADA',

                                'proxima_cobranca_em' =>
                                    $vencimento,
                            ],
                            [
                                'status' =>
                                    'SUSPENSA',

                                'dias_atraso' =>
                                    $diasAtraso,

                                'dias_tolerancia' =>
                                    $diasTolerancia,

                                'proxima_cobranca_em' =>
                                    $vencimento,
                            ]
                        );

                        $suspensas++;
                    }

                    $pdo->commit();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    /*
                     * Não colocamos SQL, credenciais ou dados do cliente
                     * no retorno do job. Apenas ID e classe da falha.
                     */
                    $falhas[] = [
                        'assinatura_id' =>
                            $assinaturaId,

                        'erro' =>
                            $exception::class,
                    ];
                }
            }

            return [
                'success' =>
                    $falhas === [],

                'executado' =>
                    true,

                'motivo' =>
                    null,

                'atrasadas' =>
                    $atrasadas,

                'suspensas' =>
                    $suspensas,

                'falhas' =>
                    $falhas,
            ];
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->assinaturas
                ->releaseDelinquencyProcessLock();
        }
    }

    /**
     * Central de vencimentos.
     *
     * Inclui:
     * - cobranças já vencidas;
     * - cobranças de hoje;
     * - cobranças que vencem nos próximos N dias.
     */
    public function listarVencimentos(
        int $diasAntecedencia = 2
    ): array {
        if (
            $diasAntecedencia < 0
            || $diasAntecedencia > 30
        ) {
            throw new RuntimeException(
                'Janela de vencimentos inválida.'
            );
        }

        $hoje =
            new DateTimeImmutable(
                'today'
            );

        $limite =
            $hoje->modify(
                '+' . $diasAntecedencia . ' days'
            );

        $rows =
            $this->assinaturas
                ->findDueAttention(
                    $limite->format(
                        'Y-m-d'
                    )
                );

        $vencimentos = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $data =
                $this->nullableStringValue(
                    $row,
                    'proxima_cobranca_em'
                );

            if (
                $data === null
                || !$this->isValidDate(
                    $data
                )
            ) {
                continue;
            }

            $dueDate =
                DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $data
                );

            if ($dueDate === false) {
                continue;
            }

            $dias =
                (int) $hoje
                    ->diff(
                        $dueDate
                    )
                    ->format(
                        '%r%a'
                    );

            $situacao =
                $dias < 0
                    ? 'VENCIDA'
                    : (
                        $dias === 0
                            ? 'HOJE'
                            : 'PROXIMA'
                    );

            $row['dias_para_vencer'] =
                $dias;

            $row['situacao_vencimento'] =
                $situacao;

            $vencimentos[] =
                $row;
        }

        return $vencimentos;
    }

    /**
     * Gera, de forma controlada, o link de lembrete para WhatsApp.
     *
     * O navegador envia somente o ID da assinatura.
     * Telefone, responsável, valor, produto e vencimento são
     * buscados novamente pelo backend.
     */
    public function gerarLembreteWhatsapp(
        int $assinaturaId,
        int $usuarioId,
        ?string $ip,
        ?string $userAgent
    ): array {
        $assinatura =
            $this->assinaturas
                ->findById(
                    $assinaturaId
                );

        if ($assinatura === null) {
            return [
                'success' => false,
                'not_found' => true,
                'message' =>
                    'Assinatura não encontrada.',
            ];
        }

        $status =
            $this->stringValue(
                $assinatura,
                'status'
            );

        if (
            !in_array(
                $status,
                [
                    'ATIVA',
                    'ATRASADA',
                    'SUSPENSA',
                ],
                true
            )
        ) {
            return [
                'success' => false,
                'not_found' => false,
                'message' =>
                    'Esta assinatura não está disponível para lembrete de cobrança.',
            ];
        }

        $vencimento =
            $this->nullableStringValue(
                $assinatura,
                'proxima_cobranca_em'
            );

        if (
            $vencimento === null
            || !$this->isValidDate(
                $vencimento
            )
        ) {
            return [
                'success' => false,
                'not_found' => false,
                'message' =>
                    'A assinatura não possui uma data de cobrança válida.',
            ];
        }

        $hoje =
            new DateTimeImmutable(
                'today'
            );

        $limite =
            $hoje->modify(
                '+2 days'
            );

        $dueDate =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $vencimento
            );

        if (
            $dueDate === false
            || $dueDate > $limite
        ) {
            return [
                'success' => false,
                'not_found' => false,
                'message' =>
                    'O lembrete por WhatsApp fica disponível a partir de dois dias antes do vencimento.',
            ];
        }

        $empresaId =
            isset($assinatura['empresa_id'])
                ? (int) $assinatura['empresa_id']
                : 0;

        if ($empresaId <= 0) {
            throw new RuntimeException(
                'Empresa inválida vinculada à assinatura.'
            );
        }

        $responsavel =
            $this->assinaturas
                ->findActivePrincipalResponsible(
                    $empresaId
                );

        if ($responsavel === null) {
            return [
                'success' => false,
                'not_found' => false,
                'message' =>
                    'O cliente não possui responsável principal ativo para receber o lembrete.',
            ];
        }

        $telefone =
            isset($responsavel['telefone'])
            && is_string($responsavel['telefone'])
                ? $responsavel['telefone']
                : '';

        $telefoneWhatsapp =
            $this->normalizeBrazilWhatsappPhone(
                $telefone
            );

        if ($telefoneWhatsapp === null) {
            return [
                'success' => false,
                'not_found' => false,
                'message' =>
                    'O responsável principal não possui um telefone válido para WhatsApp.',
            ];
        }

        $responsavelNome =
            isset($responsavel['nome'])
            && is_string($responsavel['nome'])
                ? trim($responsavel['nome'])
                : '';

        $nomeFantasia =
            $this->stringValue(
                $assinatura,
                'empresa_nome_fantasia'
            );

        $razaoSocial =
            $this->stringValue(
                $assinatura,
                'empresa_razao_social'
            );

        $empresaNome =
            $nomeFantasia !== ''
                ? $nomeFantasia
                : $razaoSocial;

        $produtoNome =
            $this->stringValue(
                $assinatura,
                'produto_nome'
            );

        $valor =
            $this->numericStringValue(
                $assinatura,
                'valor_contratado'
            );

        $moeda =
            $this->stringValue(
                $assinatura,
                'moeda'
            );

        if ($valor === null) {
            throw new RuntimeException(
                'Valor inválido na assinatura.'
            );
        }

        $valorFormatado =
            $this->formatMoneyForMessage(
                $valor,
                $moeda
            );

        $vencimentoFormatado =
            $dueDate->format(
                'd/m/Y'
            );

        $saudacaoNome =
            $responsavelNome !== ''
                ? $responsavelNome
                : 'responsável';

        $mensagem =
            'Olá, '
            . $saudacaoNome
            . '. Este é um lembrete da Init Sistemas referente à assinatura do '
            . $produtoNome
            . ' da empresa '
            . $empresaNome
            . '. A cobrança de '
            . $valorFormatado
            . ' vence em '
            . $vencimentoFormatado
            . '. Em caso de dúvidas, estamos à disposição.';

        /*
         * Não registramos telefone nem conteúdo integral da mensagem
         * na auditoria. Guardamos somente referências operacionais.
         */
        $this->auditoria->create(
            $usuarioId,
            'LEMBRETE_WHATSAPP_GERADO',
            'assinaturas',
            'assinatura',
            $assinaturaId,
            $ip,
            $userAgent,
            null,
            [
                'empresa_id' =>
                    $empresaId,

                'responsavel_id' =>
                    isset($responsavel['id'])
                        ? (int) $responsavel['id']
                        : null,

                'vencimento_referencia' =>
                    $vencimento,
            ]
        );

        return [
            'success' => true,
            'not_found' => false,
            'url' =>
                'https://wa.me/'
                . $telefoneWhatsapp
                . '?text='
                . rawurlencode(
                    $mensagem
                ),
        ];
    }

    /**
     * Normaliza somente os campos aceitos nesta etapa.
     *
     * Campos extras enviados manualmente pelo navegador,
     * como status, valor ou gateway, são descartados.
     */
    private function normalize(
        array $input
    ): array {
        return [
            'empresa_id' =>
                $this->normalizePositiveInteger(
                    $input['empresa_id']
                        ?? null
                ),

            'produto_id' =>
                $this->normalizePositiveInteger(
                    $input['produto_id']
                        ?? null
                ),

            'plano_id' =>
                $this->normalizePositiveInteger(
                    $input['plano_id']
                        ?? null
                ),

            'inicio_em' =>
                is_string(
                    $input['inicio_em']
                        ?? null
                )
                    ? trim(
                        $input['inicio_em']
                    )
                    : '',
        ];
    }

    /**
     * Validação independente do HTML.
     */
    private function validate(
        array $data
    ): array {
        $errors = [];

        if (
            !is_int(
                $data['empresa_id']
                    ?? null
            )
        ) {
            $errors['empresa_id'] =
                'Selecione um cliente válido.';
        }

        if (
            !is_int(
                $data['produto_id']
                    ?? null
            )
        ) {
            $errors['produto_id'] =
                'Selecione um produto válido.';
        }

        if (
            !is_int(
                $data['plano_id']
                    ?? null
            )
        ) {
            $errors['plano_id'] =
                'Selecione um plano válido.';
        }

        $inicioEm =
            $data['inicio_em']
            ?? '';

        if (
            !is_string($inicioEm)
            || !$this->isValidDate(
                $inicioEm
            )
        ) {
            $errors['inicio_em'] =
                'Informe uma data de início válida.';
        }

        return $errors;
    }


    /**
     * Normaliza o único dado financeiro informativo que
     * aceitamos do formulário manual.
     *
     * false = inválido.
     * null  = não informado.
     */
    private function normalizePaymentMethod(
        mixed $value
    ): string|null|false {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (!is_string($value)) {
            return false;
        }

        $normalized =
            mb_strtoupper(
                trim($value),
                'UTF-8'
            );

        $allowed = [
            'PIX',
            'BOLETO',
            'CARTAO',
            'TRANSFERENCIA',
            'DINHEIRO',
            'OUTRO',
        ];

        return in_array(
            $normalized,
            $allowed,
            true
        )
            ? $normalized
            : false;
    }

    /**
     * Calcula a próxima competência a partir do vencimento
     * quitado, e não da data em que o operador registrou o pagamento.
     *
     * Isso evita deslocar o calendário contratual quando o cliente
     * paga antecipado ou atrasado.
     */
    private function nextBillingDate(
        string $currentDueDate,
        string $periodicidade
    ): string {
        $months = match ($periodicidade) {
            'MENSAL' => 1,
            'TRIMESTRAL' => 3,
            'SEMESTRAL' => 6,
            'ANUAL' => 12,
            default => throw new RuntimeException(
                'Periodicidade inválida na assinatura.'
            ),
        };

        $base =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $currentDueDate
            );

        if (
            $base === false
            || $base->format('Y-m-d')
                !== $currentDueDate
        ) {
            throw new RuntimeException(
                'Data de cobrança inválida.'
            );
        }

        /*
         * O cálculo parte do primeiro dia do mês para não sofrer
         * o overflow conhecido de datas como 31/01 + 1 mês.
         */
        $day =
            (int) $base->format('d');

        $targetMonth =
            $base
                ->modify(
                    'first day of this month'
                )
                ->modify(
                    '+' . $months . ' months'
                );

        $lastDay =
            (int) $targetMonth
                ->modify(
                    'last day of this month'
                )
                ->format('d');

        $targetDay =
            min(
                $day,
                $lastDay
            );

        $targetDate =
            $targetMonth->setDate(
                (int) $targetMonth->format('Y'),
                (int) $targetMonth->format('m'),
                $targetDay
            );

        return $targetDate->format(
            'Y-m-d'
        );
    }


    /**
     * Normaliza número brasileiro para o formato internacional
     * exigido pelo wa.me.
     */
    private function normalizeBrazilWhatsappPhone(
        string $phone
    ): ?string {
        $digits =
            preg_replace(
                '/\D+/',
                '',
                $phone
            );

        if (!is_string($digits)) {
            return null;
        }

        $length =
            strlen(
                $digits
            );

        if (
            ($length === 10 || $length === 11)
            && !str_starts_with(
                $digits,
                '55'
            )
        ) {
            $digits =
                '55'
                . $digits;

            $length =
                strlen(
                    $digits
                );
        }

        if (
            ($length !== 12 && $length !== 13)
            || !str_starts_with(
                $digits,
                '55'
            )
        ) {
            return null;
        }

        return $digits;
    }

    /**
     * Formata o valor somente para texto de comunicação.
     *
     * Cálculos financeiros continuam usando NUMERIC/string.
     */
    private function formatMoneyForMessage(
        string $value,
        string $currency
    ): string {
        $normalized =
            number_format(
                (float) $value,
                2,
                ',',
                '.'
            );

        return $currency === 'BRL'
            ? 'R$ ' . $normalized
            : $currency . ' ' . $normalized;
    }

    private function normalizePositiveInteger(
        mixed $value
    ): ?int {
        if (is_int($value)) {
            return $value > 0
                ? $value
                : null;
        }

        if (
            !is_string($value)
            || !ctype_digit($value)
        ) {
            return null;
        }

        $normalized =
            (int) $value;

        return $normalized > 0
            ? $normalized
            : null;
    }

    private function isValidDate(
        string $value
    ): bool {
        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $value
            ) !== 1
        ) {
            return false;
        }

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $value
            );

        return $date !== false
            && $date->format(
                'Y-m-d'
            ) === $value;
    }

    private function validationResult(
        array $errors,
        array $data
    ): array {
        return [
            'success' => false,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    private function stringValue(
        array $source,
        string $key
    ): string {
        $value =
            $source[$key]
            ?? null;

        return is_string($value)
            ? $value
            : '';
    }


    private function nullableStringValue(
        array $source,
        string $key
    ): ?string {
        $value =
            $source[$key]
            ?? null;

        return is_string($value)
            && $value !== ''
                ? $value
                : null;
    }

    private function numericStringValue(
        array $source,
        string $key
    ): ?string {
        $value =
            $source[$key]
            ?? null;

        return is_numeric($value)
            ? (string) $value
            : null;
    }
}
