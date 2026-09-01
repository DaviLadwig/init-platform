<?php

declare(strict_types=1);

namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function provider(): string;

    public function testConnection(): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findCustomersByExternalReference(
        string $externalReference
    ): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findCustomersByDocument(
        string $document
    ): array;

    /**
     * @param array{
     *     name:string,
     *     cpfCnpj:string,
     *     externalReference:string,
     *     email?:string,
     *     mobilePhone?:string,
     *     notificationDisabled?:bool
     * } $customer
     *
     * @return array<string, mixed>
     */
    public function createCustomer(
        array $customer
    ): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findSubscriptionsByExternalReference(
        string $externalReference
    ): array;

    /**
     * @param array{
     *     customer:string,
     *     billingType:string,
     *     value:string,
     *     nextDueDate:string,
     *     cycle:string,
     *     description:string,
     *     externalReference:string
     * } $subscription
     *
     * @return array<string, mixed>
     */
    public function createSubscription(
        array $subscription
    ): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSubscriptionPayments(
        string $gatewaySubscriptionId
    ): array;
}
