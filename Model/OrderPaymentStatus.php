<?php

declare(strict_types=1);

namespace Ibertrand\BankSync\Model;

use Magento\Sales\Model\Order\Invoice;

/**
 * Immutable summary of an order's BankSync payment state, aggregated across all its invoices.
 *
 * Produced by {@see \Ibertrand\BankSync\Service\InvoicePaymentStatus::forOrder()} and consumed by the
 * order view panel (and, later, the order grid and the customer order list) so every surface applies
 * the same rules.
 */
class OrderPaymentStatus
{
    public const STATE_PAID = 'paid';
    public const STATE_PARTIAL = 'partial';
    public const STATE_UNPAID = 'unpaid';
    public const STATE_NONE = 'none';

    /**
     * @param Invoice[] $invoices
     */
    public function __construct(
        private readonly string $state,
        private readonly int $paidCount,
        private readonly array $invoices,
    ) {}

    public function getState(): string
    {
        return $this->state;
    }

    public function getPaidCount(): int
    {
        return $this->paidCount;
    }

    public function getTotalCount(): int
    {
        return count($this->invoices);
    }

    /**
     * @return Invoice[]
     */
    public function getInvoices(): array
    {
        return $this->invoices;
    }

    public function hasInvoices(): bool
    {
        return $this->getTotalCount() > 0;
    }

    public function isPaid(): bool
    {
        return $this->state === self::STATE_PAID;
    }
}
