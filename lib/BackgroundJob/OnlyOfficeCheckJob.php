<?php

declare(strict_types=1);

namespace OCA\OOMonitor\BackgroundJob;

use OCA\OOMonitor\Service\OnlyOfficeMonitor;
use OCP\IConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class OnlyOfficeCheckJob extends TimedJob {
    private OnlyOfficeMonitor $monitor;
    private LoggerInterface $logger;

    public function __construct(ITimeFactory $time, OnlyOfficeMonitor $monitor, LoggerInterface $logger, IConfig $config) {
        parent::__construct($time);
        $this->monitor = $monitor;
        $this->logger = $logger;
        $this->setInterval($monitor->getIntervalSeconds());
    }

    protected function run($argument): void {
        $result = $this->monitor->checkAndReconnect();
        $this->logger->info('OnlyOffice scheduled check', [
            'app' => 'oo_monitor',
            'result' => $result,
        ]);
    }
}
