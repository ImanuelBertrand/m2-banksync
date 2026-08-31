<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\TempTransaction;

use Exception;
use Ibertrand\BankSync\Logger\Logger;
use Ibertrand\BankSync\Model\ResourceModel\TempTransaction\Collection;
use Ibertrand\BankSync\Model\ResourceModel\TempTransaction\CollectionFactory;
use Ibertrand\BankSync\Model\TempTransaction;
use Ibertrand\BankSync\Service\Booker;
use Magento\Backend\App\Action;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Moves temp transactions to the transaction table with status "ignored", instead of deleting them.
 *
 * The row stays on record, so a payment that seems to be missing can still be accounted for, and its
 * hash keeps a re-import of the same month from bringing it back.
 */
class Ignore extends Action
{
    public const ADMIN_RESOURCE = 'Ibertrand_BankSync::book';

    /**
     * The reason for the normal workflow: the money that arrived is not a customer payment.
     */
    public const DEFAULT_REASON = 'Not a customer payment';

    public function __construct(
        Action\Context $context,
        protected readonly Booker $booker,
        protected readonly Filter $filter,
        protected readonly CollectionFactory $collectionFactory,
        protected readonly Session $authSession,
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
        $reason = trim((string) $this->getRequest()->getParam('reason')) ?: self::DEFAULT_REASON;
        $ignoredBy = $this->authSession->getUser()?->getId();

        $ignored = 0;
        $failed = 0;
        foreach ($this->getCollection() as $tempTransaction) {
            /** @var TempTransaction $tempTransaction */
            try {
                $this->booker->ignore($tempTransaction, $reason, $ignoredBy ? (int) $ignoredBy : null);
                $ignored++;
            } catch (Exception $e) {
                $this->logger->error($e);
                $failed++;
            }
        }

        if ($ignored > 0) {
            $this->messageManager->addSuccessMessage(
                __('%1 transaction(s) have been ignored and kept on record.', $ignored),
            );
        }
        if ($failed > 0) {
            $this->messageManager->addErrorMessage(
                __('%1 transaction(s) could not be ignored. Check the logs for more details.', $failed),
            );
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
    }
}
