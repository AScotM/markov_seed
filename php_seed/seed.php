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

    public function generate(int $length): string {
        if (empty($this->model)) {
            throw new RuntimeException("Model not trained");
        }
        if ($length < $this->n) {
            throw new InvalidArgumentException("Requested length ($length) must be at least n ({$this->n})");
        }

        $keys = array_keys($this->model);
        $seed = $keys[random_int(0, count($keys) - 1)];
        $output = $seed;

        while (mb_strlen($output) < $length) {
            $lastN = mb_substr($output, -$this->n);

            if (empty($this->model[$lastN])) {
                $this->log("Fallback: n-gram '$lastN' not found, restarting with new seed");
                $seed = $keys[random_int(0, count($keys) - 1)];
                $output .= mb_substr($seed, -1); // Append last character of new seed
                continue;
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

    public function saveModel(string $filename): void {
        file_put_contents($filename, serialize($this->model));
        $this->log("Model saved to $filename");
    }

    public function loadModel(string $filename): void {
        if (!file_exists($filename)) {
            throw new RuntimeException("Model file $filename does not exist");
        }
        $this->model = unserialize(file_get_contents($filename));
        $this->log("Model loaded from $filename");
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
}

// Example usage
$trainingText = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>/?";
$trainingText .= " The quick brown fox jumps over the lazy dog. Pack my box with five dozen liquor jugs.";

try {
    $markov = new MarkovSeedGenerator(3, true);
    $markov->train($trainingText);

    for ($i = 0; $i < 5; $i++) {
        $seed = $markov->generate(16);
        echo "Generated " . ($i + 1) . ": " . $seed . "\n";
    }

    // Optional: Save model for reuse
    $markov->saveModel('markov_model.dat');
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
