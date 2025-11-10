<?php

namespace Ibertrand\BankSync\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\Config;

class PaymentMethods implements OptionSourceInterface
{
    protected Config $paymentModelConfig;

    /**
     * PaymentMethods constructor.
     *
     * @param Config $paymentModelConfig
     */
    public function __construct(
        protected readonly Config $paymentModelConfig,
    ) {
        $this->paymentModelConfig = $paymentModelConfig;
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    public function toOptionArray()
    {
        $methods = $this->paymentModelConfig->getActiveMethods();
        $options = [];

        foreach ($methods as $methodCode => $method) {
            $options[] = [
                'value' => $methodCode,
                'label' => $method->getTitle(),
            ];
        }

        return $options;
    }
}
