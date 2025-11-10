<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\TempTransaction;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;

class Import extends Action
{
    public const ADMIN_RESOURCE = 'Ibertrand_BankSync::sub_menu_temp_transactions';

    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->prepend(__('Import Transactions'));

        return $resultPage;
    }
}
