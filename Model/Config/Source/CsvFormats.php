<?php

namespace Ibertrand\BankSync\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CsvFormats implements OptionSourceInterface
{
    private array $data;

    public function __construct(
        protected readonlyCollectionFactory $collectionFactory,
    ) {
    }

    /**
     * @return array|array[]
     */
    public function toOptionArray()
    {
        $result = [];
        foreach ($this->toArray() as $key => $value) {
            $result[] = [
                'value' => $key,
                'label' => $value,
            ];
        }
        return $result;
    }

    /**
     * @return string[]
     */
    public function toArray(): array
    {
        if (!isset($this->data)) {
            $collection = $this->collectionFactory->create();
            $this->data = [];
            foreach ($collection as $item) {
                $this->data[$item->getId()] = $item->getName();
            }
        }
        return $this->data;
    }
}
