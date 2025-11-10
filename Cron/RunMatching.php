<?php

namespace Ibertrand\BankSync\Cron;

use Exception;
use Ibertrand\BankSync\Helper\Config;
use Ibertrand\BankSync\Logger\Logger;
use Ibertrand\BankSync\Service\Matcher;
use Magento\Cron\Model\Schedule;

class RunMatching
{
    public function __construct(
        protected readonly Logger $logger,
        protected readonly Matcher $matcher,
        protected readonly Config $config,
    ) {
    }

    /**
     * @param Schedule $schedule
     *
     * @return void
     */
    public function execute(Schedule $schedule): void
    {
        if (!$this->config->isEnabled()) {
            $schedule->setMessages("BankSync is disabled");
            return;
        }

        if (!$this->config->isAsyncMatching()) {
            $schedule->setMessages("Async matching is disabled");
            return;
        }

        $this->logger->info("Matching new transactions");

        try {
            $schedule->setMessages($this->matcher->matchNewTransactions());
        } catch (Exception $e) {
            $this->logger->error($e);
        }
    }
}
