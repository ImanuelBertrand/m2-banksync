<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\TempTransaction;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;

class Search extends Action
{
    public const ADMIN_RESOURCE = 'Ibertrand_BankSync::search_documents';

    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->prepend(__('Select document'));
        return $resultPage;
    }
}
