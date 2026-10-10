<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlayerStudentProof;
use App\Models\PlayerStudentProofFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerStudentProofFile>
 */
class PlayerStudentProofFileFactory extends Factory
{
    protected $model = PlayerStudentProofFile::class;

    public function definition(): array
    {
        return [
            'student_proof_id' => PlayerStudentProof::factory(),
            'student_proof_path' => 'player-student-proofs/1/student-proof/' . $this->faker->unique()->uuid() . '.pdf',
            'sort_order' => 0,
            'inserted_at' => time(),
            'updated_at' => null,
        ];
    }
}
