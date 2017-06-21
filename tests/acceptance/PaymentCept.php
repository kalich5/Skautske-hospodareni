<?php
$I = new AcceptanceTester($scenario);

$I->wantTo('create payment group');

$I->resetEmails();
$I->haveInDatabase('pa_smtp', [
    'unitId' => 27266,
    'host' => 'smtp-hospodareni.loc',
    'secure' => '',
    'username' => 'test@hospodareni.loc',
    'password' => '',
    'created' => '2017-06-15 00:00:00',
]);

$I->login();
$I->click('Platby');
$I->waitForText('Přehled plateb');
$I->click('Založit skupinu plateb');
$I->waitForText('Obecná');
$I->click('Obecná');
$I->fillField('Název', 'Jaráky');
$I->click('//option[text()="Vyberte email"]');
$I->click('//option[text()="test@hospodareni.loc"]');
$I->click('Založit skupinu');

$I->see('Zatím zde nejsou žádné platby.');

$page = new \Page\Payment($I);

$I->wantTo('create payments');

$I->amGoingTo('add first payment');
$page->addPayment('Testovací platba 1', NULL, 500);

$I->amGoingTo('add second payment');
$page->addPayment('Testovací platba 2', NULL, 500);

$I->amGoingTo('add third payment');
$page->addPayment('Testovací platba 3', 'frantisekmasa1@gmail.com', 300);

$I->wantTo('complete payment');

$I->amGoingTo('mark second payment as complete');
$I->click('(//*[@title="Zaplaceno"])[2]');

$I->canSeeNumberOfElements('(//*[text()="Připravena"])', 2);
$I->see('Dokončena');

$I->wantTo('send payment email');

$I->amGoingTo('send third payment');
$I->click('//a[contains(@class, \'ui--sendEmail-2\')]');
$I->waitForText('Odeslána');

$page->seeNumberOfPaymentsWithState("Připravena", 1);
$page->seeNumberOfPaymentsWithState("Odeslána", 1);
$page->seeNumberOfPaymentsWithState("Dokončena", 1);

$I->seeEmailCount(1);

$I->wantTo('close and reopen group');
$I->click('Uzavřít');
$I->waitForText('Znovu otevřít');
$I->click('Znovu otevřít');
$I->waitForText('Uzavřít');


$I->amGoingTo('close group');
$I->click("Uzavřít");
