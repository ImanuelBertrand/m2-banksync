<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\Dunning;

use Ibertrand\BankSync\Logger\Logger;
use Ibertrand\BankSync\Model\Dunning;
use Ibertrand\BankSync\Model\DunningRepository;
use Ibertrand\BankSync\Model\ResourceModel\Dunning\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\InvoiceFactory;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\ResourceModel\Order\Invoice as InvoiceResource;
use Throwable;

class Block extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Ibertrand_BankSync::sub_menu_dunnings';

    public function __construct(
        Context $context,
        protected readonly InvoiceRepository $invoiceRepository,
        protected readonly Logger $logger,
        protected readonly CollectionFactory $dunningCollectionFactory,
        protected readonly DunningRepository $dunningRepository,
        protected readonly OrderStatusHistoryRepositoryInterface $orderHistoryRepository,
        protected readonly ResourceConnection $resourceConnection,
        protected readonly InvoiceFactory $invoiceFactory,
        protected readonly InvoiceResource $invoiceResource,
    ) {
        parent::__construct($context);
    }

    /**
     * @param int $invoiceId
     * @param bool $paidStatus
     * @return void
     * @throws CouldNotSaveException
     */
    private function setDunningPaidStatus(int $invoiceId, bool $paidStatus): void
    {
        $dunnings = $this->dunningCollectionFactory->create()
            ->addFieldToFilter('invoice_id', $invoiceId)
            ->addFieldToFilter('is_paid', $paidStatus ? 0 : 1);

        $paid = $paidStatus ? 1 : 0;
        foreach ($dunnings as $dunning) {
            /** @var Dunning $dunning */
            $dunning->setIsPaid($paid);
            $this->dunningRepository->save($dunning);
        }
    }

    /**
     * @param Invoice $invoice
     * @param bool $setBlocked
     * @param string $comment
     * @return string The audit message
     * @throws CouldNotSaveException
     */
    private function blockInvoice(Invoice $invoice, bool $setBlocked, string $comment): string
    {
        $user = (string) $this->_auth->getUser()?->getUserName();
        $incrementId = $invoice->getIncrementId();
        if ($setBlocked) {
            $invoice->setBanksyncDunningBlockedAt(date('Y-m-d H:i:s'));
            $message = __('Dunning block for invoice %1 set by %2: %3', $incrementId, $user, $comment);
        } else {
            $invoice->setBanksyncDunningBlockedAt(null);
            $message = $comment === ''
                ? __('Dunning block for invoice %1 removed by %2', $incrementId, $user)
                : __('Dunning block for invoice %1 removed by %2: %3', $incrementId, $user, $comment);
        }
        $this->invoiceRepository->save($invoice);

        $order = $invoice->getOrder();
        $order->setHistoryEntityName('invoice');
        $this->orderHistoryRepository->save($order->addCommentToStatusHistory($message->render()));

        $this->setDunningPaidStatus((int) $invoice->getEntityId(), $setBlocked);

        return $message->render();
    }

    /**
     * Reads the current block state with a row lock, so concurrent requests can't both pass the stale check
     *
     * @param int $invoiceId
     * @return bool
     */
    private function lockAndCheckBlocked(int $invoiceId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('sales_invoice'), ['banksync_dunning_blocked_at'])
            ->where('entity_id = ?', $invoiceId)
            ->forUpdate();
        return !empty($connection->fetchOne($select));
    }

    /**
     * @return Redirect
     */
    public function execute()
    {
        $invoiceId = (int) $this->getRequest()->getParam('invoice_id');
        $setBlocked = !empty($this->getRequest()->getParam('set_blocked'));
        $comment = trim((string) $this->getRequest()->getParam('comment'));

        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $this->invoiceRepository->get($invoiceId);
        } catch (InputException|NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('This invoice no longer exists.'));
            return $redirect->setPath('sales/invoice/index');
        }
        $redirect->setPath('sales/invoice/view', ['invoice_id' => $invoiceId]);

        if ($setBlocked && $comment === '') {
            $this->messageManager->addErrorMessage(__('Please enter the reason for the dunning block.'));
            return $redirect;
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            // The requested state comes from the rendered page, which may be stale (other tab, other admin)
            if ($setBlocked === $this->lockAndCheckBlocked($invoiceId)) {
                $connection->rollBack();
                $this->messageManager->addNoticeMessage($setBlocked
                    ? __('The dunning block for this invoice is already set.')
                    : __('The dunning block for this invoice has already been removed.'));
                return $redirect;
            }

            // Load the invoice only now, under the row lock, so saving it can't overwrite concurrent changes
            // to other columns (e.g. is_banksynced set by the Booker)
            $invoice = $this->invoiceFactory->create();
            $this->invoiceResource->load($invoice, $invoiceId);
            if (!$invoice->getId()) {
                throw new NoSuchEntityException(__('This invoice no longer exists.'));
            }

            $message = $this->blockInvoice($invoice, $setBlocked, $comment);
            $connection->commit();
        } catch (Throwable $e) {
            // Only roll back if a transaction is still open, otherwise rollBack() throws and masks the real error
            if ($connection->getTransactionLevel() > 0) {
                $connection->rollBack();
            }
            $this->logger->error(
                'Changing dunning block for invoice ' . $invoiceId . ' failed: ' . $e->getMessage(),
                ['exception' => $e],
            );
            $this->messageManager->addErrorMessage(__('The dunning block could not be changed. Please check the log.'));
            return $redirect;
        }

        $this->logger->info(str_replace(["\r", "\n"], ' ', $message));
        $this->messageManager->addSuccessMessage($setBlocked
            ? __('The dunning block has been set.')
            : __('The dunning block has been removed.'));

        return $redirect;
    }
}
