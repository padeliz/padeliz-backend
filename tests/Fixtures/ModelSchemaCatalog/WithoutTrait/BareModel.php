<?php

declare(strict_types=1);

namespace Tests\Fixtures\ModelSchemaCatalog\WithoutTrait;

use Illuminate\Database\Eloquent\Model;

class BareModel extends Model
{
    protected $table = 'fixture_bare_models';
}
