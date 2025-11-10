<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\Dunning;

use Exception;
use Ibertrand\BankSync\Logger\Logger;
use Ibertrand\BankSync\Model\Dunning;
use Ibertrand\BankSync\Model\DunningRepository;
use Ibertrand\BankSync\Model\ResourceModel\Dunning\Collection as DunningCollection;
use Ibertrand\BankSync\Model\ResourceModel\Dunning\CollectionFactory as DunningCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Ui\Component\MassAction\Filter;

class MassArchive extends Action
{
    public const ADMIN_RESOURCE = 'Ibertrand_BankSync::sub_menu_dunnings';

    protected string $redirectUrl = 'banksync/dunning/index';

    public function __construct(
        Context $context,
        protected readonly Filter $filter,
        protected readonly DunningCollectionFactory $collectionFactory,
        protected readonly DunningRepository $dunningRepository,
        protected readonly Logger $logger,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return Redirect
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
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath($this->redirectUrl);
        }
    }

    /**
     * @return DunningCollection
     */
    protected function getCollection(): DunningCollection
    {
        return $this->collectionFactory->create();
    }

    /**
     * Save collection items to pdf invoices
     *
     * @param AbstractCollection $collection
     *
     * @return Redirect
     */
    public function massAction(AbstractCollection $collection): Redirect
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath($this->redirectUrl);

        /** @var Dunning[] $dunnings */
        $dunnings = $collection->getItems();
        if (count($dunnings) == 0) {
            $this->messageManager->addErrorMessage('No dunnings selected.');
            return $resultRedirect;
        }

        $failed = 0;
        $success = 0;
        $value = $this->getArchivedValue();
        foreach ($dunnings as $dunning) {
            try {
                $dunning->setArchivedAt($value);
                $this->dunningRepository->save($dunning);
                $success += 1;
            } catch (Exception $e) {
                $this->logger->error("Failed to archive dunning: {$e->getMessage()}\n{$e->getTraceAsString()}");
                $failed += 1;
            }
        }
        if ($failed > 0 && $success > 0) {
            $this->messageManager->addErrorMessage($failed . ' dunning(s) could not be archived. ' . $success . ' dunning(s) archived.');
        } elseif ($failed > 0) {
            $this->messageManager->addErrorMessage($failed . ' dunning(s) could not be archived.');
        } elseif ($success > 0) {
            $this->messageManager->addSuccessMessage($success . ' dunning(s) archived.');
        }

        return $resultRedirect;
    }

    protected function getArchivedValue(): ?string
    {
        return date('Y-m-d H:i:s');
    }
}
