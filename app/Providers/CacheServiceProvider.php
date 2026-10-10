<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Club;
use App\Models\Court;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventLottery;
use App\Models\EventLotteryPrize;
use App\Models\EventLotteryRule;
use App\Models\EventParticipation;
use App\Models\EventRegistration;
use App\Models\Location;
use App\Models\Organizer;
use App\Models\PadelEvent;
use App\Models\Player;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldOption;
use App\Models\PlayerCustomFieldValue;
use App\Models\Prize;
use App\Models\Sponsor;
use App\Models\SponsorPrize;
use App\Observers\InvalidatesResourceCacheObserver;
use App\Services\Cache\EventDivisionCacheInvalidator;
use App\Services\Cache\ManagerResourceReadCache;
use App\Services\Cache\MetadataCacheInvalidator;
use App\Services\Cache\MetadataConsumerRegistry;
use App\Services\Cache\ModelCacheInvalidator;
use App\Services\Cache\ModelSchemaCatalog;
use App\Services\Cache\PadelizCache;
use App\Services\Cache\ScheduleReadCache;
use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PadelizCache::class);
        $this->app->singleton(ModelSchemaCatalog::class);
        $this->app->singleton(MetadataConsumerRegistry::class);
        $this->app->singleton(MetadataCacheInvalidator::class);
        $this->app->singleton(EventDivisionCacheInvalidator::class);
        $this->app->singleton(ModelCacheInvalidator::class);
        $this->app->singleton(ManagerResourceReadCache::class);
        $this->app->singleton(ScheduleReadCache::class);
    }

    public function boot(): void
    {
        $observer = InvalidatesResourceCacheObserver::class;

        foreach ([
            Organizer::class,
            Club::class,
            Location::class,
            Division::class,
            Court::class,
            Prize::class,
            Sponsor::class,
            SponsorPrize::class,
            PlayerCustomField::class,
            PlayerCustomFieldOption::class,
            PadelEvent::class,
            EventDivision::class,
            EventRegistration::class,
            EventParticipation::class,
            Player::class,
            PlayerCustomFieldValue::class,
            EventLottery::class,
            EventLotteryPrize::class,
            EventLotteryRule::class,
        ] as $model) {
            $model::observe($observer);
        }
    }
}
