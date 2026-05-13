<?php

namespace Ibertrand\BankSync\Block\Adminhtml\Order\Invoice\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\Invoice;

class BanksyncStatus extends Template
{
    public function __construct(
        Context $context,
        protected readonly Registry $registry,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getInvoice(): Invoice
    {
        return $this->registry->registry('current_invoice');
    }

    public function isBanksynced(): bool
    {
        return (bool) $this->getInvoice()->getIsBanksynced();
    }
}
