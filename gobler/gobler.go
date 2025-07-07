package main

import (
	"crypto/rand"
	"encoding/binary"
	"fmt"
	"log"
	"math/big"
)

type MarkovSeedGenerator struct {
	N       int
	Model   map[string][]rune
	Text    string
	Verbose bool
}

func NewMarkovSeedGenerator(n int, verbose bool) (*MarkovSeedGenerator, error) {
	if n <= 0 {
		return nil, fmt.Errorf("n must be positive")
	}
	return &MarkovSeedGenerator{
		N:       n,
		Model:   make(map[string][]rune),
		Verbose: verbose,
	}, nil
}

func secureRandIntn(n int) int {
	if n <= 0 {
		return 0
	}
	num, err := rand.Int(rand.Reader, big.NewInt(int64(n)))
	if err != nil {
		var fallback uint32
		binary.Read(rand.Reader, binary.BigEndian, &fallback)
		return int(fallback) % n
	}
	return int(num.Int64())
}

func (m *MarkovSeedGenerator) Train(text string) error {
	runes := []rune(text)
	if len(runes) <= m.N {
		return fmt.Errorf("text too short")
	}

	m.Text = text
	limit := len(runes) - m.N

	for i := 0; i < limit; i++ {
		end := i + m.N
		if end >= len(runes) {
			break
		}
		key := string(runes[i:end])
		nextChar := runes[end]
		
		if m.Model[key] == nil {
			m.Model[key] = make([]rune, 0, 4)
		}
		m.Model[key] = append(m.Model[key], nextChar)
	}

	if m.Verbose {
		log.Printf("Trained model with %d n-grams", len(m.Model))
	}
	return nil
}

func (m *MarkovSeedGenerator) Generate(length int) (string, error) {
	if len(m.Model) == 0 {
		return "", fmt.Errorf("untrained model")
	}
	if length < m.N {
		return "", fmt.Errorf("length too short")
	}

	keys := make([]string, 0, len(m.Model))
	for k := range m.Model {
		keys = append(keys, k)
	}

	seed := keys[secureRandIntn(len(keys))]
	output := []rune(seed)

	for len(output) < length {
		nextChars := m.Model[seed]
		if len(nextChars) == 0 {
			// Fallback to random character from text
			runes := []rune(m.Text)
			if len(runes) == 0 {
				break
			}
			nextChar := runes[secureRandIntn(len(runes))]
			output = append(output, nextChar)
			seed = seed[1:] + string(nextChar)
			continue
		}

		nextChar := nextChars[secureRandIntn(len(nextChars))]
		output = append(output, nextChar)
		seed = seed[1:] + string(nextChar)
	}

	if len(output) > length {
		output = output[:length]
	}
	return string(output), nil
}

func main() {
	// Robust training text with sufficient length
	const trainingText = `ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>/?` + 
		`The quick brown fox jumps over the lazy dog. Pack my box with five dozen liquor jugs.`

	markov, err := NewMarkovSeedGenerator(3, true)
	if err != nil {
		log.Fatal(err)
	}

	if err := markov.Train(trainingText); err != nil {
		log.Fatal(err)
	}

	for i := 0; i < 5; i++ {
		seed, err := markov.Generate(16)
		if err != nil {
			log.Println("Error:", err)
			continue
		}
		fmt.Printf("Generated %d: %q\n", i+1, seed)
	}
}
