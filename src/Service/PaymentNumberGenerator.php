<?php

namespace App\Service;

use App\Repository\PaymentRepository;

class PaymentNumberGenerator
{
    public function __construct(private PaymentRepository $payments)
    {
    }

    public function generate(): string
    {
        do {
            $number = 'PAY-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } while ($this->payments->findOneBy(['paymentNumber' => $number]));

        return $number;
    }
}
