<?php

namespace Ibertrand\BankSync\Console\Command;

use Ibertrand\BankSync\Helper\Config;
use Ibertrand\BankSync\Service\Matcher;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBarFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TestCommand
 */
class MatchTransactions extends Command
{
    public function __construct(
        protected readonly Matcher $matcher,
        protected readonly Config $config,
        protected readonly ProgressBarFactory $progressBarFactory,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('banksync:match')
            ->setDescription('Match newly imported bank transfers with invoices / credit memos')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Match all transactions');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->config->isEnabled()) {
            $output->writeln('BankSync is disabled');
            return Cli::RETURN_FAILURE;
        }
        $progressBar = $this->progressBarFactory->create(['output' => $output]);
        $this->matcher->setProgressCallBack(function ($current, $total) use ($progressBar) {
            $progressBar->setMaxSteps($total);
            $progressBar->setProgress($current);
        });
        $result = $input->getOption('all')
            ? $this->matcher->matchAllTransactions()
            : $this->matcher->matchNewTransactions();
        $output->writeln("");
        $output->writeln($result);
        return Cli::RETURN_SUCCESS;
    }
}
