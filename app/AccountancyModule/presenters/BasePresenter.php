<?php

declare(strict_types=1);

namespace App\AccountancyModule;

use Model\BaseService;
use Model\Common\UnitId;
use Model\Skautis\SkautisMaintenanceChecker;
use Nette\Security\Identity;
use stdClass;
use function array_keys;
use function assert;

abstract class BasePresenter extends \App\BasePresenter
{
    protected ?string $backlink;

    /**
     * id volane v url, vetsinou id akce
     */
    protected ?int $aid;

    protected UnitId $unitId;

    /*
     * je akci možné upravovat?
     */
    protected bool $isEditable;

    private SkautisMaintenanceChecker $skautisMaintenanceChecker;

    /* camp, event, unit */
    public string $type;

    public function injectSkautisMaintenanceChecker(SkautisMaintenanceChecker $checker) : void
    {
        $this->skautisMaintenanceChecker = $checker;
    }

    protected function startup() : void
    {
        parent::startup();

        if ($this->skautisMaintenanceChecker->isMaintenance()) {
            throw new SkautisMaintenance();
        }

        if (! $this->getUser()->isLoggedIn()) {
            $this->backlink = $this->storeRequest('+ 3 days');
            if ($this->isAjax()) {
                $this->forward(':Auth:ajax', ['backlink' => $this->backlink]);
            } else {
                $this->redirect(':Default:', ['backlink' => $this->backlink]);
            }
        }

        $aid       = $this->getParameter('aid', null);
        $this->aid = $aid !== null ? (int) $aid : null; // Parameters aren't auto-casted to int

        $unitId       = $this->getParameter('unitId', null);
        $this->unitId = new UnitId($unitId !== null ? (int) $unitId : $this->unitService->getUnitId());

        $this->userService->updateLogoutTime();
    }

    /**
     * {@inheritDoc}
     */
    public function flashMessage($message, $type = 'info') : stdClass
    {
        $this->redrawControl('flash');

        return parent::flashMessage($message, $type);
    }

    public function getCurrentUnitId() : UnitId
    {
        return $this->unitId;
    }

    /**
     * @return int[]
     */
    protected function getEditableUnitIds() : array
    {
        $identity = $this->getUser()->getIdentity();

        if ($identity === null) {
            return [];
        }

        assert($identity instanceof Identity);

        /** @var array<int, mixed> $editableUnits */
        $editableUnits = $identity->access[BaseService::ACCESS_EDIT];

        return array_keys($editableUnits);
    }

    public function renderAccessDenied() : void
    {
        $this->template->setFile(__DIR__ . '/../templates/accessDenied.latte');
    }
}
