const crypto = require('crypto');

class MarkovSeedGenerator {
    constructor(n, verbose = false) {
        if (n <= 0) throw new Error("n must be positive");
        this.n = n;
        this.model = new Map();
        this.verbose = verbose;
    }

    train(text) {
        if (text.length <= this.n) {
            throw new Error("Text too short for training");
        }

        this.text = text;

        for (let i = 0; i <= text.length - this.n - 1; i++) {
            const key = text.substr(i, this.n);
            const nextChar = text.substr(i + this.n, 1);

            if (!this.model.has(key)) {
                this.model.set(key, []);
            }
            this.model.get(key).push(nextChar);
        }

        if (this.verbose) {
            console.log(`Trained model with ${this.model.size} n-grams`);
        }
    }

    generate(length) {
        if (this.model.size === 0) throw new Error("Untrained model");
        if (length < this.n) throw new Error("Length too short");

        const keys = Array.from(this.model.keys());
        let seed = keys[this.secureRandInt(keys.length)];
        let output = seed;

        while (output.length < length) {
            const lastN = output.substr(-this.n);
            const possibleNext = this.model.get(lastN);

            if (!possibleNext || possibleNext.length === 0) {
                // Fallback to random character from text
                const fallback = this.text.charAt(this.secureRandInt(this.text.length));
                output += fallback;
                continue;
            }

            const nextChar = possibleNext[this.secureRandInt(possibleNext.length)];
            output += nextChar;
        }

        return output.substr(0, length);
    }

    secureRandInt(max) {
        if (max <= 0) return 0;
        const randomBytes = crypto.randomBytes(4);
        const randomInt = randomBytes.readUInt32BE(0);
        return randomInt % max;
    }
}

// Example usage
const trainingText = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>/? " +
    "The quick brown fox jumps over the lazy dog. Pack my box with five dozen liquor jugs.";

try {
    const markov = new MarkovSeedGenerator(3, true);
    markov.train(trainingText);

    for (let i = 0; i < 5; i++) {
        const seed = markov.generate(16);
        console.log(`Generated ${i+1}: "${seed}"`);
    }
} catch (err) {
    console.error("Error:", err.message);
}
