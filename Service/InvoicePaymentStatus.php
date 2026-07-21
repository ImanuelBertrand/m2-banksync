<?php

namespace Ibertrand\BankSync\Service;

use Ibertrand\BankSync\Model\OrderPaymentStatus;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;

/**
 * Single source of truth for "has this invoice / order been paid according to BankSync?".
 *
 * The paid flag is the is_banksynced column on sales_invoice, set by
 * {@see \Ibertrand\BankSync\Service\Booker} when a bank transaction is booked against an invoice.
 *
 * Reused by the order view panel, and (later) the order grid and the customer order list, so the
 * paid/partial/unpaid rules stay identical everywhere.
 */
class InvoicePaymentStatus
{
    /**
     * Whether a single invoice has a confirmed bank transaction booked against it.
     */
    public function isInvoicePaid(Invoice $invoice): bool
    {
        return (bool) $invoice->getData('is_banksynced');
    }

    /**
     * Aggregate the BankSync payment state across all of an order's invoices.
     */
    public function forOrder(Order $order): OrderPaymentStatus
    {
        $invoices = [];
        $paidCount = 0;
        foreach ($order->getInvoiceCollection() as $invoice) {
            $invoices[] = $invoice;
            if ($this->isInvoicePaid($invoice)) {
                $paidCount++;
            }
        }

        return new OrderPaymentStatus($this->stateFromCounts($paidCount, count($invoices)), $paidCount, $invoices);
    }

    /**
     * Derive the aggregate state from paid/total invoice counts.
     *
     * Kept separate from {@see forOrder()} so grid columns that already have the counts (e.g. from a
     * SQL aggregate) can classify an order without loading its invoices.
     */
    public function stateFromCounts(int $paidCount, int $totalCount): string
    {
        return match (true) {
            $totalCount === 0 => OrderPaymentStatus::STATE_NONE,
            $paidCount >= $totalCount => OrderPaymentStatus::STATE_PAID,
            $paidCount === 0 => OrderPaymentStatus::STATE_UNPAID,
            default => OrderPaymentStatus::STATE_PARTIAL,
        };
    }
}
