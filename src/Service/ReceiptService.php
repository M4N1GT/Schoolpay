<?php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\Receipt;

class ReceiptService
{
    public function __construct(private ReceiptNumberGenerator $receiptNumberGenerator)
    {
    }

    public function createForPayment(Payment $payment): Receipt
    {
        $receipt = new Receipt();
        $receipt->setPayment($payment);
        $receipt->setReceiptNumber($this->receiptNumberGenerator->generate());
        $receipt->setVerificationCode(strtoupper(bin2hex(random_bytes(8))));
        $receipt->setQrCodeData('/receipt/verify/' . $receipt->getVerificationCode());
        $payment->setReceipt($receipt);

        return $receipt;
    }
}
