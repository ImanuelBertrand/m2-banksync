<?php

namespace Ibertrand\BankSync\Helper;

use Magento\Customer\Model\Customer;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;

class Display extends AbstractHelper
{

    private UrlInterface $urlBuilder;
    private Matching $matching;

    public function __construct(
        Context      $context,
        UrlInterface $urlBuilder,
        Matching     $matching,
    ) {
        parent::__construct($context);
        $this->urlBuilder = $urlBuilder;
        $this->matching = $matching;
    }

    /**
     * @param Order $order
     * @return string
     */
    public function getCustomerNamesForListing(Order $order): string
    {
        $billing = $order->getBillingAddress();
        $shipping = $order->getShippingAddress();
        return implode(
            '<br>',
            array_filter(array_unique([
                trim(($order->getCustomerFirstName() ?? "") . ' ' . ($order->getCustomerLastname() ?? "")),
                trim(($billing->getFirstname() ?? "") . ' ' . ($billing->getLastname() ?? "")),
                trim($billing->getCompany() ?? ""),
                trim(($shipping->getFirstname() ?? "") . ' ' . ($shipping->getLastname() ?? "")),
                trim($shipping->getCompany() ?? ""),
            ]))
        );
    }

    /**
     * @param DataObject $object
     * @return string
     */
    public function getUrl(DataObject $object): string
    {
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $id = $object->getId();
        if (empty($id)) {
            return '';
        }

        $classMappings = [
            Invoice::class => ['invoice_id', 'sales/invoice/view'],
            Creditmemo::class => ['creditmemo_id', 'sales/creditmemo/view'],
            Order::class => ['order_id', 'sales/order/view'],
            Customer::class => ['id', 'customer/index/edit'],
        ];

        foreach ($classMappings as $class => [$idName, $route]) {
            if ($object instanceof $class) {
                return $this->urlBuilder->getUrl($route, [$idName => $id]);
            }
        }

        return '';
    }


    /**
     * @param DataObject $object
     * @param array      $matchedTexts
     * @return string
     */
    public function getObjectLink(DataObject $object, array $matchedTexts = []): string
    {
        $url = $this->getUrl($object);
        if (empty($url)) {
            return '';
        }

        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $incrementId = $object->getIncrementId();
        if (empty($incrementId)) {
            return '';
        }

        $cssClass = in_array($incrementId, array_keys($matchedTexts)) ? 'banksync-matched-text' : '';
        return "<a class='$cssClass' href='$url'>$incrementId</a>";
    }

    /**
     * @param string $match
     * @return string
     */
    public function getHighlightPattern(string $match): string
    {
        return $this->matching->getMatchPattern($match);
    }

    /**
     * @param string $fullText
     * @param string $highlightPart
     * @return string
     */
    public function highLightMatch(string $fullText, string $highlightPart): string
    {
        return preg_replace(
            $this->getHighlightPattern($highlightPart),
            '<span class="banksync-matched-text">$0</span>',
            $fullText
        );
    }
}
