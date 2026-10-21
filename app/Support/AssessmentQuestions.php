<?php

namespace App\Support;

/**
 * The 24-item Communication Behavior Assessment, replicated question for question from the
 * organization's own PDF instrument (a forced-choice DISC form built in JotForm).
 *
 * Each item offers four trait words in a FIXED column order — position 0 is always the
 * Dominance-leaning word, 1 Influence, 2 Steadiness, 3 Conscientiousness — confirmed by reading
 * every one of the 24 items directly rather than assumed: the source PDF repeats its eight base
 * word-sets an uneven number of times (the "Dominan" set appears only in item 1; "Tegas" and
 * "Berani" appear four times each; the rest three times each), so this is transcribed item by
 * item, not generated from a pattern. The column order itself is exactly as consistent as that
 * repetition is uneven, which is what makes scoring by COLUMN POSITION rather than by word safe
 * (see AssessmentScoring) — the word is just what the PDF happened to call that dimension that
 * time.
 *
 * Content only: never translated. These are the client's own Indonesian instrument words, not UI
 * chrome, so — unlike everything else in this app — they are not run through the translation
 * system, the same way a KPI name an admin typed in stays exactly as they typed it.
 */
final class AssessmentQuestions
{
    public const COUNT = 24;

    /**
     * Each row: [Dominance word, Influence word, Steadiness word, Conscientiousness word].
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function all(): array
    {
        return [
            ['Dominan', 'Berpengaruh', 'Stabil', 'Cermat'],
            ['Tegas', 'Optimis', 'Sabar', 'Teliti'],
            ['Berani', 'Ekspresif', 'Setia', 'Akurat'],
            ['Penuh Inisiatif', 'Meyakinkan', 'Pendengar Baik', 'Sistematis'],
            ['Ambisius', 'Antusias', 'Sabar', 'Teliti'],
            ['Berorientasi Tugas', 'Persuasif', 'Tenang', 'Perfeksionis'],
            ['Mandiri', 'Ramah', 'Pendiam', 'Analitis'],
            ['Percaya Diri', 'Suka Bergaul', 'Setia', 'Berhati-hati'],
            ['Tegas', 'Optimis', 'Sabar', 'Teliti'],
            ['Berani', 'Ekspresif', 'Setia', 'Akurat'],
            ['Penuh Inisiatif', 'Meyakinkan', 'Pendengar Baik', 'Sistematis'],
            ['Ambisius', 'Antusias', 'Sabar', 'Teliti'],
            ['Berorientasi Tugas', 'Persuasif', 'Tenang', 'Perfeksionis'],
            ['Mandiri', 'Ramah', 'Pendiam', 'Analitis'],
            ['Percaya Diri', 'Suka Bergaul', 'Setia', 'Berhati-hati'],
            ['Tegas', 'Optimis', 'Sabar', 'Teliti'],
            ['Berani', 'Ekspresif', 'Setia', 'Akurat'],
            ['Penuh Inisiatif', 'Meyakinkan', 'Pendengar Baik', 'Sistematis'],
            ['Ambisius', 'Antusias', 'Sabar', 'Teliti'],
            ['Berorientasi Tugas', 'Persuasif', 'Tenang', 'Perfeksionis'],
            ['Mandiri', 'Ramah', 'Pendiam', 'Analitis'],
            ['Percaya Diri', 'Suka Bergaul', 'Setia', 'Berhati-hati'],
            ['Tegas', 'Optimis', 'Sabar', 'Teliti'],
            ['Berani', 'Ekspresif', 'Setia', 'Akurat'],
        ];
    }
}
