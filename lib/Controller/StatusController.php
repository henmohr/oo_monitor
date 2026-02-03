<?php

declare(strict_types=1);

namespace OCA\OOMonitor\Controller;

use OCA\OOMonitor\Service\OnlyOfficeMonitor;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class StatusController extends Controller {
    private OnlyOfficeMonitor $monitor;

    public function __construct(string $appName, IRequest $request, OnlyOfficeMonitor $monitor) {
        parent::__construct($appName, $request);
        $this->monitor = $monitor;
    }

    /**
     * @AdminRequired
     */
    public function index(): TemplateResponse {
        $status = $this->monitor->check();
        $meta = $this->monitor->getStatusMeta();

        return new TemplateResponse($this->appName, 'status', [
            'status' => $status,
            'meta' => $meta,
        ]);
    }

    /**
     * @AdminRequired
     */
    public function check(): DataResponse {
        $result = $this->monitor->checkAndReconnect();

        return new DataResponse($result);
    }

    /**
     * @AdminRequired
     */
    public function backup(): DataResponse {
        $result = $this->monitor->backupNow();

        return new DataResponse($result);
    }

    /**
     * @AdminRequired
     */
    public function settings(): DataResponse {
        $outFilePath = (string)$this->request->getParam('outFilePath', '');
        $intervalMinutes = (int)$this->request->getParam('intervalMinutes', 15);

        $meta = $this->monitor->updateSettings($outFilePath, $intervalMinutes);

        return new DataResponse([
            'ok' => true,
            'message' => 'Settings saved',
            'meta' => $meta,
        ]);
    }

    /**
     * @AdminRequired
     */
    public function testFile(): DataResponse {
        $result = $this->monitor->testOutFileAccess();

        return new DataResponse($result);
    }
}
