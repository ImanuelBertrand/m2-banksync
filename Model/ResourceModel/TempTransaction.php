<?php

namespace Ibertrand\BankSync\Model\ResourceModel;

use Ibertrand\BankSync\Model\TempTransaction as TempTransactionModel;
use Ibertrand\BankSync\Model\TempTransactionFactory;
use Ibertrand\BankSync\Model\Transaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

class TempTransaction extends AbstractDb
{
    protected TempTransactionFactory $tempTransactionFactory;

    public function __construct(
        Context                $context,
        TempTransactionFactory $tempTransactionFactory,
        $connectionName = null,
    ) {
        parent::__construct($context, $connectionName);
        $this->tempTransactionFactory = $tempTransactionFactory;
    }

    protected function _construct()
    {
        $this->_init('banksync_temp_transaction', 'entity_id');
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function deleteAll()
    {
        $connection = $this->getConnection();
        $connection->truncateTable($this->getMainTable());
    }

    /**
     * @param Transaction $transaction
     * @return TempTransactionModel
     */
    public function fromTransaction(Transaction $transaction): TempTransactionModel
    {
        return $this->tempTransactionFactory->create()
            ->setCsvSource($transaction->getCsvSource())
            ->setPayerName($transaction->getPayerName())
            ->setTransactionDate($transaction->getTransactionDate())
            ->setPurpose($transaction->getPurpose())
            ->setAmount($transaction->getAmount())
            ->setComment($transaction->getComment())
            ->setPartialHash($transaction->getPartialHash())
            ->setHash($transaction->getHash());
    }
}
