<?php

namespace Ibertrand\BankSync\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * @method int getEntityId()
 * @method Transaction setEntityId(int $entityId)
 * @method string getCsvSource()
 * @method $this setCsvSource(string $csvSource)
 * @method string getTransactionDate()
 * @method Transaction setTransactionDate(string $transactionDate)
 * @method string getPayerName()
 * @method Transaction setPayerName(string $payerName)
 * @method string getPurpose()
 * @method Transaction setPurpose(string $purpose)
 * @method float getAmount()
 * @method Transaction setAmount(float $amount)
 * @method string|null getComment()
 * @method $this setComment(string $comment)
 * @method string|null getHash()
 * @method $this setHash(string $hash)
 * @method string|null getPartialHash()
 * @method Transaction setPartialHash(string|null $partialTempTransaction)
 * @method int getDocumentId()
 * @method Transaction setDocumentId(int $documentId)
 * @method string getDocumentType()
 * @method Transaction setDocumentType(string $documentType)
 * @method int getMatchConfidence()
 * @method Transaction setMatchConfidence(int $matchConfidence)
 * @method string getStatus()
 * @method Transaction setStatus(string $status)
 * @method string|null getIgnoreReason()
 * @method Transaction setIgnoreReason(?string $reason)
 * @method int|null getIgnoredBy()
 * @method Transaction setIgnoredBy(?int $adminUserId)
 * @method string getCreatedAt()
 * @method Transaction setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method Transaction setUpdatedAt(string $updatedAt)
 */
class Transaction extends AbstractModel
{
    /**
     * Transactions are never deleted, so every one of them ends up here with one of three statuses.
     * Keeping them on record is what lets a seemingly missing payment be accounted for, and what
     * keeps re-importing the same month from bringing them back.
     */

    /** Matched to an invoice or creditmemo. */
    public const STATUS_BOOKED = 'booked';

    /** A customer payment that is kept in the log without a document, usually with a comment. */
    public const STATUS_ARCHIVED = 'archived';

    /** Not a customer payment at all, and nothing we need. */
    public const STATUS_IGNORED = 'ignored';

    protected function _construct()
    {
        $this->_init(ResourceModel\Transaction::class);
    }
}
