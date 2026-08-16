<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Eligibility;
use App\Models\EligibilityStudentProof;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EligibilityStudentProof>
 */
class EligibilityStudentProofFactory extends Factory
{
    protected $model = EligibilityStudentProof::class;

    public function definition(): array
    {
        return [
            'eligibility_id' => Eligibility::factory(),
            'student_proof_path' => 'eligibility-student-proofs/1/student-proof/placeholder.pdf',
            'sort_order' => 0,
            'inserted_at' => time(),
            'updated_at' => time(),
        ];
    }
}
