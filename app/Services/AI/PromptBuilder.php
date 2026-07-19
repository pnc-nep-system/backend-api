<?php

namespace App\Services\AI;

class PromptBuilder
{
    /**
     * Build a structured prompt for Gemini AI based on the submitted programme profile
     * and overlapping programme entries.
     *
     * @param array $programmeProfile The submitted programme profile
     * @param array $overlappingEntries Collection of overlapping programme entries (as arrays)
     * @param string $analysisScope The analysis scope (full map, geographic subset, thematic subset)
     * @param string|null $analysisScopeDetail Additional scope details
     * @return string The constructed prompt
     */
    public function build(
        array $programmeProfile,
        array $overlappingEntries,
        string $analysisScope = 'full map',
        ?string $analysisScopeDetail = null
    ): string {
        $prompt = $this->buildSystemInstruction();
        $prompt .= "\n\n---\n\n";
        $prompt .= $this->buildContextSection($analysisScope, $analysisScopeDetail);
        $prompt .= "\n\n---\n\n";
        $prompt .= $this->buildProgrammeProfileSection($programmeProfile);
        $prompt .= "\n\n---\n\n";
        $prompt .= $this->buildOverlappingEntriesSection($overlappingEntries);
        $prompt .= "\n\n---\n\n";
        $prompt .= $this->buildOutputFormatInstruction();

        return $prompt;
    }

    private function buildSystemInstruction(): string
    {
        return <<<SYSTEM
You are an expert education sector advisor for the National Education Policy (NEP) in Cambodia.
Your role is to analyse programme submissions against the existing education programme map
and provide structured advisory notes. You must base your analysis strictly on the data provided.
Do not invent facts or make assumptions beyond the supplied information.
SYSTEM;
    }

    private function buildContextSection(string $scope, ?string $scopeDetail): string
    {
        $context = "ANALYSIS CONTEXT\n";
        $context .= "Analysis Scope: {$scope}\n";

        if ($scopeDetail) {
            $context .= "Scope Detail: {$scopeDetail}\n";
        }

        $context .= <<<CONTEXT

Analyse the submitted programme profile against the overlapping programmes listed below.
Identify areas of similarity, potential duplication, coverage gaps, and provide actionable recommendations.
CONTEXT;

        return $context;
    }

    private function buildProgrammeProfileSection(array $profile): string
    {
        $section = "SUBMITTED PROGRAMME PROFILE\n";
        $section .= "The following is the profile of the programme being submitted for analysis:\n\n";

        if (!empty($profile['activities'])) {
            $section .= "Activities:\n";
            $section .= $this->formatActivities($profile['activities']);
        }

        if (!empty($profile['geography'])) {
            $section .= "Geography:\n";
            $section .= $this->formatGeography($profile['geography']);
        }

        if (!empty($profile['audiences'])) {
            $section .= "Audiences:\n";
            $section .= $this->formatAudiences($profile['audiences']);
        }

        return $section;
    }

    private function buildOverlappingEntriesSection(array $entries): string
    {
        if (empty($entries)) {
            return "OVERLAPPING PROGRAMMES\nNo overlapping programmes were found in the current map.";
        }

        $section = "OVERLAPPING PROGRAMMES\n";
        $section .= "The following " . count($entries) . " programme(s) from the existing map overlap with the submitted profile:\n\n";

        foreach ($entries as $index => $entry) {
            $num = $index + 1;
            $section .= "--- Programme {$num} ---\n";
            $section .= "Programme Name: " . ($entry['programme_name'] ?? 'N/A') . "\n";
            $section .= "Organisation: " . ($entry['organisation']['name'] ?? 'N/A') . "\n";
            $section .= "Budget Band: " . ($entry['budget_band']['label'] ?? 'N/A') . "\n";
            $section .= "Duration: " . ($entry['start_year'] ?? 'N/A') . " - " . ($entry['end_year'] ?? 'N/A') . "\n";
            $section .= "Ongoing: " . (($entry['ongoing'] ?? false) ? 'Yes' : 'No') . "\n";
            $section .= "FTE Staff: " . ($entry['fte_staff'] ?? 'N/A') . "\n";
            $section .= "Direct Beneficiaries: " . ($entry['direct_beneficiaries'] ?? 'N/A') . "\n";
            $section .= "Indirect Beneficiaries: " . ($entry['indirect_beneficiaries'] ?? 'N/A') . "\n";
            $section .= "Method: " . ($entry['method'] ?? 'N/A') . "\n";

            if (!empty($entry['keywords'])) {
                $section .= "Keywords: " . implode(', ', $entry['keywords']) . "\n";
            }

            if (!empty($entry['locations'])) {
                $section .= "Locations:\n";
                foreach ($entry['locations'] as $loc) {
                    $parts = [];
                    if (!empty($loc['province']['name'])) {
                        $parts[] = $loc['province']['name'];
                    }
                    if (!empty($loc['district']['name'])) {
                        $parts[] = $loc['district']['name'];
                    }
                    if (!empty($loc['commune']['name'])) {
                        $parts[] = $loc['commune']['name'];
                    }
                    if (!empty($loc['village']['name'])) {
                        $parts[] = $loc['village']['name'];
                    }
                    if (!empty($parts)) {
                        $section .= "  - " . implode(' > ', $parts) . "\n";
                    }
                }
            }

            if (!empty($entry['activities'])) {
                $section .= "Activities:\n";
                foreach ($entry['activities'] as $activity) {
                    $taxonomy = $activity['taxonomy'] ?? [];
                    $parts = [];
                    if (!empty($taxonomy['category']['name'])) {
                        $parts[] = $taxonomy['category']['name'];
                    }
                    if (!empty($taxonomy['subcategory']['name'])) {
                        $parts[] = $taxonomy['subcategory']['name'];
                    }
                    if (!empty($taxonomy['item']['name'])) {
                        $parts[] = $taxonomy['item']['name'];
                    }
                    $activityStr = implode(' > ', $parts);
                    if (!empty($activity['inclusion_group'])) {
                        $activityStr .= " [Group: {$activity['inclusion_group']}]";
                    }
                    if (!empty($activity['inclusion_type'])) {
                        $activityStr .= " [Type: {$activity['inclusion_type']}]";
                    }
                    if (!empty($activity['education_levels'])) {
                        $levels = array_column($activity['education_levels'], 'name');
                        $activityStr .= " [Levels: " . implode(', ', $levels) . "]";
                    }
                    $section .= "  - {$activityStr}\n";
                }
            }

            $section .= "\n";
        }

        return $section;
    }

