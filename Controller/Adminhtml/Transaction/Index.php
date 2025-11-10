<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\Transaction;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Ibertrand_BankSync::sub_menu_transactions';

    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->prepend(__('Transactions'));

        return $resultPage;
    }
}
