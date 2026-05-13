<?php

declare(strict_types=1);

namespace Ibertrand\BankSync\Controller\Adminhtml\Dunning;

class MassUnarchive extends MassArchive
{
    protected function getArchivedValue(): ?string
    {
        return null;
    }
}
