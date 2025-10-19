<?php
class MarkovSeedGenerator {
    private $n;
    private $model = [];
    private $text;
    private $verbose;
    private $logMessages = [];

    public function __construct(int $n, bool $verbose = false) {
        if ($n <= 0) {
            throw new InvalidArgumentException("n must be positive");
        }
        $this->n = $n;
        $this->verbose = $verbose;
    }

    public function train(string $text, bool $sanitize = true): void {
        // Optional input sanitization to remove control characters
        if ($sanitize) {
            $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
        }

        $length = mb_strlen($text);
        if ($length <= $this->n) {
            throw new InvalidArgumentException("Text length ($length) must be greater than n ({$this->n})");
        }

        $this->text = $text;

        for ($i = 0; $i <= $length - $this->n - 1; $i++) {
            $key = mb_substr($text, $i, $this->n);
            $nextChar = mb_substr($text, $i + $this->n, 1);

            if (!isset($this->model[$key])) {
                $this->model[$key] = [];
            }
            if (!isset($this->model[$key][$nextChar])) {
                $this->model[$key][$nextChar] = 0;
            }
            $this->model[$key][$nextChar]++;
        }

        $this->log("Trained model with " . count($this->model) . " n-grams");
    }

    public function trainFromFile(string $filename, bool $sanitize = true): void {
        if (!file_exists($filename)) {
            throw new RuntimeException("Training file $filename does not exist");
        }

        $this->log("Training from file: $filename");
        $fileSize = filesize($filename);
        $processedBytes = 0;
        
        $handle = fopen($filename, 'r');
        if (!$handle) {
            throw new RuntimeException("Could not open file: $filename");
        }

        $buffer = '';
        $chunkSize = 8192; // 8KB chunks

        while (($chunk = fread($handle, $chunkSize)) !== false && !feof($handle)) {
            $processedBytes += strlen($chunk);
            $buffer .= $chunk;
            
            // Process complete n-grams from the buffer
            $this->processChunk($buffer, $sanitize);
            
            // Keep the last n-1 characters for continuity between chunks
            $buffer = mb_substr($buffer, -$this->n);
            
            $this->log("Processed " . min($processedBytes, $fileSize) . "/$fileSize bytes (" . 
                      round(min($processedBytes, $fileSize) / $fileSize * 100, 1) . "%)");
        }

        fclose($handle);
        $this->log("Completed training with " . count($this->model) . " n-grams");
    }

    private function processChunk(string &$chunk, bool $sanitize = true): void {
        if ($sanitize) {
            $chunk = preg_replace('/[\x00-\x1F\x7F]/u', '', $chunk);
        }

        $length = mb_strlen($chunk);
        if ($length <= $this->n) {
            return;
        }

        for ($i = 0; $i <= $length - $this->n - 1; $i++) {
            $key = mb_substr($chunk, $i, $this->n);
            $nextChar = mb_substr($chunk, $i + $this->n, 1);

            if (!isset($this->model[$key])) {
                $this->model[$key] = [];
            }
            if (!isset($this->model[$key][$nextChar])) {
                $this->model[$key][$nextChar] = 0;
            }
            $this->model[$key][$nextChar]++;
        }
    }

    public function generate(int $length, ?string $startWith = null): string {
        if (empty($this->model)) {
            throw new RuntimeException("Model not trained");
        }
        if ($length < $this->n) {
            throw new InvalidArgumentException("Requested length ($length) must be at least n ({$this->n})");
        }

        $keys = array_keys($this->model);
        
        // Use provided starting n-gram or random selection
        if ($startWith && mb_strlen($startWith) === $this->n && isset($this->model[$startWith])) {
            $output = $startWith;
            $this->log("Starting generation with: '$startWith'");
        } else {
            if ($startWith) {
                $this->log("Warning: Starting n-gram '$startWith' not found, using random");
            }
            $output = $keys[random_int(0, count($keys) - 1)];
        }

        while (mb_strlen($output) < $length) {
            $lastN = mb_substr($output, -$this->n);

            if (empty($this->model[$lastN])) {
                $this->log("Fallback: n-gram '$lastN' not found, finding similar");
                $similar = $this->findSimilarNgram($lastN);
                if ($similar) {
                    $output .= mb_substr($similar, -1);
                    $this->log("Used similar n-gram: '$similar'");
                    continue;
                } else {
                    $this->log("No similar n-gram found, restarting with new seed");
                    $output .= $keys[random_int(0, count($keys) - 1)];
                    continue;
                }
            }

            // Weighted random selection
            $possibleNext = $this->model[$lastN];
            $total = array_sum($possibleNext);
            $rand = random_int(0, $total - 1);
            $current = 0;
            foreach ($possibleNext as $char => $count) {
                $current += $count;
                if ($rand < $current) {
                    $output .= $char;
                    break;
                }
            }
        }

        return mb_substr($output, 0, $length);
    }

    private function findSimilarNgram(string $target): string {
        $bestMatch = '';
        $bestDistance = PHP_INT_MAX;
        $keys = array_keys($this->model);
        
        foreach ($keys as $key) {
            $distance = $this->levenshteinDistance($target, $key);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestMatch = $key;
            }
            
            // Early exit if we find a very close match
            if ($bestDistance <= 1) {
                break;
            }
        }
        
