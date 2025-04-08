package main

import (
	"encoding/gob"
	"fmt"
	"log"
	"math/rand"
	"os"
	"strings"
	"time"
)

// MarkovSeedGenerator struct
type MarkovSeedGenerator struct {
	N      int
	Model  map[string][]string
	Verbose bool
}

// NewMarkovSeedGenerator initializes a new generator
func NewMarkovSeedGenerator(n int, verbose bool) *MarkovSeedGenerator {
	if n <= 0 {
		log.Fatal("n must be a positive integer.")
	}
	return &MarkovSeedGenerator{
		N:      n,
		Model:  make(map[string][]string),
		Verbose: verbose,
	}
}

// Train builds the Markov model from input text
func (m *MarkovSeedGenerator) Train(text string) {
	text = strings.ToLower(strings.ReplaceAll(text, "\n", " "))
	text = strings.TrimSpace(text)

	if len(text) <= m.N {
		log.Fatal("Training failed: Input text is too short for the given n-gram size.")
	}

	for i := 0; i < len(text)-m.N; i++ {
		key := text[i : i+m.N]
		nextChar := string(text[i+m.N])
		m.Model[key] = append(m.Model[key], nextChar)
	}

	if len(m.Model) < 3 {
		log.Println("Warning: Model may be too small for good randomness.")
	}

	if m.Verbose {
		fmt.Printf("Training completed! Model size: %d keys.\n", len(m.Model))
	}
}

// Generate creates a random sequence based on the trained model
func (m *MarkovSeedGenerator) Generate(length int) string {
	if len(m.Model) == 0 {
		log.Fatal("Model is empty. Train the model before generating seeds.")
	}

	rand.Seed(time.Now().UnixNano())

	// Get random starting key
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
		if nextChars, exists := m.Model[seed]; exists {
			nextChar := nextChars[rand.Intn(len(nextChars))]
			output = append(output, rune(nextChar[0]))
			seed = seed[1:] + nextChar
		} else {
			log.Println("Warning: No transitions available for key, restarting...")
			seed = keys[rand.Intn(len(keys))] // Restart with another key
		}
	}

	generatedSeed := string(output)
	log.Println("Generated seed:", generatedSeed)
	return generatedSeed
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
	trainingText := "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()"

	markov := NewMarkovSeedGenerator(3, true)
	markov.Train(trainingText)

	err := markov.SaveModel("markov_model.gob")
	if err != nil {
		log.Fatal("Error saving model:", err)
	}

	loadedMarkov, err := LoadModel("markov_model.gob")
	if err != nil {
		log.Fatal("Error loading model:", err)
	}

	for i := 0; i < 5; i++ {
		fmt.Println("Generated Seed:", loadedMarkov.Generate(12))
	}
}
