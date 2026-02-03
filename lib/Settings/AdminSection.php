<?php

declare(strict_types=1);

namespace OCA\OOMonitor\Settings;

use OCP\IL10N;
use OCP\Settings\ISection;

class AdminSection implements ISection {
    private IL10N $l10n;

    public function __construct(IL10N $l10n) {
        $this->l10n = $l10n;
    }

    public function getID(): string {
        return 'oo_monitor';
    }

    public function getName(): string {
        return $this->l10n->t('OO Monitor');
    }

    public function getPriority(): int {
        return 70;
    }

    public function getIcon(): string {
        return '';
    }
}
