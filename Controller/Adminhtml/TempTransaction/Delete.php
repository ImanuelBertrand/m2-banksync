<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\TempTransaction;

use Exception;
use Ibertrand\BankSync\Helper\Config;
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
        protected readonly Config $config,
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
        return empty($idParam)
            ? $this->filter->getCollection($this->collectionFactory->create())
            : $this->collectionFactory->create()->addFieldToFilter('entity_id', $idParam);
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        // Deleting is not part of the normal workflow: it loses the record, and the transaction comes
        // back the next time its month is re-imported. Ignoring is the supported way out.
        if (!$this->config->isDeletionAllowed()) {
            $this->messageManager->addErrorMessage(
                __(
                    'Deleting transactions is disabled. Ignore them instead, or enable deletion under '
                    . 'Stores > Configuration > Sales > BankSync > General Configuration.',
                ),
            );

            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
        }

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
                __('Error occurred while deleted the transaction(s). Check the logs for more details.'),
            );
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
    }
}
