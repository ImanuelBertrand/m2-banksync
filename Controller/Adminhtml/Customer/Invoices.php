<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Customer\Controller\RegistryConstants;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Registry;

/**
 * Ajax endpoint that renders the invoice grid for the customer "Invoices" tab.
 *
 * Registers CURRENT_CUSTOMER_ID (as the native customer/index/orders controller does) so the grid
 * block can filter invoices by the currently edited customer, then renders the
 * banksync_customer_invoices layout handle. Accepts GET and POST because the grid ajax posts.
 */
class Invoices extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Sales::sales_invoice';

    public function __construct(
        Context $context,
        private readonly Registry $coreRegistry,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $customerId = (int) $this->getRequest()->getParam('id');
        $this->coreRegistry->register(RegistryConstants::CURRENT_CUSTOMER_ID, $customerId, true);

        return $this->resultFactory->create(ResultFactory::TYPE_LAYOUT);
    }
}
