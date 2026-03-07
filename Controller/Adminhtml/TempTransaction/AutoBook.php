<?php

namespace Ibertrand\BankSync\Controller\Adminhtml\TempTransaction;

class AutoBook extends MassBook
{
    protected function getIds(): ?array
    {
        return empty($this->getRequest()->getParam('id'))
            ? null
            : [$this->getRequest()->getParam('id')];
    }
}
