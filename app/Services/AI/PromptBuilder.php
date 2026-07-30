<?php

namespace App\Services\AI;

use App\Models\ActivityCategory;
use App\Models\ActivityItem;
use App\Models\ActivitySubcategory;
use App\Models\District;
use App\Models\EducationLevel;
use App\Models\Province;

class PromptBuilder
{
    public function build(
        array $programmeProfile,
        array $overlappingEntries,
        string $analysisScope = 'full map',
        ?string $analysisScopeDetail = null,
        ?string $documentText = null,
        ?string $programmeName = null,
        ?string $submittingParty = null
    ): string {
        $resolvedProfile = $this->resolveProfileIds($programmeProfile);

        $prompt  = $this->buildSystemInstruction();
        $prompt .= "\n\n---\n\n";
        $prompt .= $this->buildContextSection($analysisScope, $analysisScopeDetail, $programmeName, $submittingParty);
        $prompt .= "\n\n---\n\n";

        if ($documentText) {
            $prompt .= $this->buildDocumentSection($documentText);
            $prompt .= "\n\n---\n\n";
        }

        $prompt .= $this->buildTaxonomyReferenceSection($programmeProfile);
        $prompt .= "\n\n---\n\n";
        $prompt .= $this->buildProgrammeProfileSection($resolvedProfile, $programmeProfile);
        $prompt .= "\n\n---\n\n";
        $prompt .= $this->buildOverlappingEntriesSection($overlappingEntries);
        $prompt .= "\n\n---\n\n";
        $prompt .= $this->buildOutputFormatInstruction();

        return $prompt;
    }

    // ── ID resolution ──────────────────────────────────────────────────────────

    private function resolveProfileIds(array $profile): array
    {
        $activities = $profile['activities'] ?? [];
        $geography  = $profile['geography']  ?? [];
        $audiences  = $profile['audiences']  ?? [];

        $categoryIds       = $activities['category_ids']       ?? [];
        $subcategoryIds    = $activities['subcategory_ids']    ?? [];
        $itemIds           = $activities['item_ids']           ?? [];
        $educationLevelIds = $activities['education_level_ids'] ?? [];
        $inclusionGroups   = $activities['inclusion_groups']   ?? $audiences['inclusion_groups'] ?? [];
        $inclusionTypes    = $activities['inclusion_types']    ?? $audiences['inclusion_types']  ?? [];
        $provinceIds       = $geography['province_ids']        ?? [];
        $districtIds       = $geography['district_ids']        ?? [];

        return [
            'activities' => [
                'categories'    => $categoryIds    ? ActivityCategory::whereIn('id', $categoryIds)->pluck('label')->toArray()    : [],
                'subcategories' => $subcategoryIds ? ActivitySubcategory::whereIn('id', $subcategoryIds)->pluck('label')->toArray() : [],
                'items'         => $itemIds        ? ActivityItem::whereIn('id', $itemIds)->pluck('label')->toArray()             : [],
                'education_levels' => $educationLevelIds ? EducationLevel::whereIn('id', $educationLevelIds)->pluck('level_name')->toArray() : [],
                'inclusion_groups' => $inclusionGroups,
                'inclusion_types'  => $inclusionTypes,
            ],
            'geography' => [
                'provinces' => $provinceIds  ? Province::whereIn('id', $provinceIds)->pluck('province_name')->toArray() : [],
                'districts' => $districtIds  ? District::whereIn('id', $districtIds)->pluck('name')->toArray()          : [],
            ],
        ];
    }

    // ── Prompt sections ────────────────────────────────────────────────────────

    private function buildSystemInstruction(): string
    {
        return <<<SYSTEM
You are a concise education sector adviser for the National Education Policy (NEP) in Cambodia.
Your task is to analyse a submitted document and produce a structured advisory note for the NEP coordinator.
Base your analysis strictly on the data provided. Do not invent facts, organisations, or locations.
SYSTEM;
    }

    private function buildContextSection(string $scope, ?string $scopeDetail, ?string $programmeName, ?string $submittingParty): string
    {
        $context = "ANALYSIS CONTEXT\n";
        $context .= "Scope: {$scope}\n";
        if ($scopeDetail)     $context .= "Detail: {$scopeDetail}\n";
        if ($programmeName)   $context .= "Programme name: {$programmeName}\n";
        if ($submittingParty) $context .= "Submitting party: {$submittingParty}\n";
        return $context;
    }

