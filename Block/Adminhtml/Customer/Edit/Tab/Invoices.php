<?php

namespace Ibertrand\BankSync\Block\Adminhtml\Customer\Edit\Tab;

use Ibertrand\BankSync\Service\InvoicePaymentStatus;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Helper\Data;
use Magento\Customer\Controller\RegistryConstants;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory;

/**
 * Invoice grid shown on the "Invoices" tab of the admin customer edit page.
 *
 * Modelled on \Magento\Customer\Block\Adminhtml\Edit\Tab\Orders, but for invoices. The "Payment
 * received" column reflects BankSync's is_banksynced flag via {@see InvoicePaymentStatus}.
 *
 * Neither sales_invoice nor sales_invoice_grid carries customer_id, so we filter by joining
 * sales_order on order_id. Using the invoice entity collection (main_table = sales_invoice) also
 * exposes is_banksynced directly, independent of the grid-sync mapping.
 */
class Invoices extends Extended
{
    public function __construct(
        Context $context,
        Data $backendHelper,
        private readonly CollectionFactory $invoiceCollectionFactory,
        private readonly Registry $coreRegistry,
        private readonly InvoicePaymentStatus $paymentStatus,
        array $data = [],
    ) {
        parent::__construct($context, $backendHelper, $data);
    }

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setId('banksync_customer_invoices_grid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('desc');
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $customerId = (int) $this->coreRegistry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);

        $collection = $this->invoiceCollectionFactory->create();
        $collection->getSelect()->join(
            ['sales_order' => $collection->getTable('sales_order')],
            'main_table.order_id = sales_order.entity_id',
            ['order_increment_id' => 'increment_id'],
        )->where('sales_order.customer_id = ?', $customerId);

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('increment_id', [
            'header' => __('Invoice #'),
            'index' => 'increment_id',
        ]);

        $this->addColumn('order_increment_id', [
            'header' => __('Order #'),
            'index' => 'order_increment_id',
            'filter_index' => 'sales_order.increment_id',
        ]);

        $this->addColumn('created_at', [
            'header' => __('Invoice Date'),
            'index' => 'created_at',
            'type' => 'datetime',
        ]);

        $this->addColumn('grand_total', [
            'header' => __('Grand Total'),
            'index' => 'grand_total',
            'type' => 'currency',
            'currency' => 'order_currency_code',
        ]);

        $this->addColumn('is_banksynced', [
            'header' => __('Payment received'),
            'index' => 'is_banksynced',
            'type' => 'options',
            'options' => [0 => __('No'), 1 => __('Yes')],
            // Must be a [widget, method] array, not a first-class-callable/Closure:
            // Grid\Column::getRowField() only runs the callback when is_array($frameCallback).
            'frame_callback' => [$this, 'decorateBanksynced'],
        ]);

        return parent::_prepareColumns();
    }

    /**
     * Render the BankSync payment status as a coloured grid badge (green = paid, red = unpaid).
     *
     * @param string $value
     * @param Invoice $row
     * @param \Magento\Backend\Block\Widget\Grid\Column $column
     * @param bool $isExport
     * @return string
     */
    public function decorateBanksynced($value, Invoice $row, $column, $isExport)
    {
        if ($isExport) {
            return $value;
        }

        $isPaid = $this->paymentStatus->isInvoicePaid($row);
        $class = $isPaid ? 'grid-severity-notice' : 'grid-severity-critical';
        $label = $isPaid ? __('Yes') : __('No');

        return '<span class="' . $class . '"><span>' . $label . '</span></span>';
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('banksync/customer/invoices', ['_current' => true]);
    }

    /**
     * @param Invoice $row
     * @return string
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('sales/order_invoice/view', ['invoice_id' => $row->getId()]);
    }
}
