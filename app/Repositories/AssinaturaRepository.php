<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class AssinaturaRepository
{
    /**
     * Chave fixa do advisory lock PostgreSQL usado pela régua
     * de inadimplência.
     *
     * O lock é por conexão e também funciona em cenários futuros
     * com múltiplas instâncias da aplicação.
     */
    private const DELINQUENCY_LOCK_KEY = 817260828;

    /*
     * Segurança / minimização de dados:
     *
     * Consultas genéricas deste Repository retornam somente os
     * campos necessários para cada fluxo. Identificadores de gateway,
     * dados de contato e futuros segredos financeiros devem ser
     * carregados apenas por métodos dedicados ao caso de uso.
     *
     * Chaves de API e segredos de webhook nunca pertencem a este
     * Repository, às views ou aos logs; ficam exclusivamente no
     * ambiente seguro da aplicação.
     */
    /**
     * Lista as assinaturas com os dados comerciais
     * necessários para a tela principal.
     */
    public function findAll(): array
    {
        $pdo = Database::connection();

        $statement = $pdo->query(
            '
            SELECT
                a.id,
                a.empresa_id,
                a.produto_id,
                a.plano_id,
                a.status,
                a.valor_contratado,
                a.moeda,
                a.periodicidade,
                a.inicio_em,
                a.trial_fim_em,
                a.vencimento_em,
                a.proxima_cobranca_em,
                a.ultimo_pagamento_em,
                a.dias_tolerancia,
                a.suspenso_em,
                a.cancelado_em,
                a.cancelamento_motivo,
                a.criado_em,
                a.atualizado_em,

                e.razao_social AS empresa_razao_social,
                e.nome_fantasia AS empresa_nome_fantasia,
                e.status AS empresa_status,

                p.codigo AS produto_codigo,
                p.nome AS produto_nome,
                p.ativo AS produto_ativo,

                pl.codigo AS plano_codigo,
                pl.nome AS plano_nome,
                pl.ativo AS plano_ativo

            FROM public.assinaturas AS a

            INNER JOIN public.empresas AS e
                ON e.id = a.empresa_id

            INNER JOIN public.produtos AS p
                ON p.id = a.produto_id

            INNER JOIN public.planos AS pl
                ON pl.id = a.plano_id
               AND pl.produto_id = a.produto_id

            ORDER BY
                CASE a.status
                    WHEN \'ATRASADA\' THEN 1
                    WHEN \'SUSPENSA\' THEN 2
                    WHEN \'PENDENTE_ATIVACAO\' THEN 3
                    WHEN \'TRIAL\' THEN 4
                    WHEN \'ATIVA\' THEN 5
                    WHEN \'CANCELADA\' THEN 6
                    ELSE 7
                END,
                a.proxima_cobranca_em NULLS LAST,
                a.id DESC
            '
        );

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    /**
     * Busca uma assinatura específica.
     */
    public function findById(
        int $assinaturaId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                a.id,
                a.empresa_id,
                a.produto_id,
                a.plano_id,
                a.status,
                a.valor_contratado,
                a.moeda,
                a.periodicidade,
                a.inicio_em,
                a.trial_fim_em,
                a.vencimento_em,
                a.proxima_cobranca_em,
                a.ultimo_pagamento_em,
                a.dias_tolerancia,
                a.suspenso_em,
                a.cancelado_em,
                a.cancelamento_motivo,
                a.criado_em,
                a.atualizado_em,

                e.razao_social AS empresa_razao_social,
                e.nome_fantasia AS empresa_nome_fantasia,
                e.status AS empresa_status,

                p.codigo AS produto_codigo,
                p.nome AS produto_nome,
                p.ativo AS produto_ativo,

                pl.codigo AS plano_codigo,
                pl.nome AS plano_nome,
                pl.ativo AS plano_ativo

            FROM public.assinaturas AS a

            INNER JOIN public.empresas AS e
                ON e.id = a.empresa_id

            INNER JOIN public.produtos AS p
                ON p.id = a.produto_id

            INNER JOIN public.planos AS pl
                ON pl.id = a.plano_id
               AND pl.produto_id = a.produto_id

            WHERE a.id = :assinatura_id

            LIMIT 1
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }


    /**
     * Bloqueia uma assinatura para alteração de estado.
     *
     * Deve ser chamado dentro de uma transação.
     */
    public function lockById(
        int $assinaturaId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                empresa_id,
                produto_id,
                plano_id,
                status,
                valor_contratado,
                moeda,
                periodicidade,
                inicio_em,
                trial_fim_em,
                vencimento_em,
                proxima_cobranca_em,
                ultimo_pagamento_em,
                dias_tolerancia,
                suspenso_em,
                cancelado_em,
                cancelamento_motivo,
                criado_em,
                atualizado_em
            FROM public.assinaturas
            WHERE id = :assinatura_id
            FOR UPDATE
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }




    /**
     * Impede duas execuções simultâneas da régua financeira.
     *
     * O lock é liberado automaticamente se a conexão com PostgreSQL
     * for encerrada de forma inesperada.
     */
    public function tryAcquireDelinquencyProcessLock(): bool
    {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            'SELECT pg_try_advisory_lock(:lock_key)'
        );

        $statement->bindValue(
            ':lock_key',
            self::DELINQUENCY_LOCK_KEY,
            PDO::PARAM_INT
        );

        $statement->execute();

        $value =
            $statement->fetchColumn();

        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 't'
            || $value === 'true';
    }

    /**
     * Libera explicitamente o advisory lock da régua financeira.
     */
    public function releaseDelinquencyProcessLock(): void
    {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            'SELECT pg_advisory_unlock(:lock_key)'
        );

        $statement->bindValue(
            ':lock_key',
            self::DELINQUENCY_LOCK_KEY,
            PDO::PARAM_INT
        );

        $statement->execute();
    }

    /**
     * Assinaturas que podem exigir atualização por inadimplência.
     *
     * Apenas IDs são carregados; cada registro será relido com
     * FOR UPDATE dentro da transação antes de qualquer alteração.
     */
    public function findDelinquencyCandidateIds(
        string $hoje
    ): array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT id
            FROM public.assinaturas
            WHERE status IN (
                \'ATIVA\',
                \'ATRASADA\'
            )
              AND proxima_cobranca_em IS NOT NULL
              AND proxima_cobranca_em < :hoje
            ORDER BY id ASC
            '
        );

        $statement->bindValue(
            ':hoje',
            $hoje,
            PDO::PARAM_STR
        );

        $statement->execute();

        $rows = $statement->fetchAll(
            PDO::FETCH_COLUMN
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn (mixed $id): int =>
                        (int) $id,
                    $rows
                ),
                static fn (int $id): bool =>
                    $id > 0
            )
        );
    }

    /**
     * Marca uma assinatura ATIVA como ATRASADA.
     */
    public function markOverdue(
        int $assinaturaId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.assinaturas
            SET
                status = \'ATRASADA\',
                atualizado_em = NOW()
            WHERE id = :assinatura_id
              AND status = \'ATIVA\'
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->rowCount() === 1;
    }

    /**
     * Suspende uma assinatura ATRASADA após o fim da tolerância.
     */
    public function suspendForDelinquency(
        int $assinaturaId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.assinaturas
            SET
                status = \'SUSPENSA\',
                suspenso_em = NOW(),
                atualizado_em = NOW()
            WHERE id = :assinatura_id
              AND status = \'ATRASADA\'
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->rowCount() === 1;
    }

    /**
     * Lista cobranças que já venceram ou vencem até a data limite.
     *
     * A data limite é calculada pelo Service no timezone da aplicação.
     */
    public function findDueAttention(
        string $dataLimite
    ): array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                a.id,
                a.empresa_id,
                a.produto_id,
                a.plano_id,
                a.status,
                a.valor_contratado,
                a.moeda,
                a.periodicidade,
                a.inicio_em,
                a.proxima_cobranca_em,
                a.dias_tolerancia,

                e.razao_social AS empresa_razao_social,
                e.nome_fantasia AS empresa_nome_fantasia,

                p.codigo AS produto_codigo,
                p.nome AS produto_nome,

                pl.codigo AS plano_codigo,
                pl.nome AS plano_nome

            FROM public.assinaturas AS a

            INNER JOIN public.empresas AS e
                ON e.id = a.empresa_id

            INNER JOIN public.produtos AS p
                ON p.id = a.produto_id

            INNER JOIN public.planos AS pl
                ON pl.id = a.plano_id
               AND pl.produto_id = a.produto_id

            WHERE a.status IN (
                \'ATIVA\',
                \'ATRASADA\'
            )
              AND a.proxima_cobranca_em IS NOT NULL
              AND a.proxima_cobranca_em <= :data_limite

            ORDER BY
                a.proxima_cobranca_em ASC,
                COALESCE(
                    NULLIF(e.nome_fantasia, \'\'),
                    e.razao_social
                ) ASC
            '
        );

        $statement->bindValue(
            ':data_limite',
            $dataLimite,
            PDO::PARAM_STR
        );

        $statement->execute();

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    /**
     * Busca o responsável principal ativo da empresa.
     *
     * O telefone é carregado somente neste fluxo dedicado
     * de comunicação, evitando exposição em consultas genéricas.
     */
    public function findActivePrincipalResponsible(
        int $empresaId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                id,
                empresa_id,
                nome,
                telefone
            FROM public.empresa_responsaveis
            WHERE empresa_id = :empresa_id
              AND principal = true
              AND ativo = true
            LIMIT 1
            '
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * Empresas disponíveis para contratação.
     *
     * Nesta etapa somente empresas ATIVAS podem
     * receber uma nova assinatura.
     */
    public function findActiveCompanies(): array
    {
        $pdo = Database::connection();

        $statement = $pdo->query(
            '
            SELECT
                id,
                razao_social,
                nome_fantasia
            FROM public.empresas
            WHERE status = \'ATIVA\'
            ORDER BY
                COALESCE(
                    NULLIF(nome_fantasia, \'\'),
                    razao_social
                ) ASC
            '
        );

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    /**
     * Catálogo ativo usado no formulário.
     *
     * Retorna somente produtos e planos que estão
     * simultaneamente ativos.
     */
    public function findActiveCatalog(): array
    {
        $pdo = Database::connection();

        $statement = $pdo->query(
            '
            SELECT
                p.id AS produto_id,
                p.codigo AS produto_codigo,
                p.nome AS produto_nome,

                pl.id AS plano_id,
                pl.codigo AS plano_codigo,
                pl.nome AS plano_nome,
                pl.valor,
                pl.moeda,
                pl.periodicidade

            FROM public.produtos AS p

            INNER JOIN public.planos AS pl
                ON pl.produto_id = p.id
               AND pl.ativo = true

            WHERE p.ativo = true

            ORDER BY
                p.nome ASC,
                pl.valor ASC,
                pl.nome ASC
            '
        );

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    /**
     * Confirma no backend que o plano pertence ao
     * produto informado e que ambos permanecem ativos.
     */
    public function findActivePlan(
        int $produtoId,
        int $planoId
    ): ?array {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT
                p.id AS produto_id,
                p.codigo AS produto_codigo,
                p.nome AS produto_nome,

                pl.id AS plano_id,
                pl.codigo AS plano_codigo,
                pl.nome AS plano_nome,
                pl.valor,
                pl.moeda,
                pl.periodicidade

            FROM public.produtos AS p

            INNER JOIN public.planos AS pl
                ON pl.produto_id = p.id

            WHERE p.id = :produto_id
              AND pl.id = :plano_id
              AND p.ativo = true
              AND pl.ativo = true

            LIMIT 1
            '
        );

        $statement->bindValue(
            ':produto_id',
            $produtoId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':plano_id',
            $planoId,
            PDO::PARAM_INT
        );

        $statement->execute();

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * Verifica se já existe assinatura corrente
     * para a mesma empresa + produto.
     *
     * Espelha a regra do índice único parcial:
     * assinaturas CANCELADAS permanecem no histórico
     * e não impedem uma nova contratação.
     */
    public function hasCurrentSubscription(
        int $empresaId,
        int $produtoId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT 1
            FROM public.assinaturas
            WHERE empresa_id = :empresa_id
              AND produto_id = :produto_id
              AND status <> \'CANCELADA\'
            LIMIT 1
            '
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':produto_id',
            $produtoId,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->fetchColumn()
            !== false;
    }


    /**
     * Bloqueia a empresa sem exigir status ATIVA.
     *
     * Usado em operações de encerramento, pois uma assinatura
     * deve poder ser cancelada mesmo quando o cliente já está inativo.
     */
    public function lockCompany(
        int $empresaId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT id
            FROM public.empresas
            WHERE id = :empresa_id
            FOR UPDATE
            '
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->fetchColumn()
            !== false;
    }

    /**
     * Bloqueia a empresa durante a criação da assinatura.
     *
     * O lock reduz condições de corrida e o índice UNIQUE
     * parcial continua sendo a defesa final no PostgreSQL.
     *
     * Deve ser chamado dentro de transação.
     */
    public function lockActiveCompany(
        int $empresaId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT id
            FROM public.empresas
            WHERE id = :empresa_id
              AND status = \'ATIVA\'
            FOR UPDATE
            '
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->fetchColumn()
            !== false;
    }


    /**
     * Verifica se a empresa possui ao menos
     * um responsável ativo.
     *
     * A ativação comercial depende dessa regra.
     */
    public function hasActiveResponsible(
        int $empresaId
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            SELECT 1
            FROM public.empresa_responsaveis
            WHERE empresa_id = :empresa_id
              AND ativo = true
            LIMIT 1
            '
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->fetchColumn()
            !== false;
    }

    /**
     * Ativa uma assinatura que ainda está pendente.
     *
     * O WHERE com status protege contra transições
     * inválidas ou repetidas.
     */
    public function activatePending(
        int $assinaturaId,
        string $proximaCobrancaEm
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.assinaturas
            SET
                status = \'ATIVA\',
                proxima_cobranca_em = :proxima_cobranca_em,
                suspenso_em = NULL,
                atualizado_em = NOW()
            WHERE id = :assinatura_id
              AND status = \'PENDENTE_ATIVACAO\'
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':proxima_cobranca_em',
            $proximaCobrancaEm,
            PDO::PARAM_STR
        );

        $statement->execute();

        return $statement->rowCount() === 1;
    }


    /**
     * Atualiza o ciclo financeiro após um pagamento confirmado.
     *
     * Apenas assinaturas ATIVAS ou ATRASADAS podem ter uma
     * mensalidade registrada por este fluxo.
     */
    public function registerConfirmedPaymentCycle(
        int $assinaturaId,
        string $proximaCobrancaEm
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.assinaturas
            SET
                status = \'ATIVA\',
                ultimo_pagamento_em = NOW(),
                proxima_cobranca_em = :proxima_cobranca_em,
                suspenso_em = NULL,
                atualizado_em = NOW()
            WHERE id = :assinatura_id
              AND status IN (
                  \'ATIVA\',
                  \'ATRASADA\'
              )
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':proxima_cobranca_em',
            $proximaCobrancaEm,
            PDO::PARAM_STR
        );

        $statement->execute();

        return $statement->rowCount() === 1;
    }


    /**
     * Cancela definitivamente uma assinatura.
     *
     * Não apaga histórico financeiro e não remove a próxima cobrança:
     * os dados permanecem como fotografia do contrato encerrado.
     *
     * O índice único parcial existente deixa de considerar a assinatura
     * depois que o status se torna CANCELADA, permitindo nova contratação.
     */
    public function cancel(
        int $assinaturaId,
        string $motivo
    ): bool {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            UPDATE public.assinaturas
            SET
                status = \'CANCELADA\',
                vencimento_em = CURRENT_DATE,
                cancelado_em = NOW(),
                cancelamento_motivo = :cancelamento_motivo,
                atualizado_em = NOW()
            WHERE id = :assinatura_id
              AND status <> \'CANCELADA\'
            '
        );

        $statement->bindValue(
            ':assinatura_id',
            $assinaturaId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':cancelamento_motivo',
            $motivo,
            PDO::PARAM_STR
        );

        $statement->execute();

        return $statement->rowCount() === 1;
    }

    /**
     * Cria a assinatura em PENDENTE_ATIVACAO.
     *
     * Ponto importante de segurança:
     * valor_contratado, moeda e periodicidade NÃO são
     * recebidos do navegador. O próprio INSERT copia
     * esses dados diretamente do plano ativo no banco.
     *
     * Retorna null se, no momento do INSERT, produto/plano
     * não forem mais válidos ou ativos.
     */
    public function createPending(
        int $empresaId,
        int $produtoId,
        int $planoId,
        string $inicioEm
    ): ?int {
        $pdo = Database::connection();

        $statement = $pdo->prepare(
            '
            INSERT INTO public.assinaturas (
                empresa_id,
                produto_id,
                plano_id,
                valor_contratado,
                moeda,
                periodicidade,
                inicio_em
            )
            SELECT
                :empresa_id,
                p.id,
                pl.id,
                pl.valor,
                pl.moeda,
                pl.periodicidade,
                :inicio_em
            FROM public.produtos AS p
            INNER JOIN public.planos AS pl
                ON pl.produto_id = p.id
            WHERE p.id = :produto_id
              AND pl.id = :plano_id
              AND p.ativo = true
              AND pl.ativo = true
            RETURNING id
            '
        );

        $statement->bindValue(
            ':empresa_id',
            $empresaId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':produto_id',
            $produtoId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':plano_id',
            $planoId,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':inicio_em',
            $inicioEm,
            PDO::PARAM_STR
        );

        $statement->execute();

        $id = $statement->fetchColumn();

        if ($id === false) {
            return null;
        }

        $assinaturaId = (int) $id;

        if ($assinaturaId <= 0) {
            throw new RuntimeException(
                'ID inválido retornado ao criar assinatura.'
            );
        }

        return $assinaturaId;
    }
}
