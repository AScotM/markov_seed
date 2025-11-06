const crypto = require('crypto');

class MarkovSeedGenerator {
    constructor(n, verbose = false) {
        if (n <= 0) throw new Error("n must be positive");
        this.n = n;
        this.model = new Map();
        this.verbose = verbose;
        this.randomBuffer = crypto.randomBytes(256);
        this.bufferIndex = 0;
    }

    train(text) {
        if (text.length <= this.n) {
            throw new Error("Text too short for training");
        }

        this.text = text;
        this.keys = [];

        for (let i = 0; i <= text.length - this.n - 1; i++) {
            const key = text.substring(i, i + this.n);
            const nextChar = text.substring(i + this.n, i + this.n + 1);

            if (!this.model.has(key)) {
                this.model.set(key, []);
                this.keys.push(key);
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

        let seed = this.keys[this.secureRandInt(this.keys.length)];
        let output = seed;

        while (output.length < length) {
            const lastN = output.substring(output.length - this.n);
            const possibleNext = this.model.get(lastN);

            if (!possibleNext || possibleNext.length === 0) {
                const newStart = this.keys[this.secureRandInt(this.keys.length)];
                output += newStart;
                continue;
            }

            const nextChar = possibleNext[this.secureRandInt(possibleNext.length)];
            output += nextChar;
        }

        return output.substring(0, length);
    }

    secureRandInt(max) {
        if (max <= 0) return 0;
        
        if (this.bufferIndex >= this.randomBuffer.length - 4) {
            this.randomBuffer = crypto.randomBytes(256);
            this.bufferIndex = 0;
        }
        
        const randomInt = this.randomBuffer.readUInt32BE(this.bufferIndex);
        this.bufferIndex += 4;
        return randomInt % max;
    }
}

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
