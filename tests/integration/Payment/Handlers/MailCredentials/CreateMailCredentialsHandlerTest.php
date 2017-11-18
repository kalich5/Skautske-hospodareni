<?php

namespace Model\Payment\Handlers\MailCredentials;

use Model\Payment\Commands\CreateMailCredentials;
use Model\Payment\EmailNotSetException;
use Model\Payment\MailCredentials;
use Model\Payment\MailCredentials\MailProtocol;
use Model\Payment\User;
use Model\Payment\UserRepositoryStub;

class CreateMailCredentialsHandlerTest extends \CommandHandlerTest
{

    /** @var UserRepositoryStub */
    private $users;

    public function _before()
    {
        $this->tester->useConfigFiles([
            'Payment/Handlers/MailCredentials/CreateMailCredentialsHandlerTest.neon',
        ]);
        parent::_before();
        $this->users = $this->tester->grabService(UserRepositoryStub::class);
        $this->tester->resetEmails();
    }

    public function getTestedEntites(): array
    {
        return [
            MailCredentials::class,
        ];
    }

    public function testRecordToDatabaseIsAdded()
    {
        $this->users->setUser(new User(10, 'František Maša', 'test@hospodareni.loc'));

        $this->commandBus->handle($this->getCommand());

        $this->tester->seeInDatabase('pa_smtp', [
            'unitId' => 666,
            'host' => 'smtp-hospodareni.loc',
            'secure' => '',
            'username' => 'test@hospodareni.loc',
            'password' => '',
        ]);
    }

    public function testEmailIsSentToUser()
    {
        $this->users->setUser(new User(10, 'František Maša', 'test@hospodareni.loc'));

        $this->commandBus->handle($this->getCommand());

        $this->tester->seeEmailCount(1);
    }

    public function testExceptionIsThrownForUserWithoutEmail()
    {
        $this->users->setUser(new User(10, 'František Maša', NULL));

        $this->expectException(EmailNotSetException::class);

        $this->commandBus->handle($this->getCommand());
    }

    private function getCommand(): CreateMailCredentials
    {
        return new CreateMailCredentials(
            666,
            'smtp-hospodareni.loc',
            'test@hospodareni.loc',
            '',
            MailProtocol::get(MailProtocol::PLAIN),
            10
        );
    }

}