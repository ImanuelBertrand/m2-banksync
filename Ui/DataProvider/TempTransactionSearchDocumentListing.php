<?php

namespace Ibertrand\BankSync\Ui\DataProvider;

use Ibertrand\BankSync\Helper\Display;
use Ibertrand\BankSync\Helper\Matching;
use Ibertrand\BankSync\Model\TempTransaction;
use Ibertrand\BankSync\Model\TempTransactionRepository;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\CollectionFactory as CreditmemoCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory as InvoiceCollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

class TempTransactionSearchDocumentListing extends AbstractDataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        protected readonly UrlInterface $urlBuilder,
        protected readonly InvoiceCollectionFactory $invoiceCollectionFactory,
        protected readonly CreditmemoCollectionFactory $creditmemoCollectionFactory,
        protected readonly TempTransactionRepository $tempTransactionRepository,
        protected readonly OrderCollectionFactory $orderCollectionFactory,
        protected readonly CustomerFactory $customerFactory,
        protected readonly CustomerResource $customerResource,
        protected readonly Http $request,
        protected readonly CustomerCollectionFactory $customerCollectionFactory,
        protected readonly PriceHelper $priceHelper,
        protected readonly Display $display,
        protected readonly Matching $matching,
        array $meta = [],
        array $data = [],
    ) {
        $this->createCollection();

        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $meta,
            $data,
        );
    }

    /**
     * @return TempTransaction
     * @throws NoSuchEntityException
     */
    protected function getTempTransaction(): TempTransaction
    {
        return $this->tempTransactionRepository->getById($this->request->getParam('id'));
    }

    /**
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function createCollection()
    {
        $tempTransaction = $this->getTempTransaction();
        $documentType = $tempTransaction->getDocumentType();

        $this->collection = $documentType
            ? $this->invoiceCollectionFactory->create()
            : $this->creditmemoCollectionFactory->create();
    }

    /**
     * @param int $id
     *
     * @return Invoice|Creditmemo
     */
    public function getDocument(int $id): Invoice|Creditmemo
    {
        /** @var Invoice|Creditmemo $document To silence IDE warnings about mismatching return value */
        $document = $this->collection->getItemById($id);
        return $document;
    }

    /**
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getData()
    {
        $data = parent::getData();

        $tempTransaction = $this->getTempTransaction();

        foreach ($data['items'] as &$item) {
            $document = $this->getDocument($item['entity_id']);
            $order = $document->getOrder();
            $purposeMatches = $this->matching->getPurposeMatches($tempTransaction, $document);

            $item['document_type'] = $tempTransaction->getDocumentType();
            $item['transaction_date'] = $tempTransaction->getTransactionDate();
            $item['transaction_id'] = $tempTransaction->getId();
            $item['increment_id'] = $this->display->getObjectLink($document, $purposeMatches);
            $item['order_increment_id'] = $this->display->getObjectLink($document->getOrder(), $purposeMatches);

            $amountIsMatched = abs(abs($tempTransaction->getAmount()) - $document->getGrandTotal()) < 0.01;
            $amountClass = $amountIsMatched ? 'banksync-matched-text' : '';
            $item['transaction_amount'] = "<span class='$amountClass'>" .
                $this->priceHelper->currency($tempTransaction->getAmount()) . "</span>";
            $item['grand_total'] = "<span class='$amountClass'>{$this->priceHelper->currency($document->getGrandTotal())}</span>";

            $item['transaction_amount_raw'] = $tempTransaction->getAmount();
            $item['grand_total_raw'] = $document->getGrandTotal();

            $customerId = $document->getOrder()->getCustomerId();
            if ($customerId) {
                $customer = $this->customerFactory->create();
                $this->customerResource->load($customer, $customerId);
                $item['customer_increment_id'] = $this->display->getObjectLink($customer, $purposeMatches);
            } else {
                $item['customer_increment_id'] = "-";
            }

            $nameMatches = $this->matching->getNameMatches($tempTransaction, $document);

            $documentName = $this->display->getCustomerNamesForListing($order);
            $purpose = $tempTransaction->getPurpose();
            $payerName = $tempTransaction->getPayerName();

            foreach (array_keys($purposeMatches) as $match) {
                $purpose = $this->display->highLightMatch($purpose, $match);
                $documentName = $this->display->highLightMatch($documentName, $match);
            }
            foreach (array_keys($nameMatches) as $match) {
                $payerName = $this->display->highLightMatch($payerName, $match);
                $documentName = $this->display->highLightMatch($documentName, $match);
            }

            $item['transaction_purpose'] = $purpose;
            $item['customer_name'] = $documentName;
            $item['transaction_payer_name'] = $payerName;
            $item['payment_method'] = $order->getPayment()->getMethodInstance()->getTitle();
            $item['comment'] = $tempTransaction->getComment();
        }

        return $data;
    }

    /**
     * @param Filter $filter
     *
     * @return void
     */
    public function addFilter(Filter $filter)
    {
        if ($filter->getField() === 'order_increment_id') {
            /** @var Order $order */

            $order = $this->orderCollectionFactory->create()
                ->addFieldToFilter('increment_id', [$filter->getConditionType() => $filter->getValue()])
                ->getFirstItem();

            $filter->setField('order_id')
                ->setValue($order->getId());
        }

        if ($filter->getField() === 'customer_increment_id') {
            /** @var Customer $customer */

            $customer = $this->customerCollectionFactory->create()
                ->addFieldToFilter('increment_id', [$filter->getConditionType() => $filter->getValue()])
                ->getFirstItem();

            $orderIds = $this->orderCollectionFactory->create()
                ->addFieldToFilter('customer_id', $customer->getId())
                ->getAllIds();

            $filter->setField('order_id')
                ->setConditionType('in')
                ->setValue(implode(',', $orderIds));
        }

        parent::addFilter($filter);
    }
}
