<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\Dunning;

use Exception;
use Ibertrand\BankSync\Logger\Logger;
use Ibertrand\BankSync\Model\DunningRepository;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;

class SendMail extends Action
{
    public const ADMIN_RESOURCE = 'Ibertrand_BankSync::sub_menu_dunnings';

    public function __construct(
        Context $context,
        protected readonly DunningRepository $dunningRepository,
        protected readonly Logger $logger,
    ) {
        parent::__construct($context);
    }

    /**
     * @return Redirect
     * @throws NoSuchEntityException
     * @throws InputException
     */
    public function execute()
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath('*/*/index');

        $dunningId = $this->getRequest()->getParam('id');
        $dunning = $this->dunningRepository->getById($dunningId);

        if ($dunning->getInvoiceIsBlocked()) {
            $this->messageManager->addErrorMessage(__('The invoice is blocked. Please unblock it first.'));
            return $redirect;
        }

        try {
            if ($dunning->sendMail()) {
                $this->dunningRepository->save($dunning);
                $this->messageManager->addSuccessMessage(__('The mail has been sent.'));
            } else {
                $this->messageManager->addErrorMessage(__('There was an error sending the mail.'));
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage() . "\n" . $e->getTraceAsString());
            $this->messageManager->addErrorMessage(_('There was an error sending the mail: ') . $e->getMessage());
        }

        return $redirect;
    }
}
