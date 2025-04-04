import random
import logging
from collections import defaultdict

# Configure logging
logging.basicConfig(filename="seed_generator.log", level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")

class MarkovSeedGenerator:
    def __init__(self, n=3, verbose=False):
        """
        Initialize the MarkovSeedGenerator.
        
        :param n: Character-level n-gram size
        :param verbose: Enable verbose mode
        """
        self.n = n
        self.model = defaultdict(list)
        self.verbose = verbose

    def train(self, text):
        """
        Train the Markov model on character sequences.
        
        :param text: Input text for training the model
        """
        if not text:
            logging.error("Training failed: Input text is empty.")
            raise ValueError("Training text cannot be empty.")

        text = text.lower().replace("\n", " ")  # Normalize text
        for i in range(len(text) - self.n):
            key = text[i:i + self.n]  # n-gram key
            next_char = text[i + self.n]  # Next character
            self.model[key].append(next_char)  # Store transition

        if self.verbose:
            print(f"Training completed! Model size: {len(self.model)} keys.")
        logging.info(f"Model trained with {len(self.model)} keys.")

    def generate(self, length=12):
        """
        Generate a seed using the trained Markov model.
        
        :param length: Desired length of the generated seed
        :return: Generated seed string
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
                logging.warning(f"Unexpected stop: No transitions available for seed '{seed}'.")
                break

        generated_seed = ''.join(output)
        logging.info(f"Generated seed: {generated_seed}")
        return generated_seed

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
