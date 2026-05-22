<?php

namespace App\Services\Snomed;

use App\Helpers\ConfigurationHelper;
use App\Services\TerminologyCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Snowstorm SNOMED CT Terminology Server Service
 * 
 * This service provides access to the Snowstorm API for SNOMED CT terminology operations.
 * 
 * Base URL: http://simrs.rumkittnipsp.com:9876
 * Version: 10.10.1
 * 
 * API Endpoints:
 * - /version - Get server version
 * - /branches - List available branches
 * - /MAIN/concepts - Search concepts (RECOMMENDED for search)
 * - /browser/MAIN/concepts - Browser-specific concept search (less accurate)
 * - /MAIN/descriptions - Search descriptions
 * - /fhir - FHIR API endpoint (R4)
 * 
 * Search Modes:
 * - STANDARD: Default search mode
 * - REGEX: Regular expression search
 * - WHOLE_WORD: Whole word matching
 * 
 * SNOMED CT Concept Structure:
 * Each concept has:
 * - conceptId: Unique identifier (e.g., "11661002")
 * - fsn (Fully Specified Name): Complete term with semantic tag
 *   Example: "Neuropathologist (occupation)"
 * - pt (Preferred Term): Short display name
 *   Example: "Neuropathologist"
 * - active: Boolean indicating if concept is active
 * - definitionStatus: PRIMITIVE or FULLY_DEFINED
 * 
 * Semantic Tags (Categories):
 * Semantic tags appear in parentheses at the end of FSN and categorize concepts:
 * 
 * Healthcare Professional Categories:
 * - (occupation) - Healthcare professionals and roles
 *   Examples: "Neuropathologist (occupation)", "Clinical nurse specialist (occupation)"
 * - (person) - Person types
 * 
 * Clinical Categories:
 * - (procedure) - Medical procedures and interventions
 *   Examples: "Referral to neuropathologist (procedure)"
 * - (finding) - Clinical findings and observations
 *   Examples: "Seen by neuropathologist (finding)"
 * - (disorder) - Diseases and disorders
 * - (body structure) - Anatomical structures
 * - (organism) - Organisms including bacteria, viruses
 * - (substance) - Chemical substances and medications
 * - (product) - Pharmaceutical products
 * 
 * Other Categories:
 * - (qualifier value) - Qualifiers for other concepts
 * - (environment) - Environmental settings
 *   Examples: "Specialist school (environment)"
 * - (physical object) - Physical objects and devices
 *   Examples: "Specialist static seat (physical object)"
 * - (regime/therapy) - Treatment regimens
 * - (event) - Events
 * - (observable entity) - Observable entities
 * - (specimen) - Specimen types
 * - (situation) - Clinical situations
 * 
 * For Doctor Specialties:
 * Most medical specialties use the (occupation) semantic tag.
 * Examples:
 * - "Neuropathologist (occupation)" - 11661002
 * - "Family medicine specialist (occupation)" - 62247001
 * - "Pain management specialist (occupation)" - 309337009
 * - "Sleep medicine specialist (occupation)" - 720503005
 * 
 * Response Structure:
 * {
 *   "items": [
 *     {
 *       "conceptId": "11661002",
 *       "active": true,
 *       "fsn": {
 *         "term": "Neuropathologist (occupation)",
 *         "lang": "en"
 *       },
 *       "pt": {
 *         "term": "Neuropathologist",
 *         "lang": "en"
 *       },
 *       "definitionStatus": "PRIMITIVE"
 *     }
 *   ],
 *   "total": 7,
 *   "limit": 50,
 *   "offset": 0
 * }
 */
class SnowstormService
{
    public $baseUrl;
    public $branch;

    public function __construct()
    {
        $this->baseUrl = ConfigurationHelper::get('snowstorm.url') ?? config('services.snowstorm.url', '');
        $this->branch = ConfigurationHelper::get('snowstorm.branch') ?? config('services.snowstorm.branch', 'MAIN');
    }

