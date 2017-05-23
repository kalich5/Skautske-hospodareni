<?php

namespace Model\DTO\Payment;

use Model\Payment\Payment;
use Model\DTO\Payment\Payment as PaymentDTO;

class PaymentFactory
{

    public static function create(Payment $payment): PaymentDTO
    {
        return new PaymentDTO(
            $payment->getId(),
            $payment->getName(),
            $payment->getAmount(),
            $payment->getEmail(),
            $payment->getDueDate(),
            $payment->getVariableSymbol(),
            $payment->getConstantSymbol(),
            $payment->getNote(),
            $payment->isClosed(),
            $payment->getState(),
            $payment->getTransaction(),
            $payment->getClosedAt(),
            $payment->getPersonId(),
            $payment->getGroupId()
        );
    }

}