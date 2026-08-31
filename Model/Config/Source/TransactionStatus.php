<?php

namespace Ibertrand\BankSync\Model\Config\Source;

use Ibertrand\BankSync\Model\Transaction;
use Magento\Framework\Data\OptionSourceInterface;

class TransactionStatus implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Transaction::STATUS_BOOKED, 'label' => __('Booked')],
            ['value' => Transaction::STATUS_ARCHIVED, 'label' => __('Archived')],
            ['value' => Transaction::STATUS_IGNORED, 'label' => __('Ignored')],
        ];
    }
}
