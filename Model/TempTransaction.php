<?php

namespace Ibertrand\BankSync\Model;

use Ibertrand\BankSync\Model\ResourceModel\MatchConfidence\Collection as MatchConfidenceCollection;
use Ibertrand\BankSync\Model\ResourceModel\MatchConfidence\CollectionFactory as MatchConfidenceCollectionFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\CreditmemoRepository;
use Magento\Sales\Model\Order\InvoiceRepository;

/**
 * @method int getEntityId()
 * @method $this setEntityId(int $entityId)
 * @method string getCsvSource()
 * @method $this setCsvSource(string $csvSource)
 * @method string|null getTransactionDate()
 * @method $this setTransactionDate(string $transactionDate)
 * @method string|null getPayerName()
 * @method $this setPayerName(string $payerName)
 * @method string|null getPurpose()
 * @method $this setPurpose(string $purpose)
 * @method string|null getHash()
 * @method $this setHash(string $hash)
 * @method float|null getAmount()
 * @method $this setAmount(float $amount)
 * @method string|null getComment()
 * @method $this setComment(string $comment)
 * @method int|null getMatchConfidence()
 * @method $this setMatchConfidence(?int $matchConfidence)
 * @method int|null getDocumentCount()
 * @method $this setDocumentCount(int $ids)
 * @method string|null getPartialHash()
 * @method $this setPartialHash(string|null $partialTempTransaction)
 * @method int getDirty()
 * @method $this setDirty(int $dirty)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $updatedAt)
 */
class TempTransaction extends AbstractModel
{
    const DIRTY = 1;
    const NOT_DIRTY = 0;

    public function __construct(
        Context                          $context,
        Registry                         $registry,
        InvoiceRepository                $invoiceRepository,
        CreditmemoRepository             $creditmemoRepository,
        MatchConfidenceCollectionFactory $matchConfidenceCollectionFactory,
        AbstractResource                 $resource = null,
        AbstractDb                       $resourceCollection = null,
        array                            $data = [],
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->invoiceRepository = $invoiceRepository;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->matchConfidenceCollectionFactory = $matchConfidenceCollectionFactory;
    }

    protected function _construct()
    {
        $this->_init('Ibertrand\BankSync\Model\ResourceModel\TempTransaction');
    }

    /**
     * @return string
     */
    public function getDocumentType(): string
    {
        return $this->getAmount() > 0 ? 'invoice' : 'creditmemo';
    }

    /**
     * @return MatchConfidenceCollection
     */
    public function getMatchCollection(): MatchConfidenceCollection
    {
        return $this->matchConfidenceCollectionFactory->create()
            ->addFieldToFilter('temp_transaction_id', $this->getId());
    }
}
