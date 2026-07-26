<?php

namespace App\Service;

class CsvImportService
{
    public function expectedStudentColumns(): array
    {
        return ['matricule', 'prenom', 'nom', 'sexe', 'date_naissance'];
    }
}
