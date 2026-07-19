<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private ?string $apiKey;
    private string $model;
    private string $endpoint;
    private int $timeout;
    private int $retryAttempts;
    private int $retryDelay;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-2.0-flash');
        $this->timeout = config('services.gemini.timeout', 30);
        $this->retryAttempts = config('services.gemini.retry_attempts', 2);
        $this->retryDelay = config('services.gemini.retry_delay', 500);
        $this->endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";
    }

    /**
     * Send a prompt to Gemini and return the parsed AI response.
     *
     * @param string $prompt The constructed prompt to send to Gemini
     * @return array The parsed AI response
     * @throws \RuntimeException If the API call fails or response parsing fails
     */
    public function generateContent(string $prompt): array
    {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 4096,
            ],
        ];

        $attempt = 0;
        $maxAttempts = $this->retryAttempts + 1;

        while ($attempt < $maxAttempts) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-goog-api-key' => $this->apiKey,
                    ])
                    ->post($this->endpoint, $payload);

                if ($response->successful()) {
                    $responseData = $response->json();

                    if (empty($responseData)) {
                        Log::error('Gemini API returned empty response');
                        throw new \RuntimeException('Gemini API returned an empty response.');
                    }

                    return $this->parseResponse($responseData);
                }

                // Handle failed responses
                $statusCode = $response->status();
                $errorBody = $response->body();

                Log::error('Gemini API request failed', [
                    'status_code' => $statusCode,
                    'attempt' => $attempt + 1,
                    'error_preview' => mb_substr($errorBody, 0, 500),
                ]);

                // Non-retryable errors
                if (in_array($statusCode, [400, 401, 403, 404])) {
                    throw new \RuntimeException(
                        "Gemini API returned error status {$statusCode}: " . $this->getErrorMessage($response->json() ?? [], $statusCode),
                        $statusCode
                    );
                }

                // Retryable errors: 429, 5xx
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw new \RuntimeException(
                        "Gemini API returned error status {$statusCode}: " . $this->getErrorMessage($response->json() ?? [], $statusCode),
                        $statusCode
                    );
                }

                // Wait before retrying
                usleep($this->retryDelay * 1000);

            } catch (ConnectionException $e) {
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    Log::error('Gemini API connection error', [
                        'message' => $e->getMessage(),
                        'attempts' => $attempt,
                    ]);
                    throw new \RuntimeException(
                        'Unable to connect to Gemini AI service. Please try again later.',
                        503
                    );
                }
                usleep($this->retryDelay * 1000);
            } catch (\RuntimeException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('Unexpected error in Gemini service', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new \RuntimeException(
                    'An unexpected error occurred while generating advisory content.',
                    500
                );
            }
        }

        throw new \RuntimeException(
            'AI service request failed after multiple attempts.',
            503
        );
    }

    /**
     * Parse the Gemini API response into a structured array.
     *
     * @param array $responseData Raw API response
     * @return array Parsed structured response
     */
    private function parseResponse(array $responseData): array
    {
        // Extract the text from Gemini's response structure
        $text = $this->extractTextFromResponse($responseData);

        if ($text === null) {
            Log::error('Gemini API response missing expected text content', [
                'response_structure' => array_keys($responseData),
            ]);
            throw new \RuntimeException('AI response did not contain expected content.');
        }

        // Try to parse the text as JSON (as instructed in the prompt)
        $parsed = $this->parseJsonFromText($text);

        if ($parsed === null) {
            Log::warning('Gemini response was not valid JSON, returning raw text', [
                'text_preview' => mb_substr($text, 0, 200),
            ]);

            // Return the raw text wrapped in a minimal structure
            return [
                'executive_summary' => $text,
                'similar_or_overlapping_programmes' => [],
                'potential_duplication' => 'Unable to parse structured response.',
                'coverage_gaps' => 'Unable to parse structured response.',
                'recommendations' => 'Unable to parse structured response.',
                'confidence_notes' => 'The AI response could not be parsed into a structured format. Raw response has been included in the executive summary.',
            ];
        }

        // Validate and ensure all expected keys exist
        return $this->ensureExpectedKeys($parsed);
    }

    /**
     * Extract the text content from Gemini's response structure.
     *
     * @param array $responseData
     * @return string|null
     */
    private function extractTextFromResponse(array $responseData): ?string
    {
        $candidates = $responseData['candidates'] ?? [];

        if (empty($candidates)) {
            // Check for blocked content
            $promptFeedback = $responseData['promptFeedback'] ?? [];
            if (!empty($promptFeedback['blockReason'])) {
                Log::warning('Gemini response blocked', [
                    'block_reason' => $promptFeedback['blockReason'],
                ]);
                throw new \RuntimeException(
                    'AI content generation was blocked: ' . ($promptFeedback['blockReason'] ?? 'Unknown reason')
                );
            }
            return null;
        }

        $firstCandidate = $candidates[0] ?? [];
        $content = $firstCandidate['content'] ?? [];
        $parts = $content['parts'] ?? [];

        if (empty($parts)) {
            // Check finish reason for errors
            $finishReason = $firstCandidate['finishReason'] ?? 'UNKNOWN';
            if ($finishReason !== 'STOP') {
                Log::warning('Gemini response finished with non-STOP reason', [
                    'finish_reason' => $finishReason,
                ]);
            }
            return null;
        }

        // Concatenate all text parts
        $textParts = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $textParts[] = $part['text'];
            }
        }

        return !empty($textParts) ? implode("\n", $textParts) : null;
    }

    /**
     * Attempt to parse JSON from the response text, handling markdown code blocks.
     *
     * @param string $text
     * @return array|null
     */
    private function parseJsonFromText(string $text): ?array
    {
        // Try direct JSON parse first
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try extracting JSON from markdown code block
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Try to find a JSON object in the text using regex
        if (preg_match('/\{[^{}]*"executive_summary"[^{}]*\}/', $text, $jsonMatches)) {
            $decoded = json_decode($jsonMatches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Ensure all expected keys are present in the parsed response.
     *
     * @param array $parsed
     * @return array
     */
    private function ensureExpectedKeys(array $parsed): array
    {
        $defaults = [
            'executive_summary' => 'No executive summary was generated.',
            'similar_or_overlapping_programmes' => [],
            'potential_duplication' => 'No duplication assessment was generated.',
            'coverage_gaps' => 'No coverage gap analysis was generated.',
            'recommendations' => 'No recommendations were generated.',
            'confidence_notes' => '',
        ];

        return array_merge($defaults, $parsed);
    }

    /**
     * Get a user-friendly error message from the API error response.
     *
     * @param array $errorBody
     * @param int $statusCode
     * @return string
     */
    private function getErrorMessage(array $errorBody, int $statusCode): string
    {
        $error = $errorBody['error'] ?? [];
        $message = $error['message'] ?? 'Unknown error';

        return match ($statusCode) {
            400 => 'Invalid request: ' . $message,
            401, 403 => 'Authentication failed. Check API key configuration.',
            404 => 'AI model not found. Check model configuration.',
            429 => 'Rate limit exceeded. Please wait and try again.',
            500, 502, 503 => 'AI service is temporarily unavailable. Please try again later.',
            default => 'Unexpected error: ' . $message,
        };
    }
}