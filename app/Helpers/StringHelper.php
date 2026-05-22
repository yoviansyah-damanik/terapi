<?php

namespace App\Helpers;

class StringHelper
{
    /**
     * Memeriksa apakah teks menggunakan bahasa Indonesia berdasarkan kumpulan leksikon klinis dan umum.
     */
    public static function isIndonesianClinicalText(string $text): bool
    {
        $text = strtolower($text);
        // Daftar kata hubung dan kata kunci klinis khas bahasa Indonesia
        $idKeywords = [
            ' dan ', ' atau ', ' yang ', ' dengan ', ' tanpa ', ' pada ', ' dari ', ' oleh ',
            ' tidak ', ' belum ', ' akut', ' kronis', ' gagal ', ' penyakit ', ' radang ', 
            ' infeksi ', ' cedera ', ' gangguan ', ' nyeri ', ' patah ', ' virus ', ' bakteri ',
            ' ringan ', ' sedang ', ' berat ', ' lainnya ', ' spesifik', ' tidak spesifik'
        ];

        // Memeriksa awal/akhir string dengan padding spasi buatan
        $paddedText = " $text ";
        foreach ($idKeywords as $keyword) {
            if (str_contains($paddedText, $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Membersihkan dan menormalisasi teks klinis dari stop-words medis 
     * dan mengganti singkatan medis umum dengan padanan aslinya.
     */
    public static function normalizeClinicalText(string $text): string
    {
        // Lowercase & Hilangkan tanda baca yang tidak perlu
        $text = strtolower(trim($text));
        $text = preg_replace('/[^\w\s\.]/', '', $text); // Sisakan huruf, angka, spasi, dan titik
        
        // Dictionary Sinonim Medis / Singkatan & Term Correction
        // e.g., zero pipe -> no pipe
        $synonyms = [
            ' ca ' => ' cancer ',
            ' zero pipe ' => ' no pipe ',
            ' disease ' => ' disorder ',
            ' nos ' => ' not otherwise specified ',
            ' tb ' => ' tuberculosis ',
            ' htn ' => ' hypertension ',
            ' dm ' => ' diabetes ',
        ];
        
        // Dictionary Stopwords Medis (Inggris)
        $stopwords = [
            ' due to ', ' and ', ' with ', ' without ', ' of ', ' the ', ' in ', ' on ', 
            ' unspecified ', ' other ', ' part ', ' varying ', ' type '
        ];

        $paddedText = " $text ";
        
        // Apply Synonyms Replacement
        foreach ($synonyms as $key => $val) {
            $paddedText = str_replace($key, $val, $paddedText);
        }
        
        // Apply Stopword Removal
        foreach ($stopwords as $stopword) {
            $paddedText = str_replace($stopword, ' ', $paddedText);
        }
        
        // Hapus double whitespace
        $text = trim(preg_replace('/\s+/', ' ', $paddedText));
        
        return $text;
    }

    /**
     * Algoritma Jaro-Winkler Distance. Mengembalikan nilai 0.0 - 1.0 (Akurasinya tinggi untuk typo & spelling errors)
     */
    public static function jaroWinkler(string $s1, string $s2): float
    {
        if ($s1 === $s2) return 1.0;
        $len1 = strlen($s1);
        $len2 = strlen($s2);
        if ($len1 == 0 || $len2 == 0) return 0.0;
        
        $matchDistance = (int) floor(max($len1, $len2) / 2) - 1;
        $s1Matches = array_fill(0, $len1, false);
        $s2Matches = array_fill(0, $len2, false);
        $matches = 0;
        $transpositions = 0;
        
        // Find matches
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchDistance);
            $end = min($i + $matchDistance + 1, $len2);
            for ($j = $start; $j < $end; $j++) {
                if (!$s2Matches[$j] && $s1[$i] === $s2[$j]) {
                    $s1Matches[$i] = true;
                    $s2Matches[$j] = true;
                    $matches++;
                    break;
                }
            }
        }
        if ($matches === 0) return 0.0;
        
        // Find transpositions
        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if ($s1Matches[$i]) {
                while (!$s2Matches[$k]) {
                    $k++;
                }
                if ($s1[$i] !== $s2[$k]) {
                    $transpositions++;
                }
                $k++;
            }
        }
        $transpositions /= 2;
        
        // Jaro Distance
        $jaro = (($matches / $len1) + ($matches / $len2) + (($matches - $transpositions) / $matches)) / 3.0;
        
        // Winkler Modification
        $prefix = 0;
        $maxPrefix = 4;
        for ($i = 0; $i < min($len1, $len2, $maxPrefix); $i++) {
            if ($s1[$i] === $s2[$i]) {
                $prefix++;
            } else {
                break;
            }
        }
        $scalingFactor = 0.1;
        $jaroWinkler = $jaro + ($prefix * $scalingFactor * (1.0 - $jaro));
        return $jaroWinkler;
    }

    /**
     * Sorensen-Dice Coefficient untuk token overlap. Mengembalikan nilai 0.0 - 1.0
     */
    public static function sorensenDice(string $s1, string $s2): float
    {
        $words1 = array_filter(str_word_count($s1, 1));
        $words2 = array_filter(str_word_count($s2, 1));
        
        if (empty($words1) && empty($words2)) return 1.0;
        if (empty($words1) || empty($words2)) return 0.0;

        $intersection = array_intersect($words1, $words2);
        return (2 * count($intersection)) / (count($words1) + count($words2));
    }

    /**
     * Cosine Similarity berbasis 2-Character Bi-Grams. Sangat bagus mendeteksi kemiringan sudut kata yang acak.
     */
    public static function cosineSimilarityBigram(string $s1, string $s2): float
    {
        $getBigrams = function ($str) {
            $bigrams = [];
            $len = strlen($str);
            if ($len < 2) return [$str => 1];
            for ($i = 0; $i < $len - 1; $i++) {
                $bg = substr($str, $i, 2);
                if (!isset($bigrams[$bg])) $bigrams[$bg] = 0;
                $bigrams[$bg]++;
            }
            return $bigrams;
        };

        $bg1 = $getBigrams($s1);
        $bg2 = $getBigrams($s2);

        $dotProduct = 0;
        foreach ($bg1 as $key => $val) {
            if (isset($bg2[$key])) {
                $dotProduct += $val * $bg2[$key];
            }
        }
        
        $mag1 = 0;
        foreach ($bg1 as $val) $mag1 += $val * $val;
        
        $mag2 = 0;
        foreach ($bg2 as $val) $mag2 += $val * $val;

        if ($mag1 == 0 || $mag2 == 0) return 0.0;
        return $dotProduct / (sqrt($mag1) * sqrt($mag2));
    }

    /**
     * Algoritma Monge-Elkan khusus menyelesaikan phrasa terbalik (e.g. "Acute Appendicitis" vs "Appendicitis, Acute")
     * dengan mencari skor kemiripan tertingi di antara setiap pasangan kata/token yang dikawinsilangkan.
     */
    public static function mongeElkan(string $s1, string $s2): float
    {
        $A = array_values(array_filter(str_word_count($s1, 1)));
        $B = array_values(array_filter(str_word_count($s2, 1)));
        
        if (empty($A) || empty($B)) return 0.0;

        // Base matching function inside Monge-Elkan will use Jaro-Winkler
        $sum = 0.0;
        foreach ($A as $aToken) {
            $maxScore = 0.0;
            foreach ($B as $bToken) {
                $score = self::jaroWinkler($aToken, $bToken);
                if ($score > $maxScore) {
                    $maxScore = $score;
                }
            }
            $sum += $maxScore;
        }

        return $sum / count($A);
    }

    /**
     * Normalized Levenshtein jarak string (0-1)
     */
    public static function normalizedLevenshtein(string $s1, string $s2): float
    {
        $maxLen = max(strlen($s1), strlen($s2));
        if ($maxLen == 0) return 1.0;
        $levenshteinDist = levenshtein($s1, $s2);
        return 1.0 - ($levenshteinDist / $maxLen);
    }

    /**
     * Hitung skor kemiripan antara dua kalimat menggunakan Ensemble Model 
     * (menggabungkan 4 algoritma NLP dengan pembobotan tingkat lanjut).
     * 
     * @return int Persentase kemiripan (0 hingga 100).
     */
    public static function calculateSimilarityScore(string $source, string $target): int
    {
        // Tahap 1: Text Pre-processing
        $source = self::normalizeClinicalText($source);
        $target = self::normalizeClinicalText($target);
        
        // Exact Match Short-circuit
        if ($source === $target) {
            return 100;
        }
        
        if (empty($source) || empty($target)) return 0;

        // Tahap 2: Kalkulasi paralel 4 Engine Evaluator
        $scoreJaroWinkler = self::jaroWinkler($source, $target);           // Toleransi typo dan awalan kata
        $scoreDice        = self::sorensenDice($source, $target);          // Irisan token base
        $scoreCosine      = self::cosineSimilarityBigram($source, $target);// Deteksi kata ganda (bigram vector)
        $scoreMongeElkan  = self::mongeElkan($source, $target);            // Susunan kata terbalik (Clinical Phrasing)

        // Tahap 3: Pembobotan / Ensemble (Total Weight = 1.0)
        // JaroWinkler (20%), SorensenDice (20%), Cosine Bigram (35%), MongeElkan (25%)
        $finalEnsembleScore = (
            ($scoreJaroWinkler * 0.20) +
            ($scoreDice * 0.20) +
            ($scoreCosine * 0.35) +
            ($scoreMongeElkan * 0.25)
        );

        return (int) round($finalEnsembleScore * 100);
    }
}

