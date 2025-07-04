import random
import logging
import re
from collections import defaultdict
from typing import List, Optional, Dict, Any
import pickle
import json

class MarkovSeedGenerator:
    """
    MarkovSeedGenerator generates pseudo-random seeds based on character-level n-grams.
    Note: This generator is NOT cryptographically secure.
    """
    def __init__(
        self,
        n: int = 3,
        verbose: bool = False,
        random_seed: Optional[int] = None,
        log_to_file: bool = False,
        log_level: str = "INFO",
        fallback_char: str = " "
    ):
        """
        Initialize the MarkovSeedGenerator.

        Args:
            n: Character-level n-gram size (must be >= 1).
            verbose: Print detailed output to stdout.
            random_seed: Seed for reproducibility (None for no seed).
            log_to_file: Log to "seed_generator.log" if True, else stdout.
            log_level: Logging level (DEBUG, INFO, WARNING, ERROR, CRITICAL).
            fallback_char: Character to use if no transitions exist (default: space).
        """
        if not isinstance(n, int) or n < 1:
            raise ValueError("n must be a positive integer.")
        self.n = n
        self.model: defaultdict[str, List[str]] = defaultdict(list)
        self.verbose = verbose
        self.fallback_char = fallback_char

        # Initialize randomness
        if random_seed is not None:
            random.seed(random_seed)

        # Configure logging
        log_format = "%(asctime)s - %(levelname)s - %(message)s"
        log_level = getattr(logging, log_level.upper(), logging.INFO)
        if log_to_file:
            logging.basicConfig(filename="seed_generator.log", level=log_level, format=log_format)
        else:
            logging.basicConfig(level=log_level, format=log_format)

    def train(self, text: str) -> None:
        """
        Train the Markov model on character sequences.

        Args:
            text: Training text for the Markov model.
        
        Raises:
            ValueError: If text is empty or shorter than n-gram size.
        """
        if not text:
            logging.error("Training failed: Input text is empty.")
            raise ValueError("Training text cannot be empty.")
        if len(text) < self.n:
            logging.error(f"Training failed: Text (len={len(text)}) is shorter than n-gram size (n={self.n}).")
            raise ValueError("Text must be at least as long as the n-gram size.")

        # Normalize text (lowercase, replace whitespace, remove duplicates)
        text = re.sub(r"\s+", " ", text.lower().strip())
        for i in range(len(text) - self.n):
            key = text[i:i + self.n]
            next_char = text[i + self.n]
            self.model[key].append(next_char)

        if self.verbose:
            print(f"Trained model with {len(self.model)} n-grams.")
        logging.info(f"Model trained. Keys: {len(self.model)}")

    def generate(self, length: int = 12, max_attempts: int = 10) -> str:
        """
        Generate a seed using the trained Markov model.

        Args:
            length: Desired seed length.
            max_attempts: Max retries if dead-end is reached.
        
        Returns:
            Generated seed string.

        Raises:
            RuntimeError: If model is untrained or generation fails.
        """
        if not self.model:
            logging.error("Generation failed: Model is untrained.")
            raise RuntimeError("Train the model first.")

        seed = random.choice(list(self.model.keys()))
        output = list(seed)

        for _ in range(length - self.n):
            if seed in self.model and self.model[seed]:
                next_char = random.choice(self.model[seed])
                output.append(next_char)
                seed = seed[1:] + next_char
            else:
                # Fallback: Try another key or use fallback_char
                for _ in range(max_attempts):
                    seed = random.choice(list(self.model.keys()))
                    if seed in self.model and self.model[seed]:
                        break
                else:
                    next_char = self.fallback_char
                output.append(next_char)
                seed = seed[1:] + next_char

        generated_seed = "".join(output)[:length]
        if self.verbose:
            print(f"Generated: {generated_seed}")
        logging.info(f"Generated seed: {generated_seed}")
        return generated_seed

    def reset_model(self) -> None:
        """Reset the Markov model to an empty state."""
        self.model.clear()
        logging.info("Model reset.")

    def save_model(self, filepath: str, format: str = "pickle") -> None:
        """
        Save the trained model to a file.

        Args:
            filepath: Path to save the model.
            format: Serialization format ("pickle" or "json").
        """
        if format == "pickle":
            with open(filepath, "wb") as f:
                pickle.dump(dict(self.model), f)
        elif format == "json":
            with open(filepath, "w") as f:
                json.dump(dict(self.model), f)
        else:
            raise ValueError("Format must be 'pickle' or 'json'.")
        logging.info(f"Model saved to {filepath}.")

    def load_model(self, filepath: str, format: str = "pickle") -> None:
        """
        Load a trained model from a file.

        Args:
            filepath: Path to the saved model.
            format: Serialization format ("pickle" or "json").
        """
        if format == "pickle":
            with open(filepath, "rb") as f:
                model_dict = pickle.load(f)
        elif format == "json":
            with open(filepath, "r") as f:
                model_dict = json.load(f)
        else:
            raise ValueError("Format must be 'pickle' or 'json'.")
        self.model = defaultdict(list, model_dict)
        logging.info(f"Model loaded from {filepath}.")

if __name__ == "__main__":
    # Example usage
    training_text = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()"

    # Initialize with verbose logging
    generator = MarkovSeedGenerator(n=3, verbose=True, random_seed=42, log_to_file=False)
    
    try:
        generator.train(training_text)
        for _ in range(5):
            print("Seed:", generator.generate(12))
        
        # Save and reload the model
        generator.save_model("markov_model.pkl")
        generator.reset_model()
        generator.load_model("markov_model.pkl")
        print("Reloaded model generated:", generator.generate(12))
    except Exception as e:
        logging.exception("Error: " + str(e))
