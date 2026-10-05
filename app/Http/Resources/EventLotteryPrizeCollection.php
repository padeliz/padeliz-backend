<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EventLotteryPrizeCollection extends ResourceCollection
{
    public $collects = EventLotteryPrizeResource::class;

    public function __construct(
        mixed $resource,
        private readonly ?bool $hasUnresolvedPending = null,
    ) {
        parent::__construct($resource);
    }

    /**
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        if ($this->hasUnresolvedPending !== null) {
            $default['meta']['has_unresolved_pending'] = $this->hasUnresolvedPending;
        }

        return $default;
    }
}