    private function buildDocumentSection(string $documentText): string
    {
        $truncated = mb_substr($documentText, 0, 5000);
        return "SUBMITTED DOCUMENT CONTENT\n" . $truncated;
    }

    private function buildTaxonomyReferenceSection(array $programmeProfile): string
    {
        $itemIds        = $programmeProfile['activities']['item_ids']        ?? [];
        $subcategoryIds = $programmeProfile['activities']['subcategory_ids'] ?? [];
        $categoryIds    = $programmeProfile['activities']['category_ids']    ?? [];

        $totalCategories = ActivityCategory::count();

        // Skip taxonomy dump when all categories are passed (non-member fallback) —
        // the full tree is too large and the AI has the document text to reason from.
        if (count($categoryIds) >= $totalCategories) {
            $educationLevels = EducationLevel::orderBy('id')->pluck('level_name');
            $section = "TAXONOMY REFERENCE\n(Full taxonomy available — infer activities from document content above.)\n";
            if ($educationLevels->isNotEmpty()) {
                $section .= "EDUCATION LEVELS: " . $educationLevels->implode(', ') . "\n";
            }
            return $section;
        }

        $categories = ActivityCategory::with(['subcategories' => function ($q) use ($itemIds, $subcategoryIds, $categoryIds) {
            if (!empty($categoryIds)) $q->whereIn('category_id', $categoryIds);
            $q->with(['items' => function ($iq) use ($itemIds, $subcategoryIds) {
                if (!empty($itemIds)) $iq->whereIn('id', $itemIds);
                elseif (!empty($subcategoryIds)) $iq->whereIn('subcategory_id', $subcategoryIds);
            }]);
        }])->when(!empty($categoryIds), fn($q) => $q->whereIn('id', $categoryIds))->get();

        $section = "TAXONOMY REFERENCE (matched activity taxonomy only)\n";
        foreach ($categories as $cat) {
            $section .= "- {$cat->label}\n";
            foreach ($cat->subcategories as $sub) {
                $section .= "  - {$sub->label}\n";
                foreach ($sub->items as $item) {
                    $section .= "    - {$item->label}\n";
                }
            }
        }

        $educationLevels = EducationLevel::orderBy('id')->pluck('level_name');
        if ($educationLevels->isNotEmpty()) {
            $section .= "\nEDUCATION LEVELS: " . $educationLevels->implode(', ') . "\n";
        }

        return $section;
    }

    private function buildProgrammeProfileSection(array $resolved, array $rawProfile): string
    {
        $section = "EXTRACTED PROGRAMME PROFILE\n";

        $activities = $resolved['activities'];
        $hasSpecificActivities = !empty($rawProfile['activities']['item_ids'])
            || !empty($rawProfile['activities']['subcategory_ids']);

        if ($hasSpecificActivities) {
            $allActivityNames = array_unique(array_merge(
                $activities['categories']    ?? [],
                $activities['subcategories'] ?? [],
                $activities['items']         ?? []
            ));
            $section .= "Activities: " . implode(', ', $allActivityNames) . "\n";
        } else {
            $section .= "Activities: (infer from document content above)\n";
        }

        if (!empty($activities['education_levels'])) {
            $section .= "Education levels: " . implode(', ', $activities['education_levels']) . "\n";
        }

        if (!empty($activities['inclusion_groups'])) {
            $section .= "Inclusion groups: " . implode(', ', $activities['inclusion_groups']) . "\n";
        }

        if (!empty($activities['inclusion_types'])) {
            $section .= "Inclusion types: " . implode(', ', $activities['inclusion_types']) . "\n";
        }

        $geo = $resolved['geography'];
        $allLocationNames = array_unique(array_merge($geo['provinces'] ?? [], $geo['districts'] ?? []));
        $section .= "Locations: " . (empty($allLocationNames) ? '(none recorded)' : implode(', ', $allLocationNames)) . "\n";

        return $section;
    }

