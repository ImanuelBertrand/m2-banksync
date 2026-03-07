<?php

namespace Ibertrand\BankSync\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class MatchConfidence
 *
 * @method int getTempTransactionId()
 * @method $this setTempTransactionId(int $transactionId)
 * @method int getDocumentId()
 * @method $this setDocumentId(int $documentId)
 * @method float getConfidence()
 * @method $this setConfidence(float $matchConfidence)
 */
class MatchConfidence extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(ResourceModel\MatchConfidence::class);
    }

}
