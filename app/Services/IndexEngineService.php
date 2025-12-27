<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Token;
use App\Models\Posting;
use App\Models\Url;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Wamania\Snowball\Stemmer\English;

class IndexEngineService
{
    private English $stemmer;
    private array $stopwords;

    public function __construct()
    {
        $this->stemmer = new English();
        $this->stopwords = $this->loadStopwords();
    }

    /**
     * Index a document into the inverted index.
     */
    public function indexDocument(Url $url, string $title, ?string $description, ?string $content): void
    {
        try {
            DB::beginTransaction();

            // Combine all text for indexing
            $fullText = implode(' ', array_filter([$title, $description, $content]));
            $wordCount = str_word_count($fullText);
            $contentHash = hash('sha256', $fullText);

            // Create or update document
            $document = Document::updateOrCreate(
                ['url_id' => $url->id],
                [
                    'title' => $title,
                    'description' => $description,
                    'content' => $content,
                    'content_hash' => $contentHash,
                    'word_count' => $wordCount,
                    'indexed_at' => now(),
                ]
            );

            // Tokenize and build inverted index
            $tokens = $this->tokenize($fullText);
            $this->buildInvertedIndex($document, $tokens);

            DB::commit();

            Log::info('Document indexed', [
                'url_id' => $url->id,
                'word_count' => $wordCount,
                'unique_tokens' => count(array_unique($tokens)),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Indexing failed', [
                'url_id' => $url->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Tokenize text into searchable tokens.
     */
    private function tokenize(string $text): array
    {
        // Normalize text
        $text = strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        $tokens = [];
        foreach ($words as $word) {
            // Skip if too short or is stopword
            if (strlen($word) < config('indexer.min_token_length', 2)) {
                continue;
            }
            
            if (in_array($word, $this->stopwords)) {
                continue;
            }

            // Apply stemming if enabled
            if (config('indexer.use_stemming', true)) {
                $word = $this->stemmer->stem($word);
            }

            $tokens[] = $word;
        }

        return $tokens;
    }

    /**
     * Build inverted index from tokens.
     */
    private function buildInvertedIndex(Document $document, array $tokens): void
    {
        // Calculate term frequencies
        $termFrequencies = array_count_values($tokens);
        
        // Track positions for phrase queries
        $positions = [];
        foreach ($tokens as $pos => $token) {
            if (!isset($positions[$token])) {
                $positions[$token] = [];
            }
            $positions[$token][] = $pos;
        }

        // Delete existing postings for this document
        Posting::where('url_id', $document->url_id)->delete();

        // Insert/update tokens and postings
        foreach ($termFrequencies as $token => $frequency) {
            // Get or create token
            $tokenModel = Token::firstOrCreate(
                ['token' => $token],
                ['document_frequency' => 0]
            );

            // Create posting
            Posting::create([
                'token_id' => $tokenModel->id,
                'url_id' => $document->url_id,
                'term_frequency' => $frequency,
                'positions' => $positions[$token],
            ]);

            // Update document frequency (count distinct documents containing this token)
            $tokenModel->document_frequency = Posting::where('token_id', $tokenModel->id)
                ->distinct('url_id')
                ->count('url_id');
            $tokenModel->save();
        }
    }

    /**
     * Load stopwords list.
     */
    private function loadStopwords(): array
    {
        return [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
            'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
            'to', 'was', 'will', 'with', 'the', 'this', 'but', 'they', 'have',
            'had', 'what', 'when', 'where', 'who', 'which', 'why', 'how',
        ];
    }

    /**
     * Get average document length for BM25 scoring.
     */
    public function getAverageDocumentLength(): float
    {
        return Document::avg('word_count') ?: 100.0;
    }

    /**
     * Get total document count for IDF calculation.
     */
    public function getTotalDocumentCount(): int
    {
        return Document::count();
    }
}