    private function buildOutputFormatInstruction(): string
    {
        return <<<FORMAT
OUTPUT FORMAT
You MUST respond with a valid JSON object containing the following keys. Do not include any text outside the JSON object.

{
  "executive_summary": "A concise summary of the analysis findings (2-3 paragraphs).",
  "similar_or_overlapping_programmes": [
    {
      "programme_name": "Name of the overlapping programme",
      "organisation": "Organisation name",
      "overlap_type": "activity / geography / audience / multiple",
      "description": "Description of how this programme overlaps with the submitted profile"
    }
  ],
  "potential_duplication": "Assessment of whether the submitted programme may duplicate existing efforts. Include specific references to overlapping programmes.",
  "coverage_gaps": "Identification of any gaps in coverage that the submitted programme could address, based on the existing map data.",
  "recommendations": "Actionable recommendations for the programme submission, grounded in the data provided.",
  "confidence_notes": "Any notes on confidence level, data limitations, or additional context (optional)."
}
FORMAT;
    }

    private function formatActivities(array $activities): string
    {
        $text = '';
        $categoryIds = $activities['category_ids'] ?? [];
        $subcategoryIds = $activities['subcategory_ids'] ?? [];
        $itemIds = $activities['item_ids'] ?? [];
        $educationLevelIds = $activities['education_level_ids'] ?? [];
        $inclusionGroups = $activities['inclusion_groups'] ?? [];
        $inclusionTypes = $activities['inclusion_types'] ?? [];

        if (!empty($categoryIds)) {
            $text .= "  Category IDs: " . implode(', ', $categoryIds) . "\n";
        }
        if (!empty($subcategoryIds)) {
            $text .= "  Subcategory IDs: " . implode(', ', $subcategoryIds) . "\n";
        }
        if (!empty($itemIds)) {
            $text .= "  Item IDs: " . implode(', ', $itemIds) . "\n";
        }
        if (!empty($educationLevelIds)) {
            $text .= "  Education Level IDs: " . implode(', ', $educationLevelIds) . "\n";
        }
        if (!empty($inclusionGroups)) {
            $text .= "  Inclusion Groups: " . implode(', ', $inclusionGroups) . "\n";
        }
        if (!empty($inclusionTypes)) {
            $text .= "  Inclusion Types: " . implode(', ', $inclusionTypes) . "\n";
        }

        return $text;
    }

    private function formatGeography(array $geography): string
    {
        $text = '';
        $provinceIds = $geography['province_ids'] ?? [];
        $districtIds = $geography['district_ids'] ?? [];
        $communeIds = $geography['commune_ids'] ?? [];
        $villageIds = $geography['village_ids'] ?? [];

        if (!empty($provinceIds)) {
            $text .= "  Province IDs: " . implode(', ', $provinceIds) . "\n";
        }
        if (!empty($districtIds)) {
            $text .= "  District IDs: " . implode(', ', $districtIds) . "\n";
        }
        if (!empty($communeIds)) {
            $text .= "  Commune IDs: " . implode(', ', $communeIds) . "\n";
        }
        if (!empty($villageIds)) {
            $text .= "  Village IDs: " . implode(', ', $villageIds) . "\n";
        }

        return $text;
    }

    private function formatAudiences(array $audiences): string
    {
        $text = '';
        $groups = $audiences['inclusion_groups'] ?? [];
        $types = $audiences['inclusion_types'] ?? [];

        if (!empty($groups)) {
            $text .= "  Inclusion Groups: " . implode(', ', $groups) . "\n";
        }
        if (!empty($types)) {
            $text .= "  Inclusion Types: " . implode(', ', $types) . "\n";
        }

        return $text;
    }
}