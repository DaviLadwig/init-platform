<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Session;
use App\Repositories\AssinaturaRepository;
use App\Repositories\AssinaturaPagamentoRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\AssinaturaDocumentoRepository;
use App\Repositories\DocumentoContratualRepository;
use App\Repositories\SystemAuditLogRepository;
use App\Services\AssinaturaService;
use App\Services\ContratoStorageService;
use App\Services\DocumentoContratualService;
use RuntimeException;

final class AssinaturaController
{
    private const ACTIVE_MENU = 'assinaturas';

    private const PAGE_STYLES = [
        'assinaturas.css',
    ];

    /**
     * Somente estes campos podem chegar ao Service
     * durante o cadastro.
     */
    private const FORM_FIELDS = [
        'empresa_id',
        'produto_id',
        'plano_id',
        'inicio_em',
    ];

    private AssinaturaService $service;

    private DocumentoContratualService $documentoService;

    public function __construct()
    {
        $this->service = new AssinaturaService(
            new AssinaturaRepository(),
            new AssinaturaPagamentoRepository(),
            new AuditLogRepository(),
            new SystemAuditLogRepository()
        );

        $this->documentoService =
            new DocumentoContratualService(
                new DocumentoContratualRepository(),
                new AssinaturaDocumentoRepository(),
                new AssinaturaRepository(),
                new AuditLogRepository(),
                new ContratoStorageService()
            );
    }

    /**
     * Lista as assinaturas cadastradas.
     */
    public function index(): void
    {
        $assinaturas =
            $this->service->listar();

        $resumo = [
            'total' => count($assinaturas),
            'pendentes' => 0,
            'trial' => 0,
            'ativas' => 0,
            'atrasadas' => 0,
            'suspensas' => 0,
            'canceladas' => 0,
        ];

        foreach ($assinaturas as $assinatura) {
            if (!is_array($assinatura)) {
                continue;
            }

            $status =
                $assinatura['status']
                ?? null;

            if (!is_string($status)) {
                continue;
            }

            switch ($status) {
                case 'PENDENTE_ATIVACAO':
                    $resumo['pendentes']++;
                    break;

                case 'TRIAL':
                    $resumo['trial']++;
                    break;

                case 'ATIVA':
                    $resumo['ativas']++;
                    break;

                case 'ATRASADA':
                    $resumo['atrasadas']++;
                    break;

                case 'SUSPENSA':
                    $resumo['suspensas']++;
                    break;

                case 'CANCELADA':
                    $resumo['canceladas']++;
                    break;
            }
        }

        $vencimentosAtencao =
            $this->service
                ->listarVencimentos(
                    2
                );

        $resumo['vencimentos'] =
            count(
                $vencimentosAtencao
            );

        $this->render(
            'assinaturas/index.php',
            'Assinaturas',
            self::ACTIVE_MENU,
            [
                'assinaturas' =>
                    $assinaturas,

                'resumo' =>
                    $resumo,

                'success' =>
                    $this->consumeFlash(
                        '_flash_success'
                    ),

                'error' =>
                    $this->consumeFlash(
                        '_flash_error'
                    ),

                'pageStyles' =>
                    self::PAGE_STYLES,
            ]
        );
    }

    /**
     * Exibe o formulário de cadastro.
     */
    public function create(): void
    {
        $this->renderCreateForm(
            [],
            $this->emptyFormData()
        );
    }

