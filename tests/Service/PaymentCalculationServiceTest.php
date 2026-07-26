<?php

namespace App\Tests\Service;

use App\Entity\Discount;
use App\Entity\FeeAssignment;
use App\Entity\Student;
use App\Entity\StudentDiscount;
use App\Repository\FeeAssignmentRepository;
use App\Repository\PaymentDetailRepository;
use App\Repository\StudentDiscountRepository;
use App\Service\PaymentCalculationService;
use PHPUnit\Framework\TestCase;

final class PaymentCalculationServiceTest extends TestCase
{
    public function testNetAmountAppliesPercentDiscount(): void
    {
        $student = new Student();
        $fee = (new FeeAssignment())->setAmount(150000);
        $discount = (new Discount())->setType(Discount::TYPE_PERCENT)->setValue(10);

        $service = $this->service([
            (new StudentDiscount())->setStudent($student)->setDiscount($discount),
        ]);

        self::assertSame(135000.0, $service->getNetAmount($student, $fee));
    }

    private function service(array $studentDiscounts): PaymentCalculationService
    {
        $discounts = $this->createMock(StudentDiscountRepository::class);
        $discounts->method('findBy')->willReturn($studentDiscounts);

        return new PaymentCalculationService(
            $this->createMock(FeeAssignmentRepository::class),
            $discounts,
            $this->createMock(PaymentDetailRepository::class),
        );
    }
}
