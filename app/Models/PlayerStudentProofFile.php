<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read string $url
 *
 * @OA\Schema(
 *     schema="PlayerStudentProofFile",
 *     required={"student_proof_id", "student_proof_path"},
 *     @OA\Property(property="id_player_student_proof_file", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="student_proof_id", type="integer", format="int64", title="Student Proof"),
 *     @OA\Property(property="sort_order", type="integer", title="Sort Order"),
 *     @OA\Property(property="url", type="string", readOnly=true, title="URL"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At")
 * )
 */
class PlayerStudentProofFile extends Model
{
    use HasFactory;

    protected $table = 'player_student_proof_files';

    protected $primaryKey = 'id_player_student_proof_file';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'student_proof_id',
        'student_proof_path',
        'sort_order',
    ];

    protected $casts = [
        'student_proof_id' => 'integer',
        'sort_order' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    protected $appends = ['url'];

    protected static function booted(): void
    {
        static::deleting(function (PlayerStudentProofFile $file): void {
            if ($file->student_proof_path !== null && $file->student_proof_path !== '') {
                Storage::disk($file->storageDisk())->delete($file->student_proof_path);
            }
        });
    }

    public function proof(): BelongsTo
    {
        return $this->belongsTo(PlayerStudentProof::class, 'student_proof_id', 'id_player_student_proof');
    }

    public function storageDisk(): string
    {
        return config('filesystems.user_uploads_disk', 'public');
    }

    public function getUrlAttribute(): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($this->storageDisk());

        return $disk->url($this->student_proof_path);
    }
}
