<?php

namespace App\Services\AI;

class PromptBuilder
{
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
and provide comprehensive, detailed advisory notes. You must base your analysis strictly on the data provided.
Do not invent facts or make assumptions beyond the supplied information.

When providing your analysis:
- Be specific and detailed in your descriptions
- Use the actual names of provinces, districts, and locations (not IDs)
- Explain the significance of overlaps and gaps
- Provide actionable, context-aware recommendations
- Consider the geographic and thematic scope of programmes
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
Your analysis should:
1. Provide a comprehensive executive summary (2-3 paragraphs) highlighting key findings
2. Identify and describe similar or overlapping programmes with specific details about HOW they overlap
3. Assess potential duplication, considering geographic coverage, target audiences, and activities
4. Identify coverage gaps that the submitted programme could address
5. Provide specific, actionable recommendations grounded in the actual data
6. Include confidence notes about the analysis quality and any data limitations

Be thorough and detailed. Use actual location names (provinces, districts) and activity names in your analysis.
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
            $section .= "Geographic Coverage:\n";
            $section .= $this->formatGeography($profile['geography']);
        }

        if (!empty($profile['audiences'])) {
            $section .= "Target Audiences:\n";
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
                $keywordNames = [];
                foreach ($entry['keywords'] as $keyword) {
                    if (is_string($keyword)) {
                        $keywordNames[] = $keyword;
                    } elseif (is_array($keyword)) {
                        $keywordNames[] = $keyword['name'] ?? $keyword['keyword'] ?? json_encode($keyword);
                    } elseif (is_object($keyword)) {
                        $keywordNames[] = $keyword->name ?? $keyword->keyword ?? (string)$keyword;
                    }
                }
                $section .= "Keywords: " . implode(', ', array_filter($keywordNames)) . "\n";
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
  "executive_summary": "A comprehensive 2-3 paragraph summary of the analysis. Include specific details about the programme profile, key overlaps identified, and overall assessment. Mention specific provinces, activities, and organisations where relevant.",
  
  "similar_or_overlapping_programmes": [
    {
      "programme_name": "Name of the overlapping programme",
      "organisation": "Organisation name",
      "overlap_type": "activity / geography / audience / multiple",
      "description": "Detailed description of HOW this programme overlaps with the submitted profile. Be specific about shared geographic areas, target audiences, or activities. Use actual location names and activity names."
    }
  ],
  
  "potential_duplication": "Detailed assessment of whether the submitted programme may duplicate existing efforts. Explain WHERE and HOW duplication might occur. Reference specific overlapping programmes by name and describe the specific areas of concern (e.g., 'Both programmes target primary education in Phnom Penh province').",
  
  "coverage_gaps": "Detailed identification of any gaps in coverage that the submitted programme could address. Be specific about which geographic areas, beneficiary groups, or activity areas are underserved based on the existing map data. Use actual province/district names.",
  
  "recommendations": "Comprehensive, actionable recommendations for the programme submission. Provide 3-5 specific recommendations grounded in the data. Each recommendation should explain WHY it matters and HOW it addresses a specific finding from the analysis.",
  
  "confidence_notes": "Notes on confidence level, data quality, limitations, or additional context. For example: 'Analysis based on full map scope with 3 overlapping programmes identified. High confidence due to detailed activity and geographic data available.'"
}

IMPORTANT: In your analysis, always use actual names (province names, district names, activity names) instead of IDs. Make your response detailed, specific, and actionable.
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

        $text .= "\n  Note: These IDs correspond to the taxonomy categories, subcategories, and items shown in the OVERLAPPING PROGRAMMES section below.\n";

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

        $text .= "\n  Note: These location IDs correspond to the actual location names shown in the OVERLAPPING PROGRAMMES section below.\n";

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