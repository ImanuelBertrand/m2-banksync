<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\Dunning;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Ibertrand_BankSync::sub_menu_dunnings';

    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->prepend(__('Dunnings'));

        return $resultPage;
    }
}