    /**
     * Search for concepts by term using the /MAIN/concepts endpoint.
     * This endpoint provides more accurate search results than the browser endpoint.
     *
     * @param string $term Search term
     * @param int $limit Maximum number of results (default: 10, max: 100)
     * @param int $offset Result offset for pagination (default: 0)
     * @param string $searchMode Search mode: STANDARD, REGEX, WHOLE_WORD (default: STANDARD)
     * @param array $conceptIds Optional array of concept IDs to filter results
     * @param string|null $semanticTag Optional semantic tag to filter (e.g. 'disorder', 'procedure')
     * @return array Array with 'items', 'total', 'limit', 'offset'
     */
    public function search(string $term, int $limit = 10, int $offset = 0, string $searchMode = 'STANDARD', ?array $conceptIds = [], string|null|array $semanticTag = null)
    {
        // Cek cache sebelum memanggil API eksternal
        $cacheKey = TerminologyCacheService::snomedKey([$term, $limit, $offset, $searchMode, $conceptIds, $semanticTag]);

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $termWithTag = $term;

            if ($semanticTag != null || $semanticTag != '') {
                if (is_array($semanticTag)) {
                    foreach ($semanticTag as $key => $value) {
                        $termWithTag .= " ($value)";
                    }
                } else {
                    $termWithTag .= " ($semanticTag)";
                }
            }

            $params = [
                'term' => $termWithTag,
                'activeFilter' => true,
                'limit' => min($limit, 100),
                'offset' => $offset,
                'searchMode' => $searchMode,
                'lang' => 'en',
                'includeLeafFlag' => false,
                'descriptionType' => '900000000000003001'
            ];

            if (!empty($conceptIds)) {
                $params['conceptIds'] = implode(',', $conceptIds);
            }

            $response = Http::get("{$this->baseUrl}/{$this->branch}/concepts", $params);

            if ($response->successful()) {
                $data = $response->json();
                $result = [
                    'items' => $data['items'] ?? [],
                    'total' => $data['total'] ?? 0,
                    'limit' => $data['limit'] ?? $limit,
                    'offset' => $data['offset'] ?? $offset,
                ];

                // Simpan ke cache hanya jika response valid (hindari cache error response)
                Cache::put($cacheKey, $result, TerminologyCacheService::TTL_SNOMED);

                return $result;
            }

            Log::error('Snowstorm API Error: ' . $response->body());
            return ['items' => [], 'total' => 0, 'limit' => $limit, 'offset' => $offset];
        } catch (\Exception $e) {
            Log::error('Snowstorm API Exception: ' . $e->getMessage());
            return ['items' => [], 'total' => 0, 'limit' => $limit, 'offset' => $offset];
        }
    }

    /**
     * Get a single concept by ID.
     * 
     * @param string $conceptId SNOMED CT Concept ID
     * @return array|null Concept details or null if not found
     */
    public function getConcept(string $conceptId)
    {
        try {
            $response = Http::get("{$this->baseUrl}/{$this->branch}/concepts/{$conceptId}");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Snowstorm API Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Search descriptions (alternative search method).
     * Useful for finding concepts by their descriptions/synonyms.
     * 
     * @param string $term Search term
     * @param int $limit Maximum number of results
     * @return array Array of description items
     */
    public function searchDescriptions(string $term, int $limit = 10)
    {
        try {
            $response = Http::get("{$this->baseUrl}/{$this->branch}/descriptions", [
                'term' => $term,
                'activeFilter' => true,
                'limit' => min($limit, 50),
                'lang' => 'en',
            ]);

            if ($response->successful()) {
                return $response->json()['items'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Snowstorm API Exception: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Test connection to Snowstorm API.
     * 
     * @return array Connection status
     */
    public function testConnection(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/version");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'version' => $data['version'] ?? 'Unknown',
                    'package' => $data['package'] ?? 'Unknown',
                    'build' => $data['build'] ?? 'Unknown',
                    'message' => 'Connection successful',
                ];
            }

            return [
                'success' => false,
                'version' => null,
                'message' => 'Failed to connect: ' . $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'version' => null,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }
}