    /**
     * Processa o cadastro.
     */
    public function store(): void
    {
        $this->enforceCsrf();

        $context =
            $this->requestContext();

        $result =
            $this->service->cadastrar(
                $this->formInput(
                    $_POST
                ),
                $context['usuario_id'],
                $context['ip'],
                $context['user_agent']
            );

        if (
            ($result['success'] ?? false)
            !== true
        ) {
            $this->renderCreateForm(
                $this->resultErrors(
                    $result
                ),
                $this->resultFormData(
                    $result
                ),
                422
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Assinatura criada com sucesso e aguardando ativação.'
        );

        /*
         * POST → 303 → GET.
         *
         * Evita reenvio do formulário
         * ao atualizar a página.
         */
        $this->redirect(
            '/assinaturas',
            303
        );
    }



    /**
     * Detalhes e histórico financeiro da assinatura.
     */
    public function show(
        string $id
    ): void {
        $assinaturaId =
            $this->validateId(
                $id
            );

        $result =
            $this->service
                ->detalhar(
                    $assinaturaId
                );

        if ($result === null) {
            throw new HttpException(
                404,
                'Assinatura não encontrada.'
            );
        }

        $documentos =
            $this->documentoService
                ->prepararPainelDaAssinatura(
                    $assinaturaId
                );

        if ($documentos === null) {
            throw new HttpException(
                404,
                'Assinatura não encontrada.'
            );
        }

        $this->render(
            'assinaturas/show.php',
            'Detalhes da assinatura',
            self::ACTIVE_MENU,
            [
                'assinatura' =>
                    $result['assinatura'],

                'pagamentos' =>
                    $result['pagamentos'],

                'documentosContratuais' =>
                    $documentos,

                'success' =>
                    $this->consumeFlash(
                        '_flash_success'
                    ),

                'error' =>
                    $this->consumeFlash(
                        '_flash_error'
                    ),

                'pageStyles' =>
                    self::PAGE_STYLES,
            ]
        );
    }

    /**
     * Cancelamento definitivo da assinatura.
     */
    public function cancel(
        string $id
    ): void {
        $assinaturaId =
            $this->validateId(
                $id
            );

        $this->enforceCsrf();

        $confirmacao =
            $_POST['confirmacao']
            ?? null;

        if ($confirmacao !== '1') {
            Session::set(
                '_flash_error',
                'Confirme explicitamente o cancelamento da assinatura.'
            );

            $this->redirect(
                '/assinaturas/'
                    . $assinaturaId,
                303
            );
        }

        $motivo =
            $_POST['motivo']
            ?? '';

        $context =
            $this->requestContext();

        $result =
            $this->service
                ->cancelar(
                    $assinaturaId,
                    is_string($motivo)
                        ? $motivo
                        : '',
                    $context['usuario_id'],
                    $context['ip'],
                    $context['user_agent']
                );

        if (
            ($result['not_found'] ?? false)
            === true
        ) {
            throw new HttpException(
                404,
                'Assinatura não encontrada.'
            );
        }

        $message =
            isset($result['message'])
            && is_string($result['message'])
                ? $result['message']
                : 'Não foi possível cancelar a assinatura.';

        Session::set(
            ($result['success'] ?? false)
                === true
                    ? '_flash_success'
                    : '_flash_error',
            $message
        );

        $this->redirect(
            '/assinaturas/'
                . $assinaturaId,
            303
        );
    }

    /**
     * Ativa uma assinatura pendente.
     */
    public function activate(
        string $id
    ): void {
        $assinaturaId =
            $this->validateId(
                $id
            );

        $this->enforceCsrf();

        $context =
            $this->requestContext();

        $result =
            $this->service->ativar(
                $assinaturaId,
                $context['usuario_id'],
                $context['ip'],
                $context['user_agent']
            );

        if (
            ($result['not_found'] ?? false)
            === true
        ) {
            throw new HttpException(
                404,
                'Assinatura não encontrada.'
            );
        }

        if (
            ($result['success'] ?? false)
            !== true
        ) {
            $message =
                isset($result['message'])
                && is_string($result['message'])
                    ? $result['message']
                    : 'A assinatura não pôde ser ativada.';

            Session::set(
                '_flash_error',
                $message
            );

            $this->redirect(
                '/assinaturas',
                303
            );
        }

        Session::set(
            '_flash_success',
            'Assinatura ativada com sucesso.'
        );

        $this->redirect(
            '/assinaturas',
            303
        );
    }




    /**
     * Processa a régua de inadimplência.
     *
     * É POST porque altera estados financeiros.
     */
    public function processDelinquency(): void
    {
        $this->enforceCsrf();

        $context =
            $this->requestContext();

        $result =
            $this->service
                ->processarInadimplencia(
                    $context['usuario_id'],
                    $context['ip'],
                    $context['user_agent']
                );

        $atrasadas =
            isset($result['atrasadas'])
                ? (int) $result['atrasadas']
                : 0;

        $suspensas =
            isset($result['suspensas'])
                ? (int) $result['suspensas']
                : 0;

        $falhas =
            isset($result['falhas'])
            && is_array($result['falhas'])
                ? count($result['falhas'])
                : 0;

        if (
            ($result['executado'] ?? true)
            !== true
        ) {
            Session::set(
                '_flash_error',
                'A atualização financeira já está sendo executada por outro processo.'
            );
        } elseif ($falhas > 0) {
            Session::set(
                '_flash_error',
                'A atualização foi concluída parcialmente. '
                    . $falhas
                    . ' assinatura'
                    . ($falhas === 1 ? '' : 's')
                    . ' precisa'
                    . ($falhas === 1 ? '' : 'm')
                    . ' de revisão técnica.'
            );
        } elseif (
            $atrasadas === 0
            && $suspensas === 0
        ) {
            Session::set(
                '_flash_success',
                'Situação financeira atualizada. Nenhuma assinatura precisou mudar de status.'
            );
        } else {
            Session::set(
                '_flash_success',
                'Situação financeira atualizada: '
                    . $atrasadas
                    . ' marcada'
                    . ($atrasadas === 1 ? '' : 's')
                    . ' como atrasada'
                    . ($atrasadas === 1 ? '' : 's')
                    . ' e '
                    . $suspensas
                    . ' suspensa'
                    . ($suspensas === 1 ? '' : 's')
                    . '.'
            );
        }

        $this->redirect(
            '/assinaturas',
            303
        );
    }

    /**
     * Central de vencimentos.
     */
    public function vencimentos(): void
    {
        $vencimentos =
            $this->service
                ->listarVencimentos(
                    2
                );

        $resumo = [
            'total' =>
                count($vencimentos),

            'hoje' => 0,
            'proximos' => 0,
            'vencidos' => 0,
        ];

        foreach ($vencimentos as $item) {
            if (!is_array($item)) {
                continue;
            }

            $situacao =
                $item['situacao_vencimento']
                ?? null;

            if ($situacao === 'HOJE') {
                $resumo['hoje']++;
            } elseif ($situacao === 'PROXIMA') {
                $resumo['proximos']++;
            } elseif ($situacao === 'VENCIDA') {
                $resumo['vencidos']++;
            }
        }

        $this->render(
            'assinaturas/vencimentos.php',
            'Vencimentos',
            self::ACTIVE_MENU,
            [
                'vencimentos' =>
                    $vencimentos,

                'resumo' =>
                    $resumo,

                'success' =>
                    $this->consumeFlash(
                        '_flash_success'
                    ),

                'error' =>
                    $this->consumeFlash(
                        '_flash_error'
                    ),

                'pageStyles' =>
                    self::PAGE_STYLES,
            ]
        );
    }

    /**
     * Gera o lembrete e redireciona para o WhatsApp.
     *
     * É POST porque a geração do lembrete entra na auditoria.
     */
    public function whatsappReminder(
        string $id
    ): void {
        $assinaturaId =
            $this->validateId(
                $id
            );

        $this->enforceCsrf();

        $context =
            $this->requestContext();

        $result =
            $this->service
                ->gerarLembreteWhatsapp(
                    $assinaturaId,
                    $context['usuario_id'],
                    $context['ip'],
                    $context['user_agent']
                );

        if (
            ($result['not_found'] ?? false)
            === true
        ) {
            throw new HttpException(
                404,
                'Assinatura não encontrada.'
            );
        }

        if (
            ($result['success'] ?? false)
            !== true
        ) {
            $message =
                isset($result['message'])
                && is_string($result['message'])
                    ? $result['message']
                    : 'Não foi possível preparar o lembrete por WhatsApp.';

            Session::set(
                '_flash_error',
                $message
            );

            $this->redirect(
                '/assinaturas/vencimentos',
                303
            );
        }

        $url =
            $result['url']
            ?? null;

        if (
            !is_string($url)
            || !str_starts_with(
                $url,
                'https://wa.me/'
            )
        ) {
            throw new RuntimeException(
                'URL externa de WhatsApp inválida.'
            );
        }

        header(
            'Location: ' . $url,
            true,
            303
        );

        exit;
    }

    /**
     * Exibe a confirmação manual de pagamento.
     */
    public function payment(
        string $id
    ): void {
        $assinaturaId =
            $this->validateId(
                $id
            );

        $result =
            $this->service
                ->prepararPagamento(
                    $assinaturaId
                );

        if (
            ($result['not_found'] ?? false)
            === true
        ) {
            throw new HttpException(
                404,
                'Assinatura não encontrada.'
            );
        }

        if (
            ($result['success'] ?? false)
            !== true
        ) {
            $message =
                isset($result['message'])
                && is_string($result['message'])
                    ? $result['message']
                    : 'O pagamento não está disponível para esta assinatura.';

            Session::set(
                '_flash_error',
                $message
            );

            $this->redirect(
                '/assinaturas',
                303
            );
        }

        $assinatura =
            $result['assinatura']
            ?? null;

        if (!is_array($assinatura)) {
            throw new RuntimeException(
                'Dados da assinatura não disponíveis para confirmação de pagamento.'
            );
        }

        $this->render(
            'assinaturas/payment.php',
            'Registrar pagamento',
            self::ACTIVE_MENU,
            [
                'assinatura' =>
                    $assinatura,

                'errors' => [],

                'formData' => [
                    'metodo' => '',
                ],

                'pageStyles' =>
                    self::PAGE_STYLES,
            ]
        );
    }

    /**
     * Processa o registro manual do pagamento.
     */
    public function storePayment(
        string $id
    ): void {
        $assinaturaId =
            $this->validateId(
                $id
            );

        $this->enforceCsrf();

        $context =
            $this->requestContext();

        $metodo =
            $_POST['metodo']
            ?? '';

        $result =
            $this->service
                ->registrarPagamento(
                    $assinaturaId,
                    [
                        'metodo' =>
                            is_string($metodo)
                                ? $metodo
                                : '',
                    ],
                    $context['usuario_id'],
                    $context['ip'],
                    $context['user_agent']
                );

        if (
            ($result['not_found'] ?? false)
            === true
        ) {
            throw new HttpException(
                404,
                'Assinatura não encontrada.'
            );
        }

        if (
            ($result['success'] ?? false)
            !== true
        ) {
            $prepared =
                $this->service
                    ->prepararPagamento(
                        $assinaturaId
                    );

            $assinatura =
                $prepared['assinatura']
                ?? null;

            if (!is_array($assinatura)) {
                $message =
                    isset($result['message'])
                    && is_string($result['message'])
                        ? $result['message']
                        : 'O pagamento não pôde ser registrado.';

                Session::set(
                    '_flash_error',
                    $message
                );

                $this->redirect(
                    '/assinaturas',
                    303
                );
            }

            http_response_code(
                422
            );

            $errors =
                isset($result['errors'])
                && is_array($result['errors'])
                    ? $result['errors']
                    : [];

            if (
                $errors === []
                && isset($result['message'])
                && is_string($result['message'])
            ) {
                $errors['geral'] =
                    $result['message'];
            }

            $this->render(
                'assinaturas/payment.php',
                'Registrar pagamento',
                self::ACTIVE_MENU,
                [
                    'assinatura' =>
                        $assinatura,

                    'errors' =>
                        $errors,

                    'formData' => [
                        'metodo' =>
                            is_string($metodo)
                                ? $metodo
                                : '',
                    ],

                    'pageStyles' =>
                        self::PAGE_STYLES,
                ]
            );

            return;
        }

        Session::set(
            '_flash_success',
            'Pagamento registrado com sucesso. A próxima cobrança foi atualizada.'
        );

        $this->redirect(
            '/assinaturas',
            303
        );
    }

    /**
     * Renderiza o formulário de criação sempre com
     * clientes e catálogo recarregados do banco.
     */
    private function renderCreateForm(
        array $errors,
        array $formData,
        int $statusCode = 200
    ): void {
        if ($statusCode !== 200) {
            http_response_code(
                $statusCode
            );
        }

        $opcoes =
            $this->service
                ->opcoesCadastro();

        $empresas =
            isset($opcoes['empresas'])
            && is_array(
                $opcoes['empresas']
            )
                ? $opcoes['empresas']
                : [];

        $catalogo =
            isset($opcoes['catalogo'])
            && is_array(
                $opcoes['catalogo']
            )
                ? $opcoes['catalogo']
                : [];

        $this->render(
            'assinaturas/create.php',
            'Nova assinatura',
            self::ACTIVE_MENU,
            [
                'empresas' =>
                    $empresas,

                'catalogo' =>
                    $catalogo,

                'errors' =>
                    $errors,

                'formData' =>
                    $formData,

                'pageStyles' =>
                    self::PAGE_STYLES,

                'pageScripts' => [
                    'assinaturas.js',
                ],
            ]
        );
    }

    /**
     * Whitelist explícita dos campos aceitos.
     *
     * Qualquer campo adicional enviado manualmente
     * pelo navegador é descartado antes do Service.
     */
    private function formInput(
        array $source
    ): array {
        $input = [];

        foreach (
            self::FORM_FIELDS
            as $field
        ) {
            $value =
                $source[$field]
                ?? '';

            $input[$field] =
                is_string($value)
                    ? $value
                    : '';
        }

        return $input;
    }

    private function emptyFormData(): array
    {
        return [
            'empresa_id' => '',
            'produto_id' => '',
            'plano_id' => '',
            'inicio_em' => '',
        ];
    }

    private function resultErrors(
        array $result
    ): array {
        $errors =
            $result['errors']
            ?? null;

        return is_array($errors)
            ? $errors
            : [];
    }

    private function resultFormData(
        array $result
    ): array {
        $data =
            $result['data']
            ?? null;

        if (!is_array($data)) {
            return $this->emptyFormData();
        }

        return [
            'empresa_id' =>
                $this->scalarToString(
                    $data['empresa_id']
                    ?? null
                ),

            'produto_id' =>
                $this->scalarToString(
                    $data['produto_id']
                    ?? null
                ),

            'plano_id' =>
                $this->scalarToString(
                    $data['plano_id']
                    ?? null
                ),

            'inicio_em' =>
                is_string(
                    $data['inicio_em']
                    ?? null
                )
                    ? $data['inicio_em']
                    : '',
        ];
    }

    private function scalarToString(
        mixed $value
    ): string {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $value;
        }

        return '';
    }


