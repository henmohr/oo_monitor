<?php

declare(strict_types=1);

namespace OCA\OOMonitor\BackgroundJob;

use OCA\OOMonitor\Service\OnlyOfficeMonitor;
use OCP\IConfig;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class OnlyOfficeCheckJob extends TimedJob {
    private OnlyOfficeMonitor $monitor;
    private LoggerInterface $logger;

    public function __construct(OnlyOfficeMonitor $monitor, LoggerInterface $logger, IConfig $config) {
        parent::__construct();
        $this->monitor = $monitor;
        $this->logger = $logger;
        $interval = (int)$config->getAppValue('oo_monitor', 'check_interval', '900');
        $interval = $interval > 0 ? $interval : 900;
        $this->setInterval($interval);
    }

    protected function run($argument): void {
        $result = $this->monitor->checkAndReconnect();
        $this->logger->info('OnlyOffice scheduled check', [
            'app' => 'oo_monitor',
            'result' => $result,
        ]);
    }
}
