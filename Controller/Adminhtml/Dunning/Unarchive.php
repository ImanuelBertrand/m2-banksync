<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\Dunning;

use Exception;
use Ibertrand\BankSync\Model\DunningRepository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;

class Unarchive extends Action
{
    public const ADMIN_RESOURCE = 'Ibertrand_BankSync::sub_menu_dunnings';

    public function __construct(
        Context $context,
        protected readonly DunningRepository $dunningRepository,
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Redirect
     * @throws Exception
     */
    public function execute()
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');

        $dunningId = $this->getRequest()->getParam('id');
        if (!$dunningId) {
            return $redirect;
        }
        $dunning = $this->dunningRepository->getById($dunningId);

        if (empty($dunning->getArchivedAt())) {
            $this->messageManager->addErrorMessage(__('The dunning was not archived to begin with.'));
            return $redirect;
        }

        $dunning->setArchivedAt(null);
        $this->dunningRepository->save($dunning);

        $this->messageManager->addSuccessMessage(__('The dunning was unarchived.'));

        return $redirect;
    }
}