    private function buildOverlappingEntriesSection(array $entries): string
    {
        if (empty($entries)) {
            return "OVERLAPPING MAP ENTRIES\nNone found in the current map for this profile.\n" .
                   "NOTE: Because no map entries overlap, section_b must be an empty array. " .
                   "For section_c, identify which activities in the submitted programme have no equivalent in the map. " .
                   "For section_d, note that the analysis is limited by the absence of comparable map data.";
        }

        // Cap entries sent to AI to keep prompt within token limits
        $entries = array_slice($entries, 0, 12);

        $section = "OVERLAPPING MAP ENTRIES (" . count($entries) . " found)\n";
        $section .= "These entries were matched because they share geography or activities with the submitted programme.\n\n";

        foreach ($entries as $i => $entry) {
            $n   = $i + 1;
            $org = $entry['organisation'] ?? 'N/A';
            $org = is_array($org) ? ($org['name'] ?? 'N/A') : $org;
            $section .= "#{$n} {$org} — " . ($entry['programme_name'] ?? 'N/A') . "\n";

            $locations = [];
            foreach ($entry['locations'] ?? [] as $loc) {
                $prov  = $loc['province_name'] ?? null;
                $dist  = $loc['district_name'] ?? null;
                $parts = array_filter([$prov, $dist]);
                if ($parts) $locations[] = implode(' > ', $parts);
            }
            // Cap locations and activities per entry to limit token usage
            $locations = array_slice(array_unique($locations), 0, 5);
            $section .= "  Geography: " . (empty($locations) ? '(not recorded)' : implode('; ', $locations)) . "\n";

            $activityNames = [];
            foreach ($entry['activities'] ?? [] as $act) {
                $label = $act['name'] ?? null;
                if ($label) $activityNames[] = $label;
            }
            $activityNames = array_slice(array_unique($activityNames), 0, 8);
            $section .= "  Activities: " . (empty($activityNames) ? '(not recorded)' : implode(', ', $activityNames)) . "\n";
            $section .= "\n";
        }

        return $section;
    }

    private function buildOutputFormatInstruction(): string
    {
        return <<<'FORMAT'
OUTPUT FORMAT
Respond with a valid JSON object only. No text outside the JSON.

{
  "section_a": "2-3 sentences characterising the submitted programme: what activities it covers, who the audiences are (education levels, inclusion groups), and which geography it targets. This lets the coordinator verify the AI reading before acting on recommendations.",
  "section_b": [
    {
      "organisation": "Organisation name",
      "programme_name": "Programme name from the map",
      "overlap_type": "Geographic overlap | Thematic adjacency | Complementarity",
      "rationale": "One sentence stating: (1) the specific shared dimension, (2) what each party covers that the other does not, and (3) the recommended relationship — coordinate to avoid duplication / learning exchange / collaborate to create a more complete response."
    }
  ],
  "section_c": "Identify two things: (1) activities or dimensions in the SUBMITTED PROGRAMME that are NOT covered by any entry in OVERLAPPING MAP ENTRIES — these are genuine gaps or novel areas where NEP has no current basis for a recommendation; (2) activities or dimensions present in the OVERLAPPING MAP ENTRIES that the submitted programme does NOT cover — these represent complementarity opportunities where collaboration could create a more complete response. Be explicit about which is which. If neither applies, state 'No significant gaps or complementarity identified.'",
  "section_d": "Internal notes for the NEP coordinator only — not released to the requesting party. You MUST address all of the following that apply: (1) Duplication risk: if any overlapping entry shares the same geography AND activities, explicitly flag it as a potential duplication and state which organisation the coordinator must contact before the project proceeds. (2) Ambiguities: any part of the submitted document that was unclear or could be interpreted multiple ways. (3) Low-confidence interpretations: where the AI had to infer rather than read directly. (4) Data quality issues: if the programme profile has missing activities or locations that limited the analysis. (5) Human judgement required: decisions that cannot be made from structured data alone. If none of the above apply, state 'No flags.'"
}

Rules:
- Base everything strictly on the data provided. Do not invent organisations, programmes, or locations.
- section_b must only reference organisations and programmes listed in OVERLAPPING MAP ENTRIES above.
- If no overlapping entries exist, return an empty array for section_b.
- overlap_type must be one of: "Geographic overlap", "Thematic adjacency", "Complementarity".
- For section_c: compare the submitted programme activities against the map entries activities line by line — do not just say 'no gaps'.
- For section_d: if any map entry shares both the same province AND at least one activity item with the submitted programme, you MUST flag it as a duplication risk in section_d.
- Use actual names (provinces, activities, organisations) — never IDs.
- Keep every field concise but complete. Coordinators scan this in under 60 seconds.
FORMAT;
    }
}
