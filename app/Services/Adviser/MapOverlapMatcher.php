<?php

namespace App\Services\Adviser;

use App\Models\ProgrammeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;


class MapOverlapMatcher
{
    /**
     * @param array $programmeProfile extracted programme profile from SRS 6.2 step 3
     * Expected keys (all optional):
     * - activities: array of taxonomy ids/names/keys
     *   - category_ids[] / subcategory_ids[] / item_ids[]
     *   - education_level_ids[]
     *   - inclusion_groups[]
     *   - inclusion_types[]
     * - geography: array
     *   - province_ids[]
     *   - district_ids[]
     *   - commune_ids[]
     *   - village_ids[]
     * - audiences: array
     *   - inclusion_groups[] / inclusion_types[] (mapped to programme_activity.inclusion_group/type)
     * - scope: string (optional) one of: full map | geographic subset | thematic subset
     * - scope_detail: string (optional)
     *
     * @param string $analysisScope one of: full map, geographic subset, thematic subset
     */
    public function match(array $programmeProfile, string $analysisScope = 'full map'): Builder
    {
        $q = ProgrammeEntry::query()->distinct();

        $analysisScope = $analysisScope ?: 'full map';

        // Flags to apply overlap on at least one dimension.
        $hasActivitySignals = $this->hasAnyActivitySignals($programmeProfile);
        $hasGeographySignals = $this->hasAnyGeographySignals($programmeProfile);
        $hasAudienceSignals = $this->hasAnyAudienceSignals($programmeProfile);

        // Build overlap predicates; final query is OR across dimensions
        // but scope constraints are applied via the order of constraints:
        // - geographic subset: constrain geography first (must match geography universe)
        // - thematic subset: constrain thematic/activity first (must match thematic universe)
        $activityPredicate = $this->buildActivityOverlapPredicate($programmeProfile);
        $geographyPredicate = $this->buildGeographyOverlapPredicate($programmeProfile);
        $audiencePredicate = $this->buildAudienceOverlapPredicate($programmeProfile);

        if ($analysisScope === 'geographic subset') {
            // Require geography universe overlap when geography signals exist.
            if ($hasGeographySignals) {
                $q->where(function (Builder $sub) use ($geographyPredicate, $activityPredicate, $audiencePredicate) {
                    $sub->where($geographyPredicate)
                        ->orWhere($activityPredicate)
                        ->orWhere($audiencePredicate);
                });
                return $q;
            }

            // If no geography signals were provided, fall back to OR across dimensions.
        }

        if ($analysisScope === 'thematic subset') {
            if ($hasActivitySignals || $hasAudienceSignals) {
                $q->where(function (Builder $sub) use ($activityPredicate, $audiencePredicate, $geographyPredicate) {
                    $sub->where($activityPredicate)
                        ->orWhere($audiencePredicate)
                        ->orWhere($geographyPredicate);
                });
                return $q;
            }

            // If no thematic signals were provided, fall back to OR across dimensions.
        }

        // full map (or fallback): overlap on at least one of activity/geography/audience.
        $q->where(function (Builder $sub) use ($activityPredicate, $geographyPredicate, $audiencePredicate) {
            $sub->where($activityPredicate)
                ->orWhere($geographyPredicate)
                ->orWhere($audiencePredicate);
        });

        return $q;
    }

    private function hasAnyActivitySignals(array $profile): bool
    {
        $activities = $profile['activities'] ?? [];
        $categoryIds = $activities['category_ids'] ?? [];
        $subcategoryIds = $activities['subcategory_ids'] ?? [];
        $itemIds = $activities['item_ids'] ?? [];
        $educationLevelIds = $activities['education_level_ids'] ?? [];
        $inclusionGroups = $activities['inclusion_groups'] ?? [];
        $inclusionTypes = $activities['inclusion_types'] ?? [];

        return !empty($categoryIds)
            || !empty($subcategoryIds)
            || !empty($itemIds)
            || !empty($educationLevelIds)
            || !empty($inclusionGroups)
            || !empty($inclusionTypes);
    }

    private function hasAnyGeographySignals(array $profile): bool
    {
        $geo = $profile['geography'] ?? [];
        return !empty($geo['province_ids'] ?? [])
            || !empty($geo['district_ids'] ?? [])
            || !empty($geo['commune_ids'] ?? [])
            || !empty($geo['village_ids'] ?? []);
    }

    private function hasAnyAudienceSignals(array $profile): bool
    {
        $aud = $profile['audiences'] ?? [];
        $groups = $aud['inclusion_groups'] ?? [];
        $types = $aud['inclusion_types'] ?? [];
        return !empty($groups) || !empty($types);
    }

