<?php

declare(strict_types=1);

namespace OCA\OOMonitor\Settings;

use OCA\OOMonitor\Service\OnlyOfficeMonitor;
use OCP\Settings\ISettings;
use OCP\AppFramework\Http\TemplateResponse;

class Admin implements ISettings {
    private OnlyOfficeMonitor $monitor;

    public function __construct(OnlyOfficeMonitor $monitor) {
        $this->monitor = $monitor;
    }

    public function getForm(): TemplateResponse {
        $status = $this->monitor->check();
        $meta = $this->monitor->getStatusMeta();

        return new TemplateResponse('oo_monitor', 'status', [
            'status' => $status,
            'meta' => $meta,
        ]);
    }

    public function getSection(): string {
        return 'oo_monitor';
    }

    public function getPriority(): int {
        return 70;
    }
}
