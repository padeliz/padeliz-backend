<?php

declare(strict_types=1);

namespace Tests\Fixtures\ModelSchemaCatalog\WithTrait;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Model;

class TraitedModel extends Model
{
    use HasResourceMetadata;

    protected $table = 'fixture_traited_models';

    public static array $filterableFields = [];
}