    private function validateId(
        string $id
    ): int {
        $validated = filter_var(
            $id,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        if ($validated === false) {
            throw new HttpException(
                404,
                'Assinatura não encontrada.'
            );
        }

        return $validated;
    }

    /**
     * Valida CSRF das operações POST.
     */
    private function enforceCsrf(): void
    {
        $token =
            $_POST['_token']
            ?? null;

        Csrf::enforce(
            is_string($token)
                ? $token
                : null
        );
    }

    /**
     * Retorna somente contexto seguro
     * necessário para auditoria.
     *
     * @return array{
     *     usuario_id: int,
     *     ip: ?string,
     *     user_agent: ?string
     * }
     */
    private function requestContext(): array
    {
        $auth =
            Session::get(
                'auth'
            );

        if (!is_array($auth)) {
            throw new RuntimeException(
                'Usuário autenticado não identificado.'
            );
        }

        $rawUserId =
            $auth['user_id']
            ?? null;

        if (is_int($rawUserId)) {
            $usuarioId =
                $rawUserId;
        } elseif (
            is_string($rawUserId)
            && ctype_digit(
                $rawUserId
            )
        ) {
            $usuarioId =
                (int) $rawUserId;
        } else {
            $usuarioId = 0;
        }

        if ($usuarioId <= 0) {
            throw new RuntimeException(
                'Usuário autenticado não identificado.'
            );
        }

        /*
         * Enquanto não houver proxy reverso
         * confiável configurado, não utilizamos
         * X-Forwarded-For.
         */
        $ip =
            $_SERVER['REMOTE_ADDR']
            ?? null;

        if (
            !is_string($ip)
            || filter_var(
                $ip,
                FILTER_VALIDATE_IP
            ) === false
        ) {
            $ip = null;
        }

        $userAgent =
            $_SERVER['HTTP_USER_AGENT']
            ?? null;

        if (is_string($userAgent)) {
            $userAgent =
                mb_substr(
                    $userAgent,
                    0,
                    1000,
                    'UTF-8'
                );
        } else {
            $userAgent = null;
        }

        return [
            'usuario_id' =>
                $usuarioId,

            'ip' =>
                $ip,

            'user_agent' =>
                $userAgent,
        ];
    }

    private function consumeFlash(
        string $key
    ): ?string {
        $value =
            Session::get(
                $key
            );

        Session::remove(
            $key
        );

        return is_string($value)
            ? $value
            : null;
    }

    /**
     * Renderiza a view dentro do layout principal.
     */
    private function render(
        string $view,
        string $title,
        string $activeMenu,
        array $data = []
    ): void {
        $auth =
            Session::get(
                'auth'
            );

        $userName =
            is_array($auth)
            && isset($auth['name'])
            && is_string(
                $auth['name']
            )
                ? $auth['name']
                : 'Usuário';

        $roles =
            is_array($auth)
            && isset($auth['roles'])
            && is_array(
                $auth['roles']
            )
                ? $auth['roles']
                : [];

        $userRole =
            implode(
                ', ',
                array_filter(
                    $roles,
                    static fn(
                        mixed $role
                    ): bool =>
                        is_string($role)
                        && $role !== ''
                )
            );

        $csrfToken =
            Csrf::token();

        $appUrl =
            rtrim(
                Env::required(
                    'APP_URL'
                ),
                '/'
            );

        /*
         * Somente as variáveis explicitamente
         * fornecidas pelo Controller chegam à view.
         */
        extract(
            $data,
            EXTR_SKIP
        );

        ob_start();

        require dirname(
            __DIR__,
            2
        )
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . $view;

        $content =
            ob_get_clean();

        if (!is_string($content)) {
            $content = '';
        }

        require dirname(
            __DIR__,
            2
        )
            . DIRECTORY_SEPARATOR
            . 'views'
            . DIRECTORY_SEPARATOR
            . 'layouts'
            . DIRECTORY_SEPARATOR
            . 'main.php';
    }

    /**
     * Redirecionamento interno controlado.
     */
    private function redirect(
        string $path,
        int $statusCode = 302
    ): void {
        $appUrl =
            rtrim(
                Env::required(
                    'APP_URL'
                ),
                '/'
            );

        $normalizedPath =
            '/'
            . ltrim(
                $path,
                '/'
            );

        header(
            'Location: '
            . $appUrl
            . $normalizedPath,
            true,
            $statusCode
        );

        exit;
    }
}
