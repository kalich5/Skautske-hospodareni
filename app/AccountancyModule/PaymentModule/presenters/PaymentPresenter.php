<?php

namespace App\AccountancyModule\PaymentModule;

use Nette\Application\UI\Form;

/**
 * @author Hána František <sinacek@gmail.com>
 */
class PaymentPresenter extends BasePresenter {

    protected $notFinalStates;

    /**
     *
     * @var \Model\BankService
     */
    protected $bank;
    protected $readUnits;
    protected $editUnits;
    protected $unitService;

    public function __construct(\Model\PaymentService $paymentService, \Model\BankService $bankService, \Model\UnitService $unitService) {
        parent::__construct($paymentService);
        $this->bank = $bankService;
        $this->unitService = $unitService;
    }

    protected function startup() {
        parent::startup();
        //Kontrola ověření přístupu
        $this->template->notFinalStates = $this->notFinalStates = $this->model->getNonFinalStates();
        //$this->groups = $this->model->getGroupsIn($this->user->getIdentity()->access['read']);
        $this->readUnits = $this->unitService->getReadUnits($this->user);
        $this->editUnits = $this->unitService->getEditUnits($this->user);
    }

    public function renderDefault($onlyOpen = 1) {
        $this->template->onlyOpen = $onlyOpen;
        $this->template->groups = $groups = $this->model->getGroups(array_keys($this->readUnits), $onlyOpen);
        $this->template->payments = $this->model->getAll(array_keys($groups), TRUE);
    }

    public function renderDetail($id) {
        $this->template->units = $this->readUnits;
        $this->template->group = $group = $this->model->getGroup(array_keys($this->readUnits), $id);
        $maxVS = $this->model->getMaxVS($group['id']);
        if (!$group) {
            $this->flashMessage("Nemáte oprávnění zobrazit detail plateb", "warning");
            $this->redirect("Payment:default");
        }
        $form = $this['paymentForm'];
        $form->addSubmit('send', 'Přidat platbu')->setAttribute("class", "btn btn-primary");
        $form->setDefaults(array(
            'amount' => $group['amount'],
            'maturity' => $group['maturity'],
            'ks' => $group['ks'],
            'oid' => $group['id'],
            'vs' => $maxVS != NULL ? $maxVS + 1 : "",
        ));

        $this->template->payments = $payments = $this->model->getAll($id);
        $this->template->summarize = $this->model->summarizeByState($id);
        $paymentsForSendEmail = array_filter($payments, create_function('$p', 'return strlen($p->email)>4 && $p->state == "preparing";'));
        $this->template->isGroupSendActive = ($group->state == 'open') && count($paymentsForSendEmail) > 0;
    }

    public function renderEdit($pid) {
        if (!$this->isEditable) {
            $this->flashMessage("Nemáte oprávnění editovat platbu", "warning");
            $this->redirect("Payment:default");
        }
        $payment = $this->model->get(array_keys($this->editUnits), $pid);
        $form = $this['paymentForm'];
        $form->addSubmit('send', 'Přidat')->setAttribute("class", "btn btn-primary");
        $form->setDefaults(array(
            'name' => $payment->name,
            'email' => $payment->email,
            'amount' => $payment->amount,
            'maturity' => $payment->maturity,
            'vs' => $payment->vs,
            'ks' => $payment->ks,
            'note' => $payment->note,
            'oid' => $payment->groupId,
            'pid' => $payment->id,
        ));
        $this->template->linkBack = $this->link("detail", array("id" => $payment->groupId));
    }

    public function actionMassAdd($id) {
        //ověření přístupu
        $this->template->unitPairs = $this->readUnits;
        $this->template->detail = $detail = $this->model->getGroup(array_keys($this->readUnits), $id);
        $this->template->list = $list = $this->model->getPersons($this->aid, $id); //@todo:?nahradit aid za array_keys($this->editUnits) ??

        if (!$detail) {
            $this->flashMessage("Neplatný požadavek na přehled osob", "error");
            $this->redirect("Payment:detail", array("id" => $id));
        }

        $form = $this['massAddForm'];
        $form['oid']->setDefaultValue($id);

        foreach ($list as $p) {
            $form->addSelect($p['ID'] . '_email', NULL, $p['emails'])
                    ->setPrompt("")
                    ->setDefaultValue(key($p['emails']))
                    ->setAttribute('class', 'input-xlarge');
        }
    }

