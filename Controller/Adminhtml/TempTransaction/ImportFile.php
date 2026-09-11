<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\TempTransaction;

use Exception;
use Ibertrand\BankSync\Helper\Config;
use Ibertrand\BankSync\Helper\Hashes;
use Ibertrand\BankSync\Lib\Csv;
use Ibertrand\BankSync\Logger\Logger;
use Ibertrand\BankSync\Model\CsvFormat;
use Ibertrand\BankSync\Model\CsvFormatRepository;
use Ibertrand\BankSync\Model\ResourceModel\TempTransaction as TempTransactionResource;
use Ibertrand\BankSync\Model\ResourceModel\TempTransaction\CollectionFactory as TempTransactionCollectionFactory;
use Ibertrand\BankSync\Model\ResourceModel\Transaction\CollectionFactory as TransactionCollectionFactory;
use Ibertrand\BankSync\Model\TempTransactionFactory;
use Ibertrand\BankSync\Model\TempTransactionRepository;
use Ibertrand\BankSync\Service\Matcher;
use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;

class ImportFile extends Action
{
    public const ADMIN_RESOURCE = 'Ibertrand_BankSync::sub_menu_import';

    protected WriteInterface $varDirectory;

    public function __construct(
        Action\Context $context,
        Filesystem $filesystem,
        protected readonly Csv $csvProcessor,
        protected readonly TempTransactionFactory $tempTransactionFactory,
        protected readonly TempTransactionResource $tempTransactionResource,
        protected readonly TempTransactionRepository $tempTransactionRepository,
        protected readonly TempTransactionCollectionFactory $tempTransactionCollectionFactory,
        protected readonly TransactionCollectionFactory $transactionCollectionFactory,
        protected readonly CsvFormatRepository $csvFormatRepository,
        protected readonly Logger $logger,
        protected readonly Matcher $matcher,
        protected readonly Config $config,
        protected readonly Hashes $hashes,
    ) {
        $this->varDirectory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        parent::__construct($context);
    }

    /**
     * Resolve the uploaded file to a path inside the upload directory.
     *
     * The request only controls the file name, never the directory, so no file outside
     * of Upload::UPLOAD_DIR can be read or deleted.
     *
     * @return string Path relative to the var directory
     * @throws LocalizedException
     */
    protected function getUploadedFilePath(): string
    {
        $importFile = $this->getRequest()->getParam('import_file');
        $fileName = is_array($importFile) && isset($importFile[0]['file'])
            ? basename((string) $importFile[0]['file'])
            : '';

        if ($fileName === '' || !preg_match('/^[^.][\w.\-]*\.csv$/iD', $fileName)) {
            throw new LocalizedException(__('File not found.'));
        }

        $path = Upload::UPLOAD_DIR . '/' . $fileName;
        if (!$this->varDirectory->isFile($path)) {
            throw new LocalizedException(__('File not found.'));
        }

        // Resolve symlinks: a link inside the upload directory must not redirect the read.
        $baseDir = realpath($this->varDirectory->getAbsolutePath(Upload::UPLOAD_DIR));
        $realPath = realpath($this->varDirectory->getAbsolutePath($path));
        if ($baseDir === false || $realPath === false || !str_starts_with($realPath, $baseDir . '/')) {
            throw new LocalizedException(__('File not found.'));
        }

        return $path;
    }

    /**
     * @return CsvFormat
     * @throws LocalizedException
     */
    protected function getCsvFormat(): CsvFormat
    {
        try {
            return $this->csvFormatRepository->getById($this->getRequest()->getParam('csv_format'));
        } catch (Exception $e) {
            $this->logger->error($e);
            throw new LocalizedException(__('CSV format not found.'), $e->getCode(), $e);
        }
    }

    /**
     * Write 'after' plugins for this method if you need to add more filters
     *
     * @param array $row
     * @return bool
     */
    public function isRowValid(array $row): bool
    {
        return $row['amount'] >= 0 || $this->config->isSupportCreditmemos();
    }

    /**
     * @return ResponseInterface|Redirect|(Redirect&ResultInterface)|ResultInterface
     */
    public function execute()
    {
        try {
            $csvFileRelativePath = $this->getUploadedFilePath();
            $csvFilePath = $this->varDirectory->getAbsolutePath($csvFileRelativePath);

            $csvFormat = $this->getCsvFormat();
            $csvRows = $csvFormat->loadFile($csvFilePath);

            if ($this->getRequest()->getParam('delete_old')) {
                $this->tempTransactionRepository->deleteAll();
            }

            $newTransactions = [];
            foreach ($csvRows as $csvRow) {
                if (!$this->isRowValid($csvRow)) {
                    continue;
                }

                $transaction = $this->tempTransactionFactory->create([
                    'data' => [
                        'payer_name' => $csvRow['payer_name'],
                        'purpose' => $csvRow['purpose'],
                        'amount' => $csvRow['amount'],
                        'transaction_date' => $csvRow['transaction_date'],
                        'csv_source' => $csvFormat->getName(),
                        'dirty' => 1,
                    ],
                ]);
                $transaction->setHash($this->hashes->calculateHash($transaction));
                $transaction->setHasDataChanges(true);
                $newTransactions[$transaction->getHash()] = $transaction;
            }

            $hashes = array_keys($newTransactions);

            $existingTempHashes = $this->tempTransactionCollectionFactory->create()
                ->addFieldToFilter('hash', ['in' => $hashes])
                ->getColumnValues('hash');
            $existingBookedHashes = $this->transactionCollectionFactory->create()
                ->addFieldToFilter('hash', ['in' => $hashes])
                ->getColumnValues('hash');

            $newHashes = array_diff($hashes, $existingTempHashes, $existingBookedHashes);
            $newHashes = array_combine($newHashes, $newHashes);

            foreach ($newTransactions as $transaction) {
                if (isset($newHashes[$transaction->getHash()])) {
                    $this->tempTransactionResource->save($transaction);
                }
            }
            $this->varDirectory->delete($csvFileRelativePath);

            $this->messageManager->addSuccessMessage(__('CSV file has been imported successfully.'));
            if (!$this->config->isAsyncMatching()) {
                try {
                    $matchMsg = $this->matcher->matchNewTransactions();
                    $this->messageManager->addNoticeMessage($matchMsg);
                } catch (Exception $e) {
                    $this->logger->error($e);
                    $this->messageManager->addErrorMessage(
                        __('Error occurred while matching the transactions. Check the logs for more details.'),
                    );
                }
            } else {
                $this->messageManager->addNoticeMessage(
                    __('Transactions will be matched in the background. Please check the list in a few minutes.'),
                );
            }
        } catch (Exception $e) {
            $this->logger->error($e);
            $this->messageManager->addErrorMessage(
                __('Error occurred while importing the CSV file. Check the logs for more details.'),
            );
            return $this->resultRedirectFactory->create()->setPath('*/*/import');
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
    }
}
