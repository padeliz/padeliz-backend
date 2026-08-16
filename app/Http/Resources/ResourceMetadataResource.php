<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceMetadataResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'version' => $this['version'],
            'schema_name' => $this['schema_name'],
            'resource_path' => $this['resource_path'],
            'swagger_schema' => $this['swagger_schema'],
            'request_body' => $this['request_body'],
            'display_field' => $this['display_field'],
            'searchable_fields' => $this['searchable_fields'],
            'filterable_fields' => $this['filterable_fields'],
            'sortable_fields' => $this['sortable_fields'],
        ];
    }
}