        return $bestMatch;
    }

    private function levenshteinDistance(string $s1, string $s2): int {
        $len1 = mb_strlen($s1);
        $len2 = mb_strlen($s2);
        
        if ($len1 == 0) return $len2;
        if ($len2 == 0) return $len1;
        
        $matrix = [];
        
        for ($i = 0; $i <= $len1; $i++) {
            $matrix[$i] = [$i];
        }
        for ($j = 0; $j <= $len2; $j++) {
            $matrix[0][$j] = $j;
        }
        
        for ($i = 1; $i <= $len1; $i++) {
            $c1 = mb_substr($s1, $i - 1, 1);
            for ($j = 1; $j <= $len2; $j++) {
                $c2 = mb_substr($s2, $j - 1, 1);
                
                $cost = ($c1 === $c2) ? 0 : 1;
                $matrix[$i][$j] = min(
                    $matrix[$i-1][$j] + 1,     // deletion
                    $matrix[$i][$j-1] + 1,     // insertion
                    $matrix[$i-1][$j-1] + $cost // substitution
                );
            }
        }
        
        return $matrix[$len1][$len2];
    }

    public function validateModel(): array {
        $issues = [];
        $deadEnds = 0;
        $totalNgrams = count($this->model);
        
        foreach ($this->model as $ngram => $transitions) {
            $totalTransitions = array_sum($transitions);
            if ($totalTransitions === 0) {
                $issues[] = "Dead end n-gram: '$ngram' (no transitions)";
                $deadEnds++;
            }
            
            // Check for n-grams with very few transitions (potential dead ends)
            if ($totalTransitions <= 1 && count($transitions) === 1) {
                $issues[] = "Limited transitions for n-gram: '$ngram' (only $totalTransitions transition)";
            }
        }
        
        if ($deadEnds > 0) {
            $issues[] = "Model health: $deadEnds/$totalNgrams n-grams are dead ends (" . 
                       round($deadEnds/$totalNgrams * 100, 1) . "%)";
        } else {
            $issues[] = "Model health: No dead end n-grams found";
        }
        
        $issues[] = "Total n-grams in model: $totalNgrams";
        
        return $issues;
    }

    public function saveModel(string $filename): void {
        // Use JSON for security instead of serialize
        $data = json_encode($this->model, JSON_PRETTY_PRINT);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Failed to encode model: " . json_last_error_msg());
        }
        
        if (file_put_contents($filename, $data) === false) {
            throw new RuntimeException("Failed to save model to: $filename");
        }
        
        $this->log("Model saved to $filename");
    }

    public function loadModel(string $filename): void {
        if (!file_exists($filename)) {
            throw new RuntimeException("Model file $filename does not exist");
        }
        
        $data = file_get_contents($filename);
        if ($data === false) {
            throw new RuntimeException("Failed to read model file: $filename");
        }
        
        $this->model = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid model file format: " . json_last_error_msg());
        }
        
        $this->log("Model loaded from $filename with " . count($this->model) . " n-grams");
    }

    public function getModelStats(): array {
        $stats = [
            'n_grams' => count($this->model),
            'total_transitions' => 0,
            'avg_transitions_per_ngram' => 0,
            'max_transitions' => 0,
            'min_transitions' => PHP_INT_MAX,
        ];
        
        foreach ($this->model as $transitions) {
            $count = count($transitions);
            $stats['total_transitions'] += $count;
            $stats['max_transitions'] = max($stats['max_transitions'], $count);
            $stats['min_transitions'] = min($stats['min_transitions'], $count);
        }
        
        if ($stats['n_grams'] > 0) {
            $stats['avg_transitions_per_ngram'] = $stats['total_transitions'] / $stats['n_grams'];
        }
        
        return $stats;
    }

    private function log(string $message): void {
        if ($this->verbose) {
            $this->logMessages[] = $message;
            echo $message . "\n";
        }
    }

    public function getLogs(): array {
        return $this->logMessages;
    }

    public function clearLogs(): void {
        $this->logMessages = [];
    }
}

// Example usage with all new features
$trainingText = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>/?";
$trainingText .= " The quick brown fox jumps over the lazy dog. Pack my box with five dozen liquor jugs.";

try {
    $markov = new MarkovSeedGenerator(3, true);
    
    // Train from string
    $markov->train($trainingText);
    
    // Or train from file for large datasets
    // $markov->trainFromFile('large_training_data.txt');
    
    // Validate model
    $validation = $markov->validateModel();
    echo "Model Validation:\n";
    foreach ($validation as $issue) {
        echo "- $issue\n";
    }
    echo "\n";
    
    // Get statistics
    $stats = $markov->getModelStats();
    echo "Model Statistics:\n";
    foreach ($stats as $key => $value) {
        echo "- " . str_replace('_', ' ', $key) . ": $value\n";
    }
    echo "\n";
    
    // Generate with different options
    for ($i = 0; $i < 5; $i++) {
        $seed = $markov->generate(16);
        echo "Generated " . ($i + 1) . ": " . $seed . "\n";
    }
    
    echo "\n";
    
    // Generate with specific starting point
    $seeded = $markov->generate(20, "The");
    echo "Seeded generation: " . $seeded . "\n";
    
    // Save and load model
    $markov->saveModel('markov_model.json');
    
    // Create new instance and load model
    $markov2 = new MarkovSeedGenerator(3, true);
    $markov2->loadModel('markov_model.json');
    $reloadedSeed = $markov2->generate(16);
    echo "From reloaded model: " . $reloadedSeed . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
