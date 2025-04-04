import random
import logging
from collections import defaultdict
from typing import List

# Configure logging
logging.basicConfig(
    filename="seed_generator.log",
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s"
)

class MarkovSeedGenerator:
    def __init__(self, n: int = 3, verbose: bool = False):
        """
        Initialize the MarkovSeedGenerator.

        :param n: Character-level n-gram size (must be a positive integer)
        :param verbose: Enable verbose mode for detailed output (default: False)
        """
        if not isinstance(n, int) or n <= 0:
            raise ValueError("n must be a positive integer.")
        self.n = n  # Character-level n-gram size
        self.model = defaultdict(list)
        self.verbose = verbose  # Enable verbose mode

    def train(self, text: str) -> None:
        """
        Train the Markov model on character sequences.

        :param text: Training text for the Markov model
        :raises ValueError: If the input text is empty or too short for the given n-gram size
        """
        if not text:
            logging.error("Training failed: Input text is empty.")
            raise ValueError("Training text cannot be empty.")
        if len(text) <= self.n:
            logging.error("Training failed: Input text is too short for the given n-gram size.")
            raise ValueError("Input text must be longer than the n-gram size.")

        text = text.lower().replace("\n", " ")  # Normalize text
        for i in range(len(text) - self.n):
            key = text[i:i + self.n]  # n-gram key
            next_char = text[i + self.n]  # Next character
            self.model[key].append(next_char)  # Store transition

        if self.verbose:
            print(f"Training completed! Model size: {len(self.model)} keys.")
        logging.info(f"Model trained with {len(self.model)} keys.")

    def generate(self, length: int = 12) -> str:
        """
        Generate a seed using the trained Markov model.

        :param length: Desired length of the generated seed (default: 12)
        :return: Generated seed as a string
        :raises RuntimeError: If the model is empty (not trained)
        """
        if not self.model:
            logging.error("Seed generation failed: Model is empty.")
            raise RuntimeError("Train the model before generating seeds.")

        seed = random.choice(list(self.model.keys()))  # Random starting key
        output = list(seed)

        if self.verbose:
            print(f"Starting seed: {seed}")

        for _ in range(length - self.n):
            if seed in self.model:
                next_char = random.choice(self.model[seed])  # Choose next character
                output.append(next_char)
                seed = seed[1:] + next_char  # Shift n-gram window
                if self.verbose:
                    print(f"Next char: {next_char}, New seed: {seed}")
            else:
                logging.warning(f"Unexpected stop: No transitions available for key '{seed}'.")
                break

        generated_seed = ''.join(output)
        logging.info(f"Generated seed: {generated_seed}")
        return generated_seed

    def reset_model(self) -> None:
        """
        Reset the Markov model.
        """
        self.model.clear()
        logging.info("Model reset.")

# Example Usage
if __name__ == "__main__":
    training_text = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()"

    markov_seeds = MarkovSeedGenerator(n=3, verbose=True)
    
    try:
        markov_seeds.train(training_text)
        for _ in range(5):
            print("Generated Seed:", markov_seeds.generate(12))
    except Exception as e:
        logging.exception("Error in seed generation: " + str(e))