    public function createComponentMassAddForm($name) {
        $form = new Form($this, $name);
        $form->addHidden("oid");
        $form->addText("defaultAmount", "Částka:")
                ->setAttribute('class', 'input-mini');
        $form->addDatePicker('defaultMaturity', "Splatnost:")//
                ->setAttribute('class', 'input-small');
        $form->addText("defaultKs", "KS:")
                ->setAttribute('class', 'input-mini');
        $form->addText("defaultNote", "Poznámka:")
                ->setAttribute('class', 'input-small');
        $form->addSubmit('send', 'Přidat vybrané')
                ->setAttribute("class", "btn btn-primary btn-large");
        $form->onSubmit[] = array($this, $name . 'Submitted');
        return $form;
    }

    function massAddFormSubmitted(Form $form) {
        $values = $form->getValues();
        $checkboxs = $form->getHttpData($form::DATA_TEXT, 'ch[]');
        $vals = $form->getHttpData()['vals'];

        if (!$this->isEditable) {
            $this->flashMessage("Nemáte oprávnění pro práci s registrací jednotky", "error");
            $this->redirect("Payment:detail", array("id" => $values->oid));
        }
        //$list = $this->model->getPersons($this->aid, $values->oid);

        foreach ($checkboxs as $pid) {
            $pid = substr($pid, 2);
            $tmpAmount = $vals[$pid]['amount'];
            $tmpMaturity = $vals[$pid]['maturity'];
            $tmpKS = $vals[$pid]['ks'];
            $tmpNote = $vals[$pid]['note'];

            $name = $this->noEmpty($vals[$pid]['name']);
            $amount = $tmpAmount == "" ? $this->noEmpty($values['defaultAmount']) : $tmpAmount;
            if ($amount === NULL) {
                $form->addError("Musí být vyplněna částka."); //[$uid . '_' . $p['ID'] . '_amount']
                return;
            }

            if ($tmpMaturity != "") {
                $maturity = date("Y-m-d", strtotime($tmpMaturity));
            } else {
                if ($values['defaultMaturity'] instanceof \DateTime) {
                    $maturity = date("Y-m-d", strtotime($values['defaultMaturity']));
                } else {
                    $form->addError("Musí být vyplněná splatnost."); //[$uid . '_' . $p['ID'] . '_amount']
                    return;
                }
            }
            $email = $this->noEmpty($vals[$pid]['email']);
            $vs = $this->noEmpty($vals[$pid]['vs']);
            $ks = $tmpKS == "" ? $this->noEmpty($values['defaultKs']) : $tmpKS;
            $note = $tmpNote == "" ? $this->noEmpty($values['defaultNote']) : $tmpNote;

            $this->model->createPayment($values->oid, $name, $email, $amount, $maturity, $pid, $vs, $ks, $note);
        }

        $this->flashMessage("Platby byly přidány");
        $this->redirect("Payment:detail", array("id" => $values->oid));
    }

    public function handleCancel($pid) {
        if (!$this->isEditable) {
            $this->flashMessage("Neplatný požadavek na zrušení platby!", "error");
            $this->redirect("this");
        }
        if (!$this->model->get(array_keys($this->editUnits), $pid)) {
            $this->flashMessage("Platba pro zrušení nebyla nalezena!", "error");
            $this->redirect("this");
        }
        if ($this->model->update($pid, array("state" => "canceled"))) {
            $this->flashMessage("Platba byla zrušena.");
        } else {
            $this->flashMessage("Platbu se nepodařilo zrušit!", "error");
        }
        $this->redirect("this");
    }

    public function handleSend($pid) {
        if (!$this->isEditable || !$this->model->get(array_keys($this->editUnits), $pid)) {
            $this->flashMessage("Neplatný požadavek na odeslání emailu!", "error");
            $this->redirect("this");
        }
        $payment = $this->model->get(array_keys($this->editUnits), $pid);

        if ($this->model->sendInfo($this->template, $payment, $this->unitService)) {
            $this->flashMessage("Informační email byl odeslán.");
        } else {
            $this->flashMessage("Informační email se nepodařilo odeslat!", "error");
        }
        $this->redirect("this");
    }

    /**
     * rozešle všechny neposlané emaily
     * @param int $gid groupId
     */
    public function handleSendGroup($gid) {
        if (!$this->isEditable) {
            $this->flashMessage("Neoprávněný přístup k záznamu!", "error");
            $this->redirect("this");
        }
        $payments = $this->model->getAll($gid);
        $cnt = 0;
        $unitIds = array_keys($this->editUnits);
        foreach ($payments as $p) {
            $payment = $this->model->get($unitIds, $p->id);
            $cnt += $this->model->sendInfo($this->template, $payment, $this->unitService);
        }

        if ($cnt > 0) {
            $this->flashMessage("Informační emaily($cnt) byly odeslány.");
        } else {
            $this->flashMessage("Nebyl odeslán žádný informační email!", "error");
        }
        $this->redirect("this");
    }

