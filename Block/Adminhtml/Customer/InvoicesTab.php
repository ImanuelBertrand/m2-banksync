<?php

namespace Ibertrand\BankSync\Block\Adminhtml\Customer;

use Magento\Backend\Block\Template\Context;
use Magento\Customer\Controller\RegistryConstants;
use Magento\Framework\Registry;
use Magento\Ui\Component\Layout\Tabs\TabWrapper;

/**
 * Ajax-loaded "Invoices" tab on the admin customer edit page.
 *
 * Mirrors the native Orders tab (\Magento\Sales\Block\Adminhtml\CustomerOrdersTab): the grid body is
 * fetched lazily from the banksync/customer/invoices controller.
 */
class InvoicesTab extends TabWrapper
{
    /**
     * Tab position. Magento\Ui\Component\Layout\Tabs auto-assigns positions in steps of 10 in module
     * load order (Customer View=20, Account Information=30, Addresses=40, Orders=50, Carts=60, …) and
     * reads a tab block's sort_order when set (Tabs::addWrappedBlock). 55 slots this tab between Orders
     * and Carts — i.e. directly below Orders.
     */
    private const TAB_SORT_ORDER = 55;

    /**
     * @var bool
     */
    protected $isAjaxLoaded = true;

    public function __construct(
        Context $context,
        private readonly Registry $coreRegistry,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setData('sort_order', self::TAB_SORT_ORDER);
    }

    /**
     * Only show the tab when a customer is being edited (same guard as the Orders tab).
     */
    #[\Override]
    public function canShowTab()
    {
        return (bool) $this->coreRegistry->registry(RegistryConstants::CURRENT_CUSTOMER_ID);
    }

    #[\Override]
    public function getTabLabel()
    {
        return __('Invoices');
    }

    #[\Override]
    public function getTabUrl()
    {
        return $this->getUrl('banksync/customer/invoices', ['_current' => true]);
    }
}
