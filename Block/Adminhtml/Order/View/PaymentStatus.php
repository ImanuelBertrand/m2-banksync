<?php

namespace Ibertrand\BankSync\Block\Adminhtml\Order\View;

use Ibertrand\BankSync\Model\OrderPaymentStatus;
use Ibertrand\BankSync\Service\InvoicePaymentStatus;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;

/**
 * "Payment Status (BankSync)" panel on the admin order view (Information tab, by the payment method).
 *
 * Unlike the customer Invoices tab (a filterable grid of many invoices), this renders a compact
 * per-order list plus an aggregate badge, because an order has only a handful of invoices.
 */
class PaymentStatus extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly InvoicePaymentStatus $paymentStatus,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?Order
    {
        $order = $this->registry->registry('current_order') ?: $this->registry->registry('order');
        return $order instanceof Order ? $order : null;
    }

    public function getPaymentStatus(): ?OrderPaymentStatus
    {
        $order = $this->getOrder();
        return $order instanceof Order ? $this->paymentStatus->forOrder($order) : null;
    }

    public function isInvoicePaid(Invoice $invoice): bool
    {
        return $this->paymentStatus->isInvoicePaid($invoice);
    }

    public function getInvoiceUrl(Invoice $invoice): string
    {
        return $this->getUrl('sales/order_invoice/view', ['invoice_id' => $invoice->getId()]);
    }

    public function formatPrice(float $price): string
    {
        $order = $this->getOrder();
        return $order instanceof Order ? $order->formatPrice($price) : (string) $price;
    }

    /**
     * Grid-severity CSS class for the aggregate state badge (green / orange / red).
     */
    public function getStateBadgeClass(string $state): string
    {
        return match ($state) {
            OrderPaymentStatus::STATE_PAID => 'grid-severity-notice',
            OrderPaymentStatus::STATE_UNPAID => 'grid-severity-critical',
            default => 'grid-severity-minor',
        };
    }

    public function getStateLabel(string $state): Phrase
    {
        return match ($state) {
            OrderPaymentStatus::STATE_PAID => __('Paid'),
            OrderPaymentStatus::STATE_PARTIAL => __('Partially paid'),
            OrderPaymentStatus::STATE_UNPAID => __('Unpaid'),
            default => __('No invoice yet'),
        };
    }
}
