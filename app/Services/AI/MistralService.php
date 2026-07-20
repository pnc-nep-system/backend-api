<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MistralService
{
    private ?string $apiKey;
    private string $model;
    private string $endpoint;
    private int $timeout;
    private int $retryAttempts;
    private int $retryDelay;

    public function __construct()
    {
        $this->apiKey = config('services.mistral.api_key');
        $this->model = config('services.mistral.model', 'mistral-small-latest');
        $this->timeout = config('services.mistral.timeout', 30);
        $this->retryAttempts = config('services.mistral.retry_attempts', 2);
        $this->retryDelay = config('services.mistral.retry_delay', 500);
        $this->endpoint = "https://api.mistral.ai/v1/chat/completions";

        // Validate API key is configured
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Mistral API key is not configured. Please set MISTRAL_API_KEY in your environment variables.', 500);
        }
    }

    /**
     * Send a prompt to Mistral and return the parsed AI response.
     *
     * @param string $prompt The constructed prompt to send to Mistral
     * @return array The parsed AI response
     * @throws \RuntimeException If the API call fails or response parsing fails
     */
    public function generateContent(string $prompt): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.7,
            'top_p' => 0.95,
            'max_tokens' => 4096,
        ];

        $attempt = 0;
        $maxAttempts = $this->retryAttempts + 1;

        while ($attempt < $maxAttempts) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $this->apiKey,
                    ])
                    ->post($this->endpoint, $payload);

                if ($response->successful()) {
                    $responseData = $response->json();

                    if (empty($responseData)) {
                        Log::error('Mistral API returned empty response');
                        throw new \RuntimeException('Mistral API returned an empty response.');
                    }

                    return $this->parseResponse($responseData);
                }

                // Handle failed responses
                $statusCode = $response->status();
                $errorBody = $response->body();

                Log::error('Mistral API request failed', [
                    'status_code' => $statusCode,
                    'attempt' => $attempt + 1,
                    'error_message' => $this->getErrorMessage($response->json() ?? [], $statusCode),
                ]);

                // Non-retryable errors
                if (in_array($statusCode, [400, 401, 403, 404])) {
                    throw new \RuntimeException(
                        "Mistral API returned error status {$statusCode}: " . $this->getErrorMessage($response->json() ?? [], $statusCode),
                        $statusCode
                    );
                }

                // Retryable errors: 429, 5xx
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw new \RuntimeException(
                        "Mistral API returned error status {$statusCode}: " . $this->getErrorMessage($response->json() ?? [], $statusCode),
                        $statusCode
                    );
                }

                // Wait before retrying
                usleep($this->retryDelay * 1000);

            } catch (ConnectionException $e) {
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    Log::error('Mistral API connection error', [
                        'message' => $e->getMessage(),
                        'attempts' => $attempt,
                    ]);
                    throw new \RuntimeException(
                        'Unable to connect to Mistral AI service. Please try again later.',
                        503
                    );
                }
                usleep($this->retryDelay * 1000);
            } catch (\RuntimeException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('Unexpected error in Mistral service', [
                    'error_type' => get_class($e),
                    'message' => $e->getMessage(),
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
     * Parse the Mistral API response into a structured array.
     *
     * @param array $responseData Raw API response
     * @return array Parsed structured response
     */
    private function parseResponse(array $responseData): array
    {
        // Extract the text from Mistral's response structure
        $text = $this->extractTextFromResponse($responseData);

        if ($text === null) {
            Log::error('Mistral API response missing expected text content', [
                'response_structure' => array_keys($responseData),
            ]);
            throw new \RuntimeException('AI response did not contain expected content.');
        }

        // Try to parse the text as JSON (as instructed in the prompt)
        $parsed = $this->parseJsonFromText($text);

        if ($parsed === null) {
            Log::warning('Mistral response was not valid JSON, returning raw text', [
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
     * Extract the text content from Mistral's response structure.
     *
     * @param array $responseData
     * @return string|null
     */
    private function extractTextFromResponse(array $responseData): ?string
    {
        $choices = $responseData['choices'] ?? [];

        if (empty($choices)) {
            Log::warning('Mistral response missing choices', [
                'response_structure' => array_keys($responseData),
            ]);
            return null;
        }

        $firstChoice = $choices[0] ?? [];
        $message = $firstChoice['message'] ?? [];
        $content = $message['content'] ?? null;

        if (empty($content)) {
            $finishReason = $firstChoice['finish_reason'] ?? 'UNKNOWN';
            if ($finishReason !== 'stop') {
                Log::warning('Mistral response finished with non-stop reason', [
                    'finish_reason' => $finishReason,
                ]);
            }
            return null;
        }

        return $content;
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
        $message = $errorBody['message'] ?? 'Unknown error';

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