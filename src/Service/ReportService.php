<?php

namespace App\Service;

use App\Repository\StudentRepository;

class ReportService
{
    public function __construct(
        private StudentRepository $students,
        private PaymentCalculationService $calculator,
    ) {
    }

    /**
     * Le montant attendu se calcule eleve par eleve : un frais affecte a une
     * classe est du par chaque eleve de cette classe, et les reductions sont
     * individuelles. Sommer les FeeAssignment une seule fois sous-estimerait
     * fortement le montant attendu.
     */
    public function overview(): array
    {
        $expected = 0.0;
        $paid = 0.0;

        foreach ($this->students->findAll() as $student) {
            $situation = $this->calculator->getStudentSituation($student);
            $expected += $situation['netTotal'];
            $paid += $situation['paidTotal'];
        }

        return [
            'students' => $this->students->count([]),
            'expected' => $expected,
            'paid' => $paid,
            'remaining' => max(0.0, $expected - $paid),
            'collectionRate' => $expected > 0 ? round(($paid / $expected) * 100, 1) : 0,
        ];
    }
}
