<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\Dunning;

use Exception;
use Ibertrand\BankSync\Model\Dunning;
use Ibertrand\BankSync\Model\DunningRepository;
use Ibertrand\BankSync\Model\Pdf\Dunning as PdfDunning;
use Ibertrand\BankSync\Model\ResourceModel\Dunning\Collection as DunningCollection;
use Ibertrand\BankSync\Model\ResourceModel\Dunning\CollectionFactory as DunningCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Ui\Component\MassAction\Filter;
use Zend_Pdf_Exception;

class MassPrint extends Action
{
    public const ADMIN_RESOURCE = 'Ibertrand_BankSync::sub_menu_dunnings';

    protected string $redirectUrl = 'banksync/dunning/index';

    public function __construct(
        Context $context,
        protected readonly FileFactory $fileFactory,
        protected readonly Filter $filter,
        protected readonly DunningCollectionFactory $collectionFactory,
        protected readonly PdfDunning $pdfDunning,
        protected readonly DunningRepository $dunningRepository,
    ) {
        parent::__construct($context);
    }

    /**
     * @return DunningCollection
     */
    protected function getCollection(): DunningCollection
    {
        return $this->collectionFactory->create();
    }

    /**
     * @throws Zend_Pdf_Exception
     */
    protected function getPdfContents($collection): string
    {
        return $this->pdfDunning->getPdf($collection->getItems())->render();
    }

    /**
     * @return string
     */
    protected function getFilename(): string
    {
        return __('Dunnings') . ' ' . date('d.m.Y') . '.pdf';
    }

    /**
     * Save collection items to pdf invoices
     *
     * @param AbstractCollection $collection
     *
     * @return ResponseInterface
     * @throws Exception
     */
    public function massAction(AbstractCollection $collection): ResponseInterface
    {
        /** @var Dunning[] $dunnings */
        $dunnings = $collection->getItems();
        $dunnings = array_filter($dunnings, fn ($item) => !$item->getInvoiceIsBlocked());
        $fileContent = ['type' => 'string', 'value' => $this->getPdfContents($dunnings), 'rm' => true];
        foreach ($dunnings as $dunning) {
            /** @var Dunning $dunning */
            $dunning->setSentAt(date('Y-m-d H:i:s'));
            $this->dunningRepository->save($dunning);
        }
        return $this->fileFactory->create(
            $this->getFilename(),
            $fileContent,
            DirectoryList::VAR_DIR,
            'application/pdf'
        );
    }

    /**
     * Execute action
     *
     * @return Redirect|ResponseInterface
     * @throws Exception
     */
    public function execute()
    {
        try {
            /** @var AbstractCollection $collection */
            $collection = $this->filter->getCollection($this->getCollection());
            return $this->massAction($collection);
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            /** @var Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath($this->redirectUrl);
        }
    }
}
