<?php

namespace Ibertrand\BankSync\Block\Adminhtml;

use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\EntityInterface;
use Magento\Sales\Model\Order\Invoice;

class DunningBlockButton extends Container
{
    public function __construct(
        Context $context,
        protected readonly Registry $registry,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    protected function _construct()
    {
        $isBlocked = $this->invoiceIsBlocked();
        $this->addButton(
            'banksync_block_dunning_button',
            [
                'label' => $this->getLabel(),
                'class' => 'dunning_block',
                'data_attribute' => [
                    'mage-init' => [
                        'Ibertrand_BankSync/js/dunning-block' => [
                            'url' => $this->getUrl('banksync/dunning/block', ['invoice_id' => $this->getInvoiceId()]),
                            'setBlocked' => $isBlocked ? 0 : 1,
                            'commentRequired' => !$isBlocked,
                            'title' => $this->getLabel(),
                            'content' => $isBlocked
                                ? (string) __('Comment (optional):')
                                : (string) __('Please enter the reason for the dunning block:'),
                        ],
                    ],
                ],
            ],
            sortOrder: -5,
        );

        parent::_construct();
    }

    /**
     * @return string
     */
    protected function getLabel(): string
    {
        return $this->invoiceIsBlocked()
            ? __('Remove dunning block')
            : __('Dunning block');
    }

    /**
     * @return bool
     */
    protected function invoiceIsBlocked(): bool
    {
        return !empty($this->getInvoice()->getBanksyncDunningBlockedAt());
    }

    /**
     * @return Invoice
     */
    protected function getInvoice(): EntityInterface
    {
        return $this->registry->registry('current_invoice');
    }

    /**
     * @return integer
     */
    protected function getInvoiceId(): int
    {
        return $this->getInvoice()->getId();
    }
}
