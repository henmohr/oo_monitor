<?php

declare(strict_types=1);

namespace OCA\OOMonitor\Command;

use OCA\OOMonitor\Service\OnlyOfficeMonitor;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetIntervalCommand extends Command {
    protected static $defaultName = 'oo_monitor:set-interval';

    private IConfig $config;
    private OnlyOfficeMonitor $monitor;

    public function __construct(IConfig $config, OnlyOfficeMonitor $monitor) {
        parent::__construct();
        $this->config = $config;
        $this->monitor = $monitor;
    }

    protected function configure(): void {
        $this
            ->setDescription('Get or set OnlyOffice monitor job interval (minutes).')
            ->addArgument('minutes', InputArgument::OPTIONAL, 'Interval in minutes (>=1).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $minutesArg = $input->getArgument('minutes');

        if ($minutesArg === null) {
            $seconds = (int)$this->config->getAppValue('oo_monitor', 'check_interval', '900');
            $minutes = max(1, (int)round($seconds / 60));
            $output->writeln('Current interval: ' . $minutes . ' minutes');
            return Command::SUCCESS;
        }

        $minutes = (int)$minutesArg;
        if ($minutes < 1) {
            $output->writeln('<error>Interval must be >= 1 minute.</error>');
            return Command::FAILURE;
        }

        $this->monitor->updateIntervalMinutes($minutes);
        $output->writeln('Interval updated to ' . $minutes . ' minutes');

        return Command::SUCCESS;
    }
}
