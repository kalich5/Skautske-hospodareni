<?php


namespace Tests\Integration\Pairing;


use Model\Payment\BankAccount;
use Model\Payment\BankAccountService;
use Model\Payment\Group;
use Model\Payment\IUnitResolver;
use Model\Payment\Repositories\IBankAccountRepository;
use Model\Payment\Repositories\IGroupRepository;

class BankAccountServiceTest extends \IntegrationTest
{

    /** @var BankAccountService */
    private $bankAccountService;

    /** @var IBankAccountRepository */
    private $bankAccounts;

    /** @var IGroupRepository */
    private $groups;


    protected function _before()
    {
        $this->tester->useConfigFiles(['Payment/BankAccountServiceTest.neon']);
        parent::_before();
        $this->bankAccountService = $this->tester->grabService(BankAccountService::class);
        $this->bankAccounts = $this->tester->grabService(IBankAccountRepository::class);
        $this->groups = $this->tester->grabService(IGroupRepository::class);
    }

    public function getTestedEntites(): array
    {
        return [
            BankAccount::class,
            Group::class,
        ];
    }

    public function testDisallowingBankAccountForSubunitsCascadesToGroups()
    {
        $bankAccount = $this->createBankAccount();
        $bankAccount->allowForSubunits();
        $this->bankAccounts->save($bankAccount);

        $this->addGroup(5, $bankAccount);
        $this->addGroup(5, $bankAccount);
        $this->addGroup(10, $bankAccount); // This one belongs to official unit

        $this->bankAccountService->disallowForSubunits($bankAccount->getId());

        $group1 = $this->groups->find(1); // subunit
        $group2 = $this->groups->find(2); // subunit
        $group3 = $this->groups->find(3);

        $this->assertNull($group1->getBankAccountId());
        $this->assertNull($group2->getBankAccountId());
        $this->assertSame(1, $group3->getBankAccountId());
    }

    private function createBankAccount(): BankAccount
    {
        return new BankAccount(
            5, // official id is resolved to 10
            'Název',
            new BankAccount\AccountNumber(NULL, '2000942144', '2010'),
            NULL,
            new \DateTimeImmutable(),
            $this->tester->grabService(IUnitResolver::class)
        );
    }

    private function addGroup(int $unitId, BankAccount $account): void
    {
        $group = new Group(
            $unitId,
            NULL,
            'Nazev',
            NULL,
            NULL,
            NULL,
            NULL,
            new \DateTimeImmutable(),
            new Group\EmailTemplate('', ''),
            NULL,
            $account
        );

        $this->groups->save($group);
    }

}