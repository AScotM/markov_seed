import random
import logging
from collections import defaultdict
from typing import Dict, List, Optional

class MarkovSeedGenerator:
    def __init__(self, n: int = 3, verbose: bool = False):
        """
        Enhanced Markov chain seed generator.
        
        Args:
            n: N-gram size (default 3)
            verbose: Debug output (default False)
        """
        self.n = n
        self.model: Dict[str, List[str]] = defaultdict(list)
        self.verbose = verbose
        self._setup_logging()

    def _setup_logging(self):
        """Configure logging handler."""
        logging.basicConfig(
            filename="seed_generator.log",
            level=logging.INFO,
            format="%(asctime)s - %(levelname)s - %(message)s",
            filemode="a"
        )

    def _normalize_text(self, text: str) -> str:
        """Normalize input text consistently."""
        text = text.lower()
        replacements = {'\n': ' ', '\t': ' ', '\r': ' '}
        for old, new in replacements.items():
            text = text.replace(old, new)
        return text.strip()

    def train(self, text: str, min_transitions: int = 5) -> None:
        """
        Train the Markov model with validation.
        
        Args:
            text: Training text
            min_transitions: Minimum required transitions per n-gram
            
        Raises:
            ValueError: For invalid input
        """
        if not text:
            logging.error("Empty training text")
            raise ValueError("Training text cannot be empty")
            
        text = self._normalize_text(text)
        if len(text) < self.n + min_transitions:
            raise ValueError(f"Text too short for n={self.n}")
            
        # Build transition model
        for i in range(len(text) - self.n):
            key = text[i:i + self.n]
            next_char = text[i + self.n]
            self.model[key].append(next_char)
            
        logging.info(f"Trained model with {len(self.model)} n-grams")

    def generate(self, length: int = 12, max_attempts: int = 10) -> Optional[str]:
        """
        Generate a seed with retry logic.
        
        Args:
            length: Desired seed length
            max_attempts: Maximum generation attempts
            
        Returns:
            Generated seed or None if failed
        """
        if not self.model:
            logging.error("Untrained model")
            raise RuntimeError("Model must be trained first")
            
        for attempt in range(max_attempts):
            try:
                seed = random.choice([k for k in self.model.keys() if len(self.model[k]) > 0])
                output = list(seed)
                
                for _ in range(length - self.n):
                    possible_chars = self.model.get(seed[-self.n:], [])
                    if not possible_chars:
                        break
                    next_char = random.choice(possible_chars)
                    output.append(next_char)
                    seed = seed[1:] + next_char
                
                if len(output) >= length * 0.8:  # Accept partial seeds
                    result = ''.join(output)
                    logging.info(f"Generated seed: {result}")
                    return result
                    
            except Exception as e:
                logging.warning(f"Attempt {attempt + 1} failed: {str(e)}")
                
        logging.error(f"Failed after {max_attempts} attempts")
        return None

    def save_model(self, filename: str) -> bool:
        """Save model to file."""
        try:
            with open(filename, 'w') as f:
                json.dump(self.model, f)
            return True
        except Exception as e:
            logging.error(f"Failed to save model: {str(e)}")
            return False

    @classmethod
    def load_model(cls, filename: str, n: int = 3) -> 'MarkovSeedGenerator':
        """Load model from file."""
        try:
            with open(filename) as f:
                model = json.load(f)
            generator = cls(n=n)
            generator.model = defaultdict(list, model)
            return generator
        except Exception as e:
            logging.error(f"Failed to load model: {str(e)}")
            raise

if __name__ == "__main__":
    try:
        # Example with better training data
        training_data = """
            abcdefghijklmnopqrstuvwxyz
            ABCDEFGHIJKLMNOPQRSTUVWXYZ
            0123456789!@#$%^&*()
            Lorem ipsum dolor sit amet
            The quick brown fox jumps
        """
        
        generator = MarkovSeedGenerator(n=3, verbose=True)
        generator.train(training_data, min_transitions=3)
        
        # Generate and print 5 seeds
        for i in range(5):
            seed = generator.generate(16)
            if seed:
                print(f"Seed {i+1}: {seed}")
                
        # Save model example
        generator.save_model("markov_model.json")
        
    except Exception as e:
        logging.exception("Fatal error in main execution")
        print(f"Error: {str(e)}")
