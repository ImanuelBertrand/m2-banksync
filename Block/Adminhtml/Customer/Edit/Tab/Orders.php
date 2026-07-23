<?php

namespace Ibertrand\BankSync\Block\Adminhtml\Customer\Edit\Tab;

use Ibertrand\BankSync\Model\OrderPaymentStatus;
use Ibertrand\BankSync\Service\InvoicePaymentStatus;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Helper\Data;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory;
use Magento\Sales\Helper\Reorder;

/**
 * Adds a "Payment received" column (aggregate BankSync status across the order's invoices) to the
 * Orders grid on the admin customer edit page.
 *
 * Standalone BankSync wires this via a preference on the native grid (etc/di.xml). Where another
 * module already overrides that grid (e.g. Bertrand\Sarto), that override extends this class and
 * calls addPaymentStatusColumn()/addPaymentStatusData() so both column sets appear.
 */
class Orders extends \Magento\Customer\Block\Adminhtml\Edit\Tab\Orders
{
    public function __construct(
        Context $context,
        Data $backendHelper,
        CollectionFactory $collectionFactory,
        Reorder $salesReorder,
        Registry $coreRegistry,
        protected readonly InvoicePaymentStatus $paymentStatus,
        protected readonly ResourceConnection $resourceConnection,
        array $data = [],
    ) {
        parent::__construct($context, $backendHelper, $collectionFactory, $salesReorder, $coreRegistry, $data);
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $result = parent::_prepareColumns();
        $this->addPaymentStatusColumn();
        return $result;
    }

    #[\Override]
    protected function _prepareCollection()
    {
        parent::_prepareCollection();
        $collection = $this->getCollection();
        if ($collection !== null) {
            $this->addPaymentStatusData($collection);
        }
        return $this;
    }

    /**
     * Register the "Payment received" column. Reusable by subclasses that rebuild the column set.
     */
    protected function addPaymentStatusColumn(): void
    {
        $this->addColumn('banksync_payment_status', [
            'header' => __('Payment received'),
            'index' => 'banksync_inv_total',
            'filter' => false,
            'sortable' => false,
            // Must be a [widget, method] array, not a first-class-callable/Closure:
            // Grid\Column::getRowField() only runs the callback when is_array($frameCallback).
            'frame_callback' => [$this, 'decoratePaymentStatus'],
        ]);
    }

    /**
     * Add per-order invoice counts (total + banksynced) to the grid collection via correlated
     * subqueries, so the aggregate status can be derived without loading each order's invoices.
     */
    protected function addPaymentStatusData(Collection $collection): void
    {
        if (!$collection instanceof AbstractDb) {
            return;
        }
        $connection = $collection->getConnection();
        $invoiceTable = $connection->quoteIdentifier($this->resourceConnection->getTableName('sales_invoice'));
        $collection->getSelect()->columns([
            'banksync_inv_total' => new Expression(
                "(SELECT COUNT(*) FROM {$invoiceTable} bsi WHERE bsi.order_id = main_table.entity_id)",
            ),
            'banksync_inv_paid' => new Expression(
                "(SELECT COALESCE(SUM(bsi.is_banksynced), 0) FROM {$invoiceTable} bsi "
                . "WHERE bsi.order_id = main_table.entity_id)",
            ),
        ]);
    }

    /**
     * Render the aggregate BankSync status as a coloured grid badge.
     *
     * @param string $value
     * @param \Magento\Framework\DataObject $row
     * @param \Magento\Backend\Block\Widget\Grid\Column $column
     * @param bool $isExport
     * @return string
     */
    public function decoratePaymentStatus($value, $row, $column, $isExport)
    {
        $total = (int) $row->getData('banksync_inv_total');
        $paid = (int) $row->getData('banksync_inv_paid');
        $state = $this->paymentStatus->stateFromCounts($paid, $total);
        $label = $this->getPaymentStatusLabel($state);

        // No invoices yet: no counts to show, keep the descriptive label.
        if ($state === OrderPaymentStatus::STATE_NONE) {
            if ($isExport) {
                return (string) $label;
            }

            return '<span class="' . $this->getPaymentStatusBadgeClass($state) . '"><span>'
                . $this->escapeHtml((string) $label) . '</span></span>';
        }

        // Show "paid / total" (e.g. "1 / 2") so it is clear at a glance whether every invoice is paid.
        $counts = $paid . ' / ' . $total;

        if ($isExport) {
            return $counts;
        }

        return '<span class="' . $this->getPaymentStatusBadgeClass($state) . '" title="'
            . $this->escapeHtmlAttr((string) $label) . '"><span>' . $this->escapeHtml($counts)
            . '</span></span>';
    }

    protected function getPaymentStatusBadgeClass(string $state): string
    {
        return match ($state) {
            OrderPaymentStatus::STATE_PAID => 'grid-severity-notice',
            OrderPaymentStatus::STATE_UNPAID => 'grid-severity-critical',
            default => 'grid-severity-minor',
        };
    }

    protected function getPaymentStatusLabel(string $state): Phrase
    {
        return match ($state) {
            OrderPaymentStatus::STATE_PAID => __('Paid'),
            OrderPaymentStatus::STATE_PARTIAL => __('Partially paid'),
            OrderPaymentStatus::STATE_UNPAID => __('Unpaid'),
            default => __('No invoice yet'),
        };
    }
}
