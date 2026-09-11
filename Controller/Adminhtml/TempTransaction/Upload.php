<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\TempTransaction;

use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;

class Upload extends Action
{
    public const ADMIN_RESOURCE = 'Ibertrand_BankSync::sub_menu_import';

    public const UPLOAD_DIR = 'tmp/banksync';

    protected WriteInterface $varDirectory;

    public function __construct(
        Action\Context $context,
        Filesystem $filesystem,
        protected readonly UploaderFactory $fileUploaderFactory,
    ) {
        $this->varDirectory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        parent::__construct($context);
    }

    public function execute()
    {
        $target = $this->varDirectory->getAbsolutePath(self::UPLOAD_DIR);
        try {
            $uploader = $this->fileUploaderFactory->create(['fileId' => 'import_file']);
            $uploader->setAllowedExtensions(['csv']); // Set allowed file extensions
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(false);
            $result = $uploader->save($target);

            // The client only needs the stored file name to reference the upload on submit.
            // Absolute paths are re-posted verbatim by the form, which is both an LFI surface
            // and a WAF trigger (OWASP CRS 930120 matches on '/tmp/' and friends).
            unset($result['path'], $result['tmp_name']);

            // 'name' is the raw client-supplied file name; the stored one is sanitized by the
            // uploader and is also the accurate label when a collision triggered a rename.
            $result['name'] = $result['file'];

            return $this->resultFactory->create(ResultFactory::TYPE_JSON)
                ->setData($result);
        } catch (Exception $e) {
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $resultJson->setData(['error' => $e->getMessage(), 'errorcode' => $e->getCode()]);
            return $resultJson;
        }
    }
}
