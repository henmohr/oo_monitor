<?php

declare(strict_types=1);

namespace OCA\OOMonitor\Service;

use OCP\Files\IAppData;
use OCP\IConfig;
use OCP\App\IAppManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class OnlyOfficeMonitor {
    private const BACKUP_FILE = 'onlyoffice_backup.json';
    private const BACKUP_FOLDER = 'backups';
    private const OUT_FILE = 'out-oo.txt';
    private const ONLYOFFICE_APP_ID = 'onlyoffice';
    private const CHECK_INTERVAL_MINUTES_KEY = 'check_interval_minutes';
    private const CHECK_INTERVAL_SECONDS_LEGACY_KEY = 'check_interval';
    private const CONFIG_KEYS = [
        'demo',
        'DocumentServerUrl',
        'documentserverInternal',
        'StorageUrl',
        'secret',
        'defFormats',
        'editFormats',
        'sameTab',
        'preview',
        'advanced',
        'cronChecker',
        'versionHistory',
        'protection',
        'customizationChat',
        'customizationCompactHeader',
        'customizationFeedback',
        'customizationForcesave',
        'customizationHelp',
        'customizationToolbarNoTabs',
        'customizationReviewDisplay',
        'customizationTheme',
        'groups',
        'verify_peer_off',
        'jwt_secret',
        'jwt_header',
        'jwt_leeway',
        'settings_error',
        'limit_thumb_size',
        'permissions_modifyFilter',
        'customization_customer',
        'customization_loaderLogo',
        'customization_loaderName',
        'customization_logo',
        'customization_zoom',
        'customization_autosave',
        'customization_goback',
        'customization_macros',
        'customization_plugins',
        'editors_check_interval',
    ];

    private IConfig $config;
    private IAppData $appData;
    private LoggerInterface $logger;
    private IAppManager $appManager;

    public function __construct(IConfig $config, IAppData $appData, LoggerInterface $logger, IAppManager $appManager) {
        $this->config = $config;
        $this->appData = $appData;
        $this->logger = $logger;
        $this->appManager = $appManager;
    }

    public function getStatusMeta(): array {
        $outFile = $this->getOutFilePath();
        $outStatus = $this->getOutFileStatus($outFile);

        return [
            'appEnabled' => $this->appManager->isEnabledForUser('oo_monitor'),
            'outFilePath' => $outFile ?? '(appdata)',
            'outFileStatus' => $outStatus,
            'appdataBackupPath' => $outFile === null ? $this->getAppDataBackupPath() : '',
            'intervalMinutes' => $this->getIntervalMinutes(),
            'lastCheckAt' => $this->config->getAppValue('oo_monitor', 'last_check_at', ''),
            'lastCheckOk' => $this->config->getAppValue('oo_monitor', 'last_check_ok', ''),
            'lastReconnectAt' => $this->config->getAppValue('oo_monitor', 'last_reconnect_at', ''),
            'lastReconnectOk' => $this->config->getAppValue('oo_monitor', 'last_reconnect_ok', ''),
            'history' => $this->getHistory(),
            'lastFileTestAt' => $this->config->getAppValue('oo_monitor', 'last_file_test_at', ''),
            'lastFileTestOk' => $this->config->getAppValue('oo_monitor', 'last_file_test_ok', ''),
            'lastFileTestMessage' => $this->config->getAppValue('oo_monitor', 'last_file_test_message', ''),
        ];
    }

    public function updateSettings(?string $outFilePath, int $intervalMinutes): array {
        $this->updateOutFilePath($outFilePath);
        $this->updateIntervalMinutes($intervalMinutes);

        return $this->getStatusMeta();
    }

    public function updateIntervalMinutes(int $intervalMinutes): void {
        $intervalMinutes = max(1, $intervalMinutes);
        $this->config->setAppValue('oo_monitor', self::CHECK_INTERVAL_MINUTES_KEY, (string)$intervalMinutes);

        $this->logger->info('Interval updated', [
            'app' => 'oo_monitor',
            'interval_minutes' => $intervalMinutes,
        ]);
    }

    public function updateOutFilePath(?string $outFilePath): void {
        if ($outFilePath === null) {
            return;
        }

        $outFilePath = trim($outFilePath);
        if ($outFilePath !== '') {
            $this->config->setAppValue('oo_monitor', 'out_file_path', $outFilePath);
        } else {
            $this->config->deleteAppValue('oo_monitor', 'out_file_path');
        }

        $this->logger->info('Out file path updated', [
            'app' => 'oo_monitor',
            'out_file_path' => $outFilePath,
        ]);
    }

    public function check(): array {
        $result = $this->runOcc(['onlyoffice:documentserver', '--check']);

        $this->logger->info('OnlyOffice check executed', [
            'app' => 'oo_monitor',
            'ok' => $result['ok'],
        ]);
        $this->storeLastCheck($result['ok']);
        $this->storeHistory('check', $result['ok'], $result['message']);

        return [
            'ok' => $result['ok'],
            'message' => $result['message'],
            'output' => $result['output'],
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ];
    }

    public function reconnect(): array {
        $backup = $this->loadBackupFromOutFile();
        if ($backup === null) {
            $backup = $this->loadBackup();
        }
        if ($backup === null) {
            $backup = $this->backupConfig();
        }

        foreach ($backup as $key => $value) {
            $this->config->setAppValue(self::ONLYOFFICE_APP_ID, $key, (string)$value);
        }

        $this->logger->info('OnlyOffice config restored', [
            'app' => 'oo_monitor',
            'source' => $backup === null ? 'none' : 'backup',
            'keys' => array_keys($backup),
        ]);

        $check = $this->runOcc(['onlyoffice:documentserver', '--check']);
        $message = $check['ok'] ? 'OnlyOffice reconnected' : 'OnlyOffice reconnection failed';
        $this->logger->info($message, [
            'app' => 'oo_monitor',
            'output' => $check['output'],
        ]);
        $this->storeLastReconnect($check['ok']);
        $this->storeHistory('reconnect', $check['ok'], $message);

        return [
            'ok' => $check['ok'],
            'message' => $message,
            'output' => $check['output'],
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ];
    }

    public function checkAndReconnect(): array {
        $check = $this->check();
        if ($check['ok']) {
            return $check + [
                'action' => 'check',
                'meta' => $this->getStatusMeta(),
            ];
        }

        $reconnect = $this->reconnect();
        return $reconnect + [
            'action' => 'reconnect',
            'meta' => $this->getStatusMeta(),
        ];
    }

    private function runOcc(array $args): array {
        $phpBinary = $this->config->getSystemValue('php_path', 'php');
        $occPath = rtrim((string)\OC::$SERVERROOT, '/') . '/occ';

        $process = new Process(array_merge([$phpBinary, $occPath], $args));
        $process->setTimeout(30);
        $process->run();

        $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());
        $ok = $process->isSuccessful() && stripos($output, 'successfully') !== false;

        return [
            'ok' => $ok,
            'message' => $ok ? 'OnlyOffice OK' : 'OnlyOffice check failed',
            'output' => $output,
        ];
    }

    public function backupNow(): array {
        try {
            $data = $this->backupConfig();
            $outPath = $this->getOutFilePath();
            if ($outPath !== null) {
                $this->writeOutFile($outPath, $data);
            } else {
                $this->writeOutFileToAppData($data);
            }

            return [
                'ok' => true,
                'message' => 'Backup saved',
                'output' => '',
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Backup failed', ['app' => 'oo_monitor', 'exception' => $e]);
            return [
                'ok' => false,
                'message' => 'Backup failed: ' . $e->getMessage(),
                'output' => '',
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ];
        }
    }

    public function testOutFileAccess(): array {
        $path = $this->getOutFilePath();
        if ($path === null) {
            $ok = $this->testAppDataWrite();
            $message = $ok ? 'Appdata read/write OK' : 'Appdata read/write failed';
            $this->storeLastFileTest($ok, $message);
            return [
                'ok' => $ok,
                'message' => $message,
                'path' => '(appdata)',
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ];
        }

        $dir = dirname($path);
        if (!is_dir($dir) || !is_readable($dir) || !is_writable($dir)) {
            $this->storeLastFileTest(false, 'Directory not readable/writable');
            return [
                'ok' => false,
                'message' => 'Directory not readable/writable',
                'path' => $path,
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ];
        }

        $readOk = true;
        if (is_file($path)) {
            $readOk = is_readable($path);
        }

        $tmp = $dir . '/.oo_monitor_write_test_' . uniqid('', true);
        $writeOk = @file_put_contents($tmp, 'test') !== false;
        if ($writeOk) {
            @unlink($tmp);
        }

        $ok = $readOk && $writeOk;
        $this->storeLastFileTest($ok, $ok ? 'Read/write OK' : 'Read/write failed');

        return [
            'ok' => $ok,
            'message' => $ok ? 'Read/write OK' : 'Read/write failed',
            'path' => $path,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ];
    }

    private function backupConfig(): array {
        $data = [];
        foreach (self::CONFIG_KEYS as $key) {
            $data[$key] = $this->config->getAppValue(self::ONLYOFFICE_APP_ID, $key, '');
        }

        $this->storeBackup($data);
        $this->logger->info('OnlyOffice config backup stored', [
            'app' => 'oo_monitor',
            'count' => count($data),
        ]);

        return $data;
    }

    private function loadBackup(): ?array {
        try {
            $folder = $this->getBackupFolder();
            if (!$folder->fileExists(self::BACKUP_FILE)) {
                return null;
            }

            $file = $folder->getFile(self::BACKUP_FILE);
            $raw = $file->getContent();
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                return null;
            }

            return $data;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to load backup', ['app' => 'oo_monitor', 'exception' => $e]);
            return null;
        }
    }

    private function loadBackupFromOutFile(): ?array {
        $path = $this->getOutFilePath();
        if ($path === null) {
            return $this->loadBackupFromAppDataOutFile();
        }
        if (!is_file($path)) {
            return null;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $this->logger->warning('Failed to read out-oo.txt', ['app' => 'oo_monitor', 'path' => $path]);
            return null;
        }

        $data = [];
        foreach ($lines as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if ($key === '') {
                continue;
            }
            $data[$key] = $value;
        }

        if ($data === []) {
            return null;
        }

        $this->logger->info('Loaded OnlyOffice config from out-oo.txt', [
            'app' => 'oo_monitor',
            'path' => $path,
            'count' => count($data),
        ]);

        return $data;
    }

    private function storeBackup(array $data): void {
        $folder = $this->getBackupFolder();
        $payload = json_encode($data, JSON_PRETTY_PRINT);

        if ($folder->fileExists(self::BACKUP_FILE)) {
            $file = $folder->getFile(self::BACKUP_FILE);
            $file->putContent($payload);
            return;
        }

        $file = $folder->newFile(self::BACKUP_FILE);
        $file->putContent($payload);
    }

    private function getBackupFolder() {
        try {
            return $this->appData->getFolder(self::BACKUP_FOLDER);
        } catch (\Throwable $e) {
            return $this->appData->newFolder(self::BACKUP_FOLDER);
        }
    }

    private function getOutFilePath(): ?string {
        $appPath = $this->config->getAppValue('oo_monitor', 'out_file_path', '');
        $appPath = is_string($appPath) ? trim($appPath) : '';
        if ($appPath !== '') {
            return $appPath;
        }

        $path = $this->config->getSystemValue('oo_monitor_out_file', '');
        $path = is_string($path) ? trim($path) : '';
        return $path !== '' ? $path : null;
    }

    private function loadBackupFromAppDataOutFile(): ?array {
        try {
            $folder = $this->getBackupFolder();
            if (!$folder->fileExists(self::OUT_FILE)) {
                return null;
            }
            $file = $folder->getFile(self::OUT_FILE);
            $raw = $file->getContent();
            $lines = preg_split('/\\R/', $raw);
            if ($lines === false) {
                return null;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to read appdata out-oo.txt', ['app' => 'oo_monitor', 'exception' => $e]);
            return null;
        }

        $data = [];
        foreach ($lines as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if ($key === '') {
                continue;
            }
            $data[$key] = $value;
        }

        if ($data === []) {
            return null;
        }

        $this->logger->info('Loaded OnlyOffice config from appdata out-oo.txt', [
            'app' => 'oo_monitor',
            'count' => count($data),
        ]);

        return $data;
    }

    private function writeOutFile(string $path, array $data): void {
        $lines = [];
        foreach ($data as $key => $value) {
            $lines[] = $key . '=' . $value;
        }
        $payload = implode(PHP_EOL, $lines) . PHP_EOL;
        file_put_contents($path, $payload);

        $this->logger->info('out-oo.txt saved', [
            'app' => 'oo_monitor',
            'path' => $path,
            'count' => count($data),
        ]);
    }

    private function writeOutFileToAppData(array $data): void {
        $folder = $this->getBackupFolder();
        if ($folder->fileExists(self::OUT_FILE)) {
            $file = $folder->getFile(self::OUT_FILE);
            $file->putContent($this->serializeOutFile($data));
            return;
        }

        $file = $folder->newFile(self::OUT_FILE);
        $file->putContent($this->serializeOutFile($data));
    }

    private function serializeOutFile(array $data): string {
        $lines = [];
        foreach ($data as $key => $value) {
            $lines[] = $key . '=' . $value;
        }
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function getOutFileStatus(?string $path): array {
        if ($path === null) {
            $folder = $this->getBackupFolder();
            $exists = $folder->fileExists(self::OUT_FILE);
            return [
                'exists' => $exists,
                'readable' => true,
                'writable' => true,
                'message' => $exists ? 'Using appdata (file exists)' : 'Using appdata (file not created yet)',
            ];
        }

        $exists = is_file($path);
        $readable = $exists ? is_readable($path) : is_readable(dirname($path));
        $writable = $exists ? is_writable($path) : is_writable(dirname($path));

        $message = 'OK';
        if (!$readable || !$writable) {
            $message = 'Permission issue';
        } elseif (!$exists) {
            $message = 'File does not exist yet';
        }

        return [
            'exists' => $exists,
            'readable' => $readable,
            'writable' => $writable,
            'message' => $message,
        ];
    }

    public function getIntervalMinutes(): int {
        $minutes = (int)$this->config->getAppValue('oo_monitor', self::CHECK_INTERVAL_MINUTES_KEY, '');
        if ($minutes > 0) {
            return $minutes;
        }

        $legacySeconds = (int)$this->config->getAppValue('oo_monitor', self::CHECK_INTERVAL_SECONDS_LEGACY_KEY, '900');
        if ($legacySeconds <= 0) {
            $legacySeconds = 900;
        }
        $minutes = (int)max(1, round($legacySeconds / 60));
        // Migrate legacy value (seconds) to minutes
        $this->config->setAppValue('oo_monitor', self::CHECK_INTERVAL_MINUTES_KEY, (string)$minutes);
        return $minutes;
    }

    public function getIntervalSeconds(): int {
        return $this->getIntervalMinutes() * 60;
    }

    private function getAppDataBackupPath(): string {
        $dataDir = (string)$this->config->getSystemValue('datadirectory', '');
        $instanceId = (string)$this->config->getSystemValue('instanceid', '');
        if ($dataDir === '' || $instanceId === '') {
            return '';
        }
        return rtrim($dataDir, '/') . '/appdata_' . $instanceId . '/oo_monitor/backups';
    }

    private function storeLastCheck(bool $ok): void {
        $this->config->setAppValue('oo_monitor', 'last_check_at', (new \DateTimeImmutable())->format('c'));
        $this->config->setAppValue('oo_monitor', 'last_check_ok', $ok ? '1' : '0');
    }

    private function storeLastReconnect(bool $ok): void {
        $this->config->setAppValue('oo_monitor', 'last_reconnect_at', (new \DateTimeImmutable())->format('c'));
        $this->config->setAppValue('oo_monitor', 'last_reconnect_ok', $ok ? '1' : '0');
    }

    private function storeLastFileTest(bool $ok, string $message): void {
        $this->config->setAppValue('oo_monitor', 'last_file_test_at', (new \DateTimeImmutable())->format('c'));
        $this->config->setAppValue('oo_monitor', 'last_file_test_ok', $ok ? '1' : '0');
        $this->config->setAppValue('oo_monitor', 'last_file_test_message', $message);
    }

    private function getHistory(): array {
        try {
            $folder = $this->getBackupFolder();
            if (!$folder->fileExists('history.json')) {
                return [];
            }
            $file = $folder->getFile('history.json');
            $raw = $file->getContent();
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to read history', ['app' => 'oo_monitor', 'exception' => $e]);
            return [];
        }
    }

    private function storeHistory(string $action, bool $ok, string $message): void {
        $history = $this->getHistory();
        $history[] = [
            'ts' => (new \DateTimeImmutable())->format('c'),
            'action' => $action,
            'ok' => $ok,
            'message' => $message,
        ];
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }

        try {
            $folder = $this->getBackupFolder();
            $payload = json_encode($history, JSON_PRETTY_PRINT);
            if ($folder->fileExists('history.json')) {
                $file = $folder->getFile('history.json');
                $file->putContent($payload);
            } else {
                $file = $folder->newFile('history.json');
                $file->putContent($payload);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to write history', ['app' => 'oo_monitor', 'exception' => $e]);
        }
    }

    public function getBackupJson(): string {
        $backup = $this->loadBackup();
        if ($backup === null) {
            $backup = $this->backupConfig();
        }

        return json_encode($backup, JSON_PRETTY_PRINT);
    }

    private function testAppDataWrite(): bool {
        try {
            $folder = $this->getBackupFolder();
            $name = '.oo_monitor_test_' . uniqid('', true);
            $file = $folder->newFile($name);
            $file->putContent('test');
            $file->delete();
            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Appdata write test failed', ['app' => 'oo_monitor', 'exception' => $e]);
            return false;
        }
    }
}
