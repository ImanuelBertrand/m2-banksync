<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\TempTransaction;

use Exception;
use Ibertrand\BankSync\Logger\Logger;
use Ibertrand\BankSync\Model\ResourceModel\TempTransaction\Collection;
use Ibertrand\BankSync\Model\ResourceModel\TempTransaction\CollectionFactory;
use Ibertrand\BankSync\Model\TempTransactionRepository;
use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'Ibertrand_BankSync::sub_menu_import';

    public function __construct(
        Action\Context $context,
        protected readonly TempTransactionRepository $tempTransactionRepository,
        protected readonly Filter $filter,
        protected readonly CollectionFactory $collectionFactory,
        protected readonly Logger $logger,
    ) {
        parent::__construct($context);
    }

    /**
     * @return Collection
     * @throws LocalizedException
     */
    protected function getCollection(): Collection
    {
        $idParam = $this->getRequest()->getParam('id');
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return !empty($idParam)
            ? $this->collectionFactory->create()->addFieldToFilter('entity_id', $idParam)
            : $this->filter->getCollection($this->collectionFactory->create());
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        try {
            $tempTransactions = $this->getCollection();
            foreach ($tempTransactions as $tempTransaction) {
                $this->tempTransactionRepository->delete($tempTransaction);
            }
            $msg = count($tempTransactions) > 1
                ? __('The transactions have been deleted successfully.')
                : __('The transaction has been deleted successfully.');
            $this->messageManager->addSuccessMessage($msg);
        } catch (Exception $e) {
            $this->logger->error($e);
            $this->messageManager->addErrorMessage(
                __('Error occurred while deleted the transaction(s). Check the logs for more details.')
            );
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
    }
}
