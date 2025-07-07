<?php
class MarkovSeedGenerator {
    private $n;
    private $model = [];
    private $text;
    private $verbose;

    public function __construct(int $n, bool $verbose = false) {
        if ($n <= 0) {
            throw new InvalidArgumentException("n must be positive");
        }
        $this->n = $n;
        $this->verbose = $verbose;
    }

    public function train(string $text): void {
        $length = mb_strlen($text);
        if ($length <= $this->n) {
            throw new InvalidArgumentException("Text too short");
        }

        $this->text = $text;

        for ($i = 0; $i <= $length - $this->n - 1; $i++) {
            $key = mb_substr($text, $i, $this->n);
            $nextChar = mb_substr($text, $i + $this->n, 1);

            if (!isset($this->model[$key])) {
                $this->model[$key] = [];
            }
            $this->model[$key][] = $nextChar;
        }

        if ($this->verbose) {
            echo "Trained model with " . count($this->model) . " n-grams\n";
        }
    }

    public function generate(int $length): string {
        if (empty($this->model)) {
            throw new RuntimeException("Untrained model");
        }
        if ($length < $this->n) {
            throw new InvalidArgumentException("Length too short");
        }

        $keys = array_keys($this->model);
        $seed = $keys[random_int(0, count($keys) - 1)];
        $output = $seed;

        while (mb_strlen($output) < $length) {
            $lastN = mb_substr($output, -$this->n);
            
            if (empty($this->model[$lastN])) {
                // Fallback to random character
                $fallback = mb_substr($this->text, random_int(0, mb_strlen($this->text) - 1), 1);
                $output .= $fallback;
                continue;
            }

            $possibleNext = $this->model[$lastN];
            $nextChar = $possibleNext[random_int(0, count($possibleNext) - 1)];
            $output .= $nextChar;
        }

        return mb_substr($output, 0, $length);
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
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
