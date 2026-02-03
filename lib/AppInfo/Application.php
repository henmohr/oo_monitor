<?php

declare(strict_types=1);

namespace OCA\OOMonitor\AppInfo;

if (class_exists(__NAMESPACE__ . '\\Application', false)) {
    return;
}

use OCA\OOMonitor\BackgroundJob\OnlyOfficeCheckJob;
use OCA\OOMonitor\Command\SetIntervalCommand;
use OCA\OOMonitor\Settings\Admin;
use OCA\OOMonitor\Settings\AdminSection;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'oo_monitor';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerBackgroundJob(OnlyOfficeCheckJob::class);
        $context->registerCommand(SetIntervalCommand::class);
        $context->registerSettings(Admin::class);
        $context->registerSection(AdminSection::class);
    }

    public function boot(IBootContext $context): void {
    }
}
