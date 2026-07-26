<?php

namespace App\Service;

use App\Repository\ReceiptRepository;

class ReceiptNumberGenerator
{
    public function __construct(private ReceiptRepository $receipts)
    {
    }

    public function generate(): string
    {
        do {
            $number = 'REC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } while ($this->receipts->findOneBy(['receiptNumber' => $number]));

        return $number;
    }
}
