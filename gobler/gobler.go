package main

import (
	"encoding/gob"
	"fmt"
	"log"
	"math/rand"
	"os"
	"time"
	"unicode/utf8"
)

// MarkovSeedGenerator struct
type MarkovSeedGenerator struct {
	N       int
	Model   map[string][]string
	Verbose bool
}

// NewMarkovSeedGenerator initializes a new generator
func NewMarkovSeedGenerator(n int, verbose bool) (*MarkovSeedGenerator, error) {
	if n <= 0 {
		return nil, fmt.Errorf("n must be a positive integer")
	}
	return &MarkovSeedGenerator{
		N:       n,
		Model:   make(map[string][]string),
		Verbose: verbose,
	}, nil
}

// Train builds the Markov model from input text
func (m *MarkovSeedGenerator) Train(text string) error {
	runes := []rune(text)
	textLen := len(runes)

	if textLen <= m.N {
		return fmt.Errorf("training failed: input text is too short for the given n-gram size")
	}

	for i := 0; i < textLen-m.N; i++ {
		key := string(runes[i : i+m.N])
		nextChar := string(runes[i+m.N])
		m.Model[key] = append(m.Model[key], nextChar)
	}

	if len(m.Model) < 3 {
		log.Println("Warning: Model may be too small for good randomness.")
	}

	if m.Verbose {
		fmt.Printf("Training completed! Model size: %d keys.\n", len(m.Model))
	}
	return nil
}

// Generate creates a random sequence based on the trained model
func (m *MarkovSeedGenerator) Generate(length int) (string, error) {
	if len(m.Model) == 0 {
		return "", fmt.Errorf("model is empty. Train the model before generating seeds")
	}
	if length < m.N {
		return "", fmt.Errorf("desired length must be at least n (%d)", m.N)
	}

	keys := make([]string, 0, len(m.Model))
	for k := range m.Model {
		keys = append(keys, k)
	}
	seed := keys[rand.Intn(len(keys))]
	output := []rune(seed)

	if m.Verbose {
		fmt.Println("Starting seed:", seed)
	}

	for i := 0; i < length-m.N; i++ {
		nextChars, exists := m.Model[seed]
		if exists {
			nextChar := nextChars[rand.Intn(len(nextChars))]
			nextRune, _ := utf8.DecodeRuneInString(nextChar)
			output = append(output, nextRune)
			seedRunes := []rune(seed)
			seedRunes = append(seedRunes[1:], nextRune)
			seed = string(seedRunes)
		} else {
			log.Println("Warning: No transitions available for key, restarting...")
			seed = keys[rand.Intn(len(keys))] // Restart with another key
			// Reset output to seed to preserve length
			output = []rune(seed)
		}
	}

	generatedSeed := string(output)
	log.Println("Generated seed:", generatedSeed)
	return generatedSeed, nil
}

// SaveModel saves the Markov model to a file
func (m *MarkovSeedGenerator) SaveModel(filename string) error {
	file, err := os.Create(filename)
	if err != nil {
		return err
	}
	defer file.Close()

	encoder := gob.NewEncoder(file)
	err = encoder.Encode(m)
	if err != nil {
		return err
	}

	log.Println("Model saved to", filename)
	return nil
}

// LoadModel loads the Markov model from a file
func LoadModel(filename string) (*MarkovSeedGenerator, error) {
	file, err := os.Open(filename)
	if err != nil {
		return nil, err
	}
	defer file.Close()

	decoder := gob.NewDecoder(file)
	var model MarkovSeedGenerator
	err = decoder.Decode(&model)
	if err != nil {
		return nil, err
	}

	log.Println("Model loaded from", filename)
	return &model, nil
}

// Main function for example usage
func main() {
	rand.Seed(time.Now().UnixNano())

	trainingText := "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()"

	markov, err := NewMarkovSeedGenerator(3, true)
	if err != nil {
		log.Fatal(err)
	}
	if err := markov.Train(trainingText); err != nil {
		log.Fatal(err)
	}

	if err := markov.SaveModel("markov_model.gob"); err != nil {
		log.Fatal("Error saving model:", err)
	}

	loadedMarkov, err := LoadModel("markov_model.gob")
	if err != nil {
		log.Fatal("Error loading model:", err)
	}

	for i := 0; i < 5; i++ {
		seed, err := loadedMarkov.Generate(12)
		if err != nil {
			log.Fatal("Error generating seed:", err)
		}
		fmt.Println("Generated Seed:", seed)
	}
}