    /**
     * Returns a Closure-style predicate (Builder callback) that can be used inside where().
     */
    private function buildActivityOverlapPredicate(array $profile): callable
    {
        return function (Builder $q) use ($profile) {
            $activities = $profile['activities'] ?? [];

            $categoryIds = $activities['category_ids'] ?? [];
            $subcategoryIds = $activities['subcategory_ids'] ?? [];
            $itemIds = $activities['item_ids'] ?? [];
            $educationLevelIds = $activities['education_level_ids'] ?? [];
            $inclusionGroups = $activities['inclusion_groups'] ?? [];
            $inclusionTypes = $activities['inclusion_types'] ?? [];

            // If no activity signals are provided, force a false predicate so OR doesn't match by accident.
            if (empty($categoryIds) && empty($subcategoryIds) && empty($itemIds) && empty($educationLevelIds) && empty($inclusionGroups) && empty($inclusionTypes)) {
                $q->whereRaw('1=0');
                return;
            }

            $q->whereHas('activities', function (Builder $aq) use (
                $categoryIds,
                $subcategoryIds,
                $itemIds,
                $educationLevelIds,
                $inclusionGroups,
                $inclusionTypes
            ) {
                if (!empty($itemIds)) {
                    $aq->whereIn('activity_item_id', $itemIds);
                }

                if (!empty($subcategoryIds) || !empty($categoryIds)) {
                    $aq->whereHas('activityItem.subcategory', function (Builder $subQ) use ($subcategoryIds, $categoryIds) {
                        if (!empty($subcategoryIds)) {
                            $subQ->whereIn('subcategory_id', $subcategoryIds);
                        }
                        if (!empty($categoryIds)) {
                            $subQ->whereIn('category_id', $categoryIds);
                        }
                    });
                }

                if (!empty($educationLevelIds)) {
                    $aq->whereExists(function ($sub) use ($educationLevelIds) {
                            $sub->select(DB::raw(1))
                            ->from('programme_activity_levels')
                            ->whereColumn('programme_activity_levels.programme_activity_id', 'programme_activities.id')
                            ->whereIn('programme_activity_levels.education_level_id', $educationLevelIds);
                    });
                }

                if (!empty($inclusionGroups)) {
                    $aq->whereIn('inclusion_group', $inclusionGroups);
                }

                if (!empty($inclusionTypes)) {
                    $aq->whereIn('inclusion_type', $inclusionTypes);
                }
            });
        };
    }

    private function buildAudienceOverlapPredicate(array $profile): callable
    {
        return function (Builder $q) use ($profile) {
            $aud = $profile['audiences'] ?? [];

            $groups = $aud['inclusion_groups'] ?? [];
            $types = $aud['inclusion_types'] ?? [];

            if (empty($groups) && empty($types)) {
                $q->whereRaw('1=0');
                return;
            }

            $q->whereHas('activities', function (Builder $aq) use ($groups, $types) {
                if (!empty($groups)) {
                    $aq->whereIn('inclusion_group', $groups);
                }
                if (!empty($types)) {
                    $aq->whereIn('inclusion_type', $types);
                }
            });
        };
    }

    private function buildGeographyOverlapPredicate(array $profile): callable
    {
        return function (Builder $q) use ($profile) {
            $geo = $profile['geography'] ?? [];

            $provinceIds = $geo['province_ids'] ?? [];
            $districtIds = $geo['district_ids'] ?? [];
            $communeIds = $geo['commune_ids'] ?? [];
            // NOTE: if your ProgrammeLocation model has village_id, you can extend similarly.
            $villageIds = $geo['village_ids'] ?? [];

            if (empty($provinceIds) && empty($districtIds) && empty($communeIds) && empty($villageIds)) {
                $q->whereRaw('1=0');
                return;
            }

            $q->whereHas('locations', function (Builder $lq) use ($provinceIds, $districtIds, $communeIds, $villageIds) {
                // province_ids: include entries that have locations in province OR its districts (matches existing map endpoint behavior)
                if (!empty($provinceIds)) {
                    $lq->where(function (Builder $subQ) use ($provinceIds) {
                        $subQ->whereIn('province_id', $provinceIds)
                            ->orWhereHas('district', function (Builder $districtQ) use ($provinceIds) {
                                $districtQ->whereIn('province_id', $provinceIds);
                            });
                    });
                }

                if (!empty($districtIds)) {
                    $lq->whereIn('district_id', $districtIds);
                }

                if (!empty($communeIds)) {
                    $lq->whereIn('commune_id', $communeIds);
                }

                if (!empty($villageIds)) {
                    // only applies if programme_locations table has village_id and ProgrammeLocation has village relation.
                    $lq->whereIn('village_id', $villageIds);
                }
            });
        };
    }
}