    public function handleSendTest($gid) {
        if (!$this->isEditable) {
            $this->flashMessage("Neplatný požadavek na odeslání testovacího emailu!", "error");
            $this->redirect("this");
        }
        $personalDetail = $this->context->getService("userService")->getPersonalDetail();
        if (!isset($personalDetail->Email)) {
            $this->flashMessage("Nemáte nastavený email ve skautisu, na který by se odeslal testovací email!", "error");
            $this->redirect("this");
        }
        $group = $this->model->getGroup(array_keys($this->readUnits), $gid);
        $payment = \Nette\Utils\ArrayHash::from(array(
                    "state" => \Model\PaymentTable::PAYMENT_STATE_PREPARING,
                    "name" => "Testovací účel",
                    "email" => $personalDetail->Email,
                    "unitId" => $group->unitId,
                    "amount" => $group->amount != 0 ? $group->amount : rand(50, 1000),
                    "maturity" => $group->maturity instanceof \DateTime ? $group->maturity : new \DateTime(date("Y-m-d", strtotime("+2 week"))),
                    "ks" => $group->ks,
                    "vs" => rand(1000, 100000),
                    "email_info" => $group->email_info,
                    "note" => "obsah poznámky",
        ));

        if ($this->model->sendInfo($this->template, $payment, $this->unitService)) {
            $this->flashMessage("Testovací email byl odeslán na " . $personalDetail->Email . " .");
        } else {
            $this->flashMessage("Testovací email se nepodařilo odeslat!", "error");
        }
        $this->redirect("this");
    }

    public function handleComplete($pid) {
        if (!$this->isEditable) {
            $this->flashMessage("Nejste oprávněni k uzavření platby!", "error");
            $this->redirect("this");
        }
        if ($this->model->update($pid, array("state" => "completed"))) {
            $this->flashMessage("Platba byla zaplacena.");
        } else {
            $this->flashMessage("Platbu se nepodařilo uzavřít!", "error");
        }
        $this->redirect("this");
    }

    public function handlePairPayments($gid = NULL) {
        if ($gid !== NULL && !$this->isEditable) {
            $this->flashMessage("Nemáte oprávnění párovat platby!", "error");
            $this->redirect("this");
        }
        $pairsCnt = $this->bank->pairPayments($this->model, $this->aid, $gid);
        if ($pairsCnt > 0) {
            $this->flashMessage("Podařilo se spárovat platby ($pairsCnt)");
        } else {
            $this->flashMessage("Žádné platby nebyly spárovány");
        }
        $this->redirect("this");
    }

    public function createComponentPaymentForm($name) {
        $form = new Form($this, $name);
        $form->addText("name", "Název/účel")
                ->addRule(Form::FILLED, "Musíte zadat název platby");
        $form->addText("amount", "Částka")
                ->addRule(Form::FILLED, "Musíte vyplnit částku")
                ->addRule(Form::FLOAT, "Částka musí být zadaná jako číslo");
        $form->addText("email", "Email")
                ->addCondition(Form::FILLED)
                ->addRule(Form::EMAIL, "Zadaný email nemá platný formát");
        $form->addDatePicker("maturity", "Splatnost");
        $form->addText("vs", "VS", NULL, 10)
                ->addCondition(Form::FILLED)
                ->addRule(Form::INTEGER, "Variabilní symbol musí být číslo");
        $form->addText("ks", "KS", NULL, 10)
                ->addCondition(Form::FILLED)
                ->addRule(Form::INTEGER, "Konstantní symbol musí být číslo");
        $form->addText("note", "Poznámka");
        $form->addHidden("oid");
        $form->addHidden("pid");
        $form->onSubmit[] = array($this, 'paymentSubmitted');
        return $form;
    }

    function paymentSubmitted(Form $form) {
        if (!$this->isEditable) {
            $this->flashMessage("Nejste oprávněni k úpravám plateb!", "error");
            $this->redirect("this");
        }
        $v = $form->getValues();
        if ($v->maturity == NULL) {
            $form['maturity']->addError("Musíte vyplnit splatnost");
            return;
        }
        if ($v->pid != "") {//EDIT
            if ($this->model->update($v->pid, array('name' => $v->name, 'email' => $v->email, 'amount' => $v->amount, 'maturity' => $v->maturity, 'vs' => $v->vs, 'ks' => $v->ks, 'note' => $v->note))) {
                $this->flashMessage("Platba byla upravena");
            } else {
                $this->flashMessage("Platbu se nepodařilo založit", "error");
            }
        } else {//ADD
            if ($this->model->createPayment($v->oid, $v->name, $v->email, $v->amount, $v->maturity, NULL, $v->vs, $v->ks, $v->note)) {
                $this->flashMessage("Platba byla přidána");
            } else {
                $this->flashMessage("Platbu se nepodařilo založit", "error");
            }
        }
        $this->redirect("detail", array("id" => $v->oid));
    }

}