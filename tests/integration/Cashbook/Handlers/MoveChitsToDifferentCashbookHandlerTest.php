<?php

declare(strict_types=1);

namespace Model\Cashbook\Handlers;

use Cake\Chronos\Date;
use Mockery as m;
use Model\Cashbook\Cashbook;
use Model\Cashbook\Cashbook\CashbookType;
use Model\Cashbook\Commands\Cashbook\MoveChitsToDifferentCashbook;
use Model\Cashbook\ICategory;
use Model\Cashbook\Operation;
use Model\Cashbook\Repositories\ICashbookRepository;

final class MoveChitsToDifferentCashbookHandlerTest extends \CommandHandlerTest
{

    private const TARGET_CASHBOOK_ID = 2;
    private const SOURCE_CASHBOOK_ID = 1;

    /** @var ICashbookRepository */
    private $cashbooks;


    public function testMovingChits(): void
    {
        $type = CashbookType::get(CashbookType::EVENT);
        $this->cashbooks->save(new Cashbook(self::TARGET_CASHBOOK_ID, $type));
        $sourceCashbook = new Cashbook(self::SOURCE_CASHBOOK_ID, $type);

        for($i = 0; $i < 3; $i++) {
            $sourceCashbook->addChit(NULL, new Date(), NULL, new Cashbook\Amount('100'), 'test', $this->mockCategory());
        }

        $this->cashbooks->save($sourceCashbook);

        $this->commandBus->handle(
            new MoveChitsToDifferentCashbook([1, 3], self::SOURCE_CASHBOOK_ID, self::TARGET_CASHBOOK_ID)
        );

        $this->entityManager->clear();

        $sourceCashbook = $this->cashbooks->find(self::SOURCE_CASHBOOK_ID);
        $targetCashbook = $this->cashbooks->find(self::TARGET_CASHBOOK_ID);

        $this->assertCount(1, $sourceCashbook->getChits());
        $this->assertCount(2, $targetCashbook->getChits());
    }

    protected function getTestedEntites(): array
    {
        return [
            Cashbook::class,
            Cashbook\Chit::class,
        ];
    }

    protected function _before()
    {
        $this->tester->useConfigFiles([__DIR__ . '/MoveChitsToDifferentCashbookHandlerTest.neon']);
        parent::_before();
        $this->cashbooks = $this->tester->grabService(ICashbookRepository::class);
    }

    private function mockCategory(): ICategory
    {
        return m::mock(ICategory::class, [
            'getId' => 123,
            'getOperationType' => Operation::get(Operation::INCOME),
        ]);
    }

}
