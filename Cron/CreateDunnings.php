<?php

namespace Ibertrand\BankSync\Cron;

use Exception;
use Ibertrand\BankSync\Helper\Config;
use Ibertrand\BankSync\Helper\Dunning;
use Ibertrand\BankSync\Logger\Logger;
use Ibertrand\BankSync\Model\ResourceModel\Dunning as DunningResourceModel;
use Magento\Cron\Model\Schedule;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory;
use Magento\Store\Model\StoreManager;

class CreateDunnings
{
    public function __construct(
        protected readonly Config $config,
        protected readonly Dunning $dunningHelper,
        protected readonly CollectionFactory $invoiceCollectionFactory,
        protected readonly DunningResourceModel $dunningResourceModel,
        protected readonly StoreManager $storeManager,
        protected readonly Logger $logger,
    ) {
    }

    /**
     * @param int $storeId
     * @return bool
     */
    protected function hasEnabledTypes(int $storeId): bool
    {
        return !empty($this->dunningHelper->getEnabledDunningTypes($storeId));
    }

    /**
     * @param Schedule $schedule
     * @return void
     * @throws Exception
     */
    public function execute(Schedule $schedule): void
    {
        $count = 0;
        foreach ($this->storeManager->getStores() as $store) {
            $this->logger->info('Sending dunnings for store ' . $store->getName());
            $count += $this->createStoreDunnings($store->getId());
        }
        $schedule->setMessages("Sent $count dunnings");
    }

    /**
     * @param int $storeId
     * @return int
     * @throws AlreadyExistsException
     * @throws Exception
     */
    protected function createStoreDunnings(int $storeId): int
    {
        if (!$this->dunningHelper->isEnabled($storeId) || !$this->hasEnabledTypes($storeId)) {
            $this->logger->info('Dunning is disabled for store ' . $storeId);
            return 0;
        }

        $openInvoices = $this->dunningHelper->getOpenInvoices($storeId);
        $this->logger->info("Found " . $openInvoices->count() . " potential invoices to create dunnings for");
        $count = 0;
        foreach ($openInvoices as $invoice) {
            if ($dunning = $this->dunningHelper->getDunningToSend($invoice)) {
                $count++;
                if ($this->dunningHelper->isAutoSendMailEnabled($storeId)) {
                    $dunning->sendMail();
                }
                $this->dunningResourceModel->save($dunning);
            }
        }
        $this->logger->info("Sent $count dunnings");
        return $count;
    }
}
