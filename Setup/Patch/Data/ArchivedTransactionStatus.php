<?php

namespace Ibertrand\BankSync\Setup\Patch\Data;

use Ibertrand\BankSync\Model\Transaction;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 * Backfills the status of transactions that predate the column.
 *
 * Before the column existed, the only way for a transaction to end up in this table without a
 * document was Booker::archive() - a customer payment kept in the log. Ignoring did not exist yet,
 * so none of these rows are ignored transactions.
 */
class ArchivedTransactionStatus implements DataPatchInterface, PatchRevertableInterface
{
    public function __construct(
        protected readonly ModuleDataSetupInterface $moduleDataSetup,
    ) {}

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return void
     */
    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('banksync_transaction');

        $connection->update(
            $table,
            ['status' => Transaction::STATUS_ARCHIVED],
            ['document_id = 0 OR document_id IS NULL'],
        );

        $connection->update(
            $table,
            ['status' => Transaction::STATUS_BOOKED],
            ['document_id > 0'],
        );
    }

    /**
     * @return void
     */
    public function revert(): void
    {
        // No revert
    }
}
