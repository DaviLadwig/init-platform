<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Asaas;

use App\Contracts\PaymentGatewayInterface;
use App\Integrations\Payments\PaymentGatewayException;
use JsonException;

final class AsaasGateway implements PaymentGatewayInterface
{
    private const PROVIDER = 'ASAAS';

    private const ALLOWED_BASE_URLS = [
        'https://api-sandbox.asaas.com/v3',
        'https://api.asaas.com/v3',
    ];

    private const CUSTOMER_ID_PATTERN =
        '/^cus_[A-Za-z0-9_-]{3,116}$/';

    private const SUBSCRIPTION_ID_PATTERN =
        '/^sub_[A-Za-z0-9_-]{3,116}$/';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $userAgent
    ) {
        if (
            !in_array(
                $this->baseUrl,
                self::ALLOWED_BASE_URLS,
                true
            )
        ) {
            throw new PaymentGatewayException(
                'Ambiente Asaas inválido.'
            );
        }

        if (
            trim($this->apiKey) === ''
            || strlen($this->apiKey) < 20
        ) {
            throw new PaymentGatewayException(
                'Credencial Asaas inválida.'
            );
        }

        if (trim($this->userAgent) === '') {
            throw new PaymentGatewayException(
                'User-Agent da integração inválido.'
            );
        }

        if (!extension_loaded('curl')) {
            throw new PaymentGatewayException(
                'A extensão cURL do PHP não está disponível.'
            );
        }
    }

    public function provider(): string
    {
        return self::PROVIDER;
    }

    public function testConnection(): bool
    {
        $response = $this->request(
            'GET',
            '/customers',
            [
                'limit' => 1,
                'offset' => 0,
            ]
        );

        return $response['status'] >= 200
            && $response['status'] < 300;
    }

    public function findCustomersByExternalReference(
        string $externalReference
    ): array {
        $externalReference =
            $this->normalizeExternalReference(
                $externalReference
            );

        $response = $this->request(
            'GET',
            '/customers',
            [
                'limit' => 10,
                'offset' => 0,
                'externalReference' =>
                    $externalReference,
            ]
        );

        return $this->extractList(
            $response['body'],
            self::CUSTOMER_ID_PATTERN,
            'clientes'
        );
    }

    public function findCustomersByDocument(
        string $document
    ): array {
        $document = preg_replace(
            '/\D+/',
            '',
            $document
        );

        if (
            !is_string($document)
            || !in_array(
                strlen($document),
                [11, 14],
                true
            )
        ) {
            throw new PaymentGatewayException(
                'Documento do cliente inválido.'
            );
        }

        $response = $this->request(
            'GET',
            '/customers',
            [
                'limit' => 10,
                'offset' => 0,
                'cpfCnpj' => $document,
            ]
        );

        return $this->extractList(
            $response['body'],
            self::CUSTOMER_ID_PATTERN,
            'clientes'
        );
    }

    public function createCustomer(
        array $customer
    ): array {
        $payload = $this->normalizeCustomerPayload(
            $customer
        );

        $response = $this->request(
            'POST',
            '/customers',
            [],
            $payload
        );

        $body = $response['body'];

        $customerId = $body['id']
            ?? null;

        if (
            !is_string($customerId)
            || preg_match(
                self::CUSTOMER_ID_PATTERN,
                $customerId
            ) !== 1
        ) {
            throw new PaymentGatewayException(
                'O gateway não retornou um identificador de cliente válido.'
            );
        }

        return $body;
    }

    public function findSubscriptionsByExternalReference(
        string $externalReference
    ): array {
        $externalReference =
            $this->normalizeExternalReference(
                $externalReference
            );

        $response = $this->request(
            'GET',
            '/subscriptions',
            [
                'limit' => 10,
                'offset' => 0,
                'externalReference' =>
                    $externalReference,
            ]
        );

        return $this->extractList(
            $response['body'],
            self::SUBSCRIPTION_ID_PATTERN,
            'assinaturas'
        );
    }

    public function createSubscription(
        array $subscription
    ): array {
        $payload =
            $this->normalizeSubscriptionPayload(
                $subscription
            );

        $response = $this->request(
            'POST',
            '/subscriptions',
            [],
            $payload
        );

        $body = $response['body'];

        $subscriptionId =
            $body['id']
            ?? null;

        if (
            !is_string(
                $subscriptionId
            )
            || preg_match(
                self::SUBSCRIPTION_ID_PATTERN,
                $subscriptionId
            ) !== 1
        ) {
            throw new PaymentGatewayException(
                'O gateway não retornou um identificador de assinatura válido.'
            );
        }

        return $body;
    }

    public function listSubscriptionPayments(
        string $gatewaySubscriptionId
    ): array {
        $gatewaySubscriptionId =
            trim(
                $gatewaySubscriptionId
            );

        if (
            preg_match(
                self::SUBSCRIPTION_ID_PATTERN,
                $gatewaySubscriptionId
            ) !== 1
        ) {
            throw new PaymentGatewayException(
                'Identificador de assinatura do gateway inválido.'
            );
        }

        $response = $this->request(
            'GET',
            '/subscriptions/'
                . rawurlencode(
                    $gatewaySubscriptionId
                )
                . '/payments'
        );

        $body = $response['body'];

        $data = $body['data']
            ?? null;

        if (!is_array($data)) {
            throw new PaymentGatewayException(
                'O gateway retornou uma listagem de cobranças inválida.'
            );
        }

        $payments = [];

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = $item['id']
                ?? null;

            if (
                !is_string($id)
                || preg_match(
                    '/^pay_[A-Za-z0-9_-]{3,116}$/',
                    $id
                ) !== 1
            ) {
                continue;
            }

            $payments[] = $item;
        }

        return $payments;
    }

    private function normalizeExternalReference(
        string $externalReference
    ): string {
        $externalReference =
            trim(
                $externalReference
            );

        if (
            $externalReference === ''
            || strlen(
                $externalReference
            ) > 120
            || preg_match(
                '/^[A-Za-z0-9_-]{3,120}$/',
                $externalReference
            ) !== 1
        ) {
            throw new PaymentGatewayException(
                'Referência externa inválida.'
            );
        }

        return $externalReference;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractList(
        array $body,
        string $idPattern,
        string $resourceName
    ): array {
        $data = $body['data']
            ?? null;

        if (!is_array($data)) {
            throw new PaymentGatewayException(
                'O gateway retornou uma listagem de '
                . $resourceName
                . ' inválida.'
            );
        }

        $items = [];

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = $item['id']
                ?? null;

            if (
                !is_string($id)
                || preg_match(
                    $idPattern,
                    $id
                ) !== 1
            ) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $customer
     *
     * @return array<string, mixed>
     */
    private function normalizeCustomerPayload(
        array $customer
    ): array {
        $name = isset($customer['name'])
            && is_string($customer['name'])
                ? trim($customer['name'])
                : '';

        $document = isset($customer['cpfCnpj'])
            && is_string($customer['cpfCnpj'])
                ? preg_replace(
                    '/\D+/',
                    '',
                    $customer['cpfCnpj']
                )
                : null;

        $externalReference =
            isset($customer['externalReference'])
            && is_string(
                $customer['externalReference']
            )
                ? $this->normalizeExternalReference(
                    $customer['externalReference']
                )
                : '';

        if (
            $name === ''
            || strlen($name) > 200
        ) {
            throw new PaymentGatewayException(
                'Nome do cliente inválido.'
            );
        }

        if (
            !is_string($document)
            || !in_array(
                strlen($document),
                [11, 14],
                true
            )
        ) {
            throw new PaymentGatewayException(
                'Documento do cliente inválido.'
            );
        }

        $payload = [
            'name' => $name,
            'cpfCnpj' => $document,
            'externalReference' =>
                $externalReference,
            'notificationDisabled' =>
                isset(
                    $customer['notificationDisabled']
                )
                    ? (bool) $customer[
                        'notificationDisabled'
                    ]
                    : true,
        ];

        $email = isset($customer['email'])
            && is_string($customer['email'])
                ? trim($customer['email'])
                : '';

        if (
            $email !== ''
            && filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) !== false
        ) {
            $payload['email'] = $email;
        }

        $mobilePhone =
            isset($customer['mobilePhone'])
            && is_string(
                $customer['mobilePhone']
            )
                ? preg_replace(
                    '/\D+/',
                    '',
                    $customer['mobilePhone']
                )
                : null;

        if (
            is_string($mobilePhone)
            && in_array(
                strlen($mobilePhone),
                [10, 11],
                true
            )
        ) {
            $payload['mobilePhone'] =
                $mobilePhone;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $subscription
     *
     * @return array<string, mixed>
     */
    private function normalizeSubscriptionPayload(
        array $subscription
    ): array {
        $customer =
            isset($subscription['customer'])
            && is_string(
                $subscription['customer']
            )
                ? trim(
                    $subscription['customer']
                )
                : '';

        if (
            preg_match(
                self::CUSTOMER_ID_PATTERN,
                $customer
            ) !== 1
        ) {
            throw new PaymentGatewayException(
                'Customer do gateway inválido.'
            );
        }

        $billingType =
            isset(
                $subscription['billingType']
            )
            && is_string(
                $subscription['billingType']
            )
                ? strtoupper(
                    trim(
                        $subscription['billingType']
                    )
                )
                : '';

        if (
            !in_array(
                $billingType,
                [
                    'UNDEFINED',
                    'BOLETO',
                    'CREDIT_CARD',
                    'PIX',
                ],
                true
            )
        ) {
            throw new PaymentGatewayException(
                'Forma de cobrança inválida.'
            );
        }

        $cycle =
            isset($subscription['cycle'])
            && is_string(
                $subscription['cycle']
            )
                ? strtoupper(
                    trim(
                        $subscription['cycle']
                    )
                )
                : '';

        if (
            !in_array(
                $cycle,
                [
                    'MONTHLY',
                    'QUARTERLY',
                    'SEMIANNUALLY',
                    'YEARLY',
                ],
                true
            )
        ) {
            throw new PaymentGatewayException(
                'Periodicidade do gateway inválida.'
            );
        }

        $value =
            isset($subscription['value'])
            && is_string(
                $subscription['value']
            )
                ? trim(
                    $subscription['value']
                )
                : '';

        if (
            preg_match(
                '/^\d{1,10}(?:\.\d{1,2})?$/',
                $value
            ) !== 1
            || $this->isZeroMoney(
                $value
            )
        ) {
            throw new PaymentGatewayException(
                'Valor da assinatura inválido.'
            );
        }

        $nextDueDate =
            isset(
                $subscription['nextDueDate']
            )
            && is_string(
                $subscription['nextDueDate']
            )
                ? trim(
                    $subscription['nextDueDate']
                )
                : '';

        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $nextDueDate
            ) !== 1
        ) {
            throw new PaymentGatewayException(
                'Data de vencimento inválida.'
            );
        }

        $description =
            isset(
                $subscription['description']
            )
            && is_string(
                $subscription['description']
            )
                ? trim(
                    $subscription['description']
                )
                : '';

        if (
            $description === ''
            || strlen(
                $description
            ) > 500
        ) {
            throw new PaymentGatewayException(
                'Descrição da assinatura inválida.'
            );
        }

        $externalReference =
            isset(
                $subscription['externalReference']
            )
            && is_string(
                $subscription['externalReference']
            )
                ? $this->normalizeExternalReference(
                    $subscription[
                        'externalReference'
                    ]
                )
                : '';

        return [
            'customer' => $customer,
            'billingType' =>
                $billingType,

            /*
             * A conversão para float existe APENAS na fronteira
             * de serialização JSON porque a API exige "number".
             *
             * Nenhuma soma, divisão, arredondamento ou regra
             * financeira da Init é feita com float.
             */
            'value' => (float) $value,

            'nextDueDate' =>
                $nextDueDate,
            'cycle' => $cycle,
            'description' =>
                $description,
            'externalReference' =>
                $externalReference,
        ];
    }

    private function isZeroMoney(
        string $value
    ): bool {
        $normalized = str_replace(
            '.',
            '',
            $value
        );

        return trim(
            $normalized,
            '0'
        ) === '';
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, mixed>|null $jsonBody
     *
     * @return array{
     *     status:int,
     *     body:array<string,mixed>
     * }
     */
    private function request(
        string $method,
        string $path,
        array $query = [],
        ?array $jsonBody = null
    ): array {
        $normalizedPath = '/'
            . ltrim(
                $path,
                '/'
            );

        $url = $this->baseUrl
            . $normalizedPath;

        if ($query !== []) {
            $queryString = http_build_query(
                $query,
                '',
                '&',
                PHP_QUERY_RFC3986
            );

            if ($queryString !== '') {
                $url .= '?'
                    . $queryString;
            }
        }

        $curl = curl_init();

        if ($curl === false) {
            throw new PaymentGatewayException(
                'Não foi possível iniciar a comunicação com o gateway.'
            );
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CUSTOMREQUEST => strtoupper(
                $method
            ),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: '
                    . $this->userAgent,
                'access_token: '
                    . $this->apiKey,
            ],
        ];

        if ($jsonBody !== null) {
            try {
                $encodedBody = json_encode(
                    $jsonBody,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_PRESERVE_ZERO_FRACTION
                );
            } catch (JsonException) {
                curl_close(
                    $curl
                );

                throw new PaymentGatewayException(
                    'Não foi possível preparar os dados para o gateway.'
                );
            }

            $options[CURLOPT_POSTFIELDS] =
                $encodedBody;
        }

        curl_setopt_array(
            $curl,
            $options
        );

        $rawBody = curl_exec(
            $curl
        );

        $curlError = curl_errno(
            $curl
        );

        $status = (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        curl_close(
            $curl
        );

        if (
            $rawBody === false
            || $curlError !== 0
        ) {
            throw new PaymentGatewayException(
                'Não foi possível comunicar com o gateway de pagamento.'
            );
        }

        try {
            $decoded = json_decode(
                $rawBody,
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new PaymentGatewayException(
                'O gateway retornou uma resposta inválida.'
            );
        }

        if (!is_array($decoded)) {
            throw new PaymentGatewayException(
                'O gateway retornou uma resposta inesperada.'
            );
        }

        if (
            $status < 200
            || $status >= 300
        ) {
            throw new PaymentGatewayException(
                'O gateway recusou a requisição.'
            );
        }

        return [
            'status' => $status,
            'body' => $decoded,
        ];
    }
}
