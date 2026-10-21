<?php

namespace App\Services;

use App\Enums\AssessmentRecommendation;
use App\Support\AssessmentQuestions;
use Illuminate\Validation\ValidationException;

/**
 * Turns 24 forced-choice answers into a D/I/S/C result and a Sales / Marketing / Hybrid call.
 *
 * Column position is the dimension — 0=Dominance, 1=Influence, 2=Steadiness, 3=Conscientiousness
 * — on every item, regardless of which word the PDF used that time (see AssessmentQuestions). So
 * scoring never looks at the words at all, only at which of the four positions was picked.
 *
 * For each item the "most" pick earns its dimension +1 and the "least" pick earns its dimension
 * -1; net = how many times a dimension was picked most, minus how many times it was picked
 * least. The four nets always sum to zero (every item hands out exactly one +1 and one -1), which
 * is the classic DISC graph shape, not a bug.
 *
 * Sales-fit = D + I: assertiveness paired with persuasive energy is the "hunter" profile — direct
 * asks, comfortable with rejection, drives toward a close.
 * Marketing-fit = I + C: the same persuasive energy paired with care and system is the profile
 * that plans a campaign and gets the details of it right, rather than closing one deal at a time.
 * Influence sits in both on purpose: persuasion matters either way, and it is what keeps a highly
 * dominant-but-careless profile and a highly careful-but-withdrawn profile from both reading as
 * "Hybrid" by default.
 */
final class AssessmentScoring
{
    public const DOMINANCE = 0;

    public const INFLUENCE = 1;

    public const STEADINESS = 2;

    public const CONSCIENTIOUSNESS = 3;

    private const DIMENSIONS = ['D', 'I', 'S', 'C'];

    /**
     * @param  array<int, array{most: int, least: int}>  $answers  exactly 24 entries, 0-indexed
     *                                                             like the question bank
     * @return array{score_d: int, score_i: int, score_s: int, score_c: int, dominant_style: string, sales_index: int, marketing_index: int, recommendation: AssessmentRecommendation}
     *
     * @throws ValidationException
     */
    public function score(array $answers): array
    {
        $this->assertValid($answers);

        $net = [0, 0, 0, 0];
        foreach ($answers as $answer) {
            $net[$answer['most']]++;
            $net[$answer['least']]--;
        }

        [$d, $i, $s, $c] = $net;
        $salesIndex = $d + $i;
        $marketingIndex = $i + $c;

        return [
            'score_d' => $d,
            'score_i' => $i,
            'score_s' => $s,
            'score_c' => $c,
            'dominant_style' => self::DIMENSIONS[array_search(max($net), $net, true)],
            'sales_index' => $salesIndex,
            'marketing_index' => $marketingIndex,
            'recommendation' => $this->recommend($salesIndex, $marketingIndex),
        ];
    }

    private function recommend(int $salesIndex, int $marketingIndex): AssessmentRecommendation
    {
        $margin = (int) config('athar.assessment.role_fit_margin', 4);
        $diff = $salesIndex - $marketingIndex;

        return match (true) {
            $diff >= $margin => AssessmentRecommendation::Sales,
            $diff <= -$margin => AssessmentRecommendation::Marketing,
            default => AssessmentRecommendation::Hybrid,
        };
    }

    /**
     * @param  array<int, array{most: int, least: int}>  $answers
     *
     * @throws ValidationException
     */
    private function assertValid(array $answers): void
    {
        if (count($answers) !== AssessmentQuestions::COUNT) {
            throw ValidationException::withMessages([
                'answers' => __('errors.assessment_incomplete'),
            ]);
        }

        foreach ($answers as $index => $answer) {
            $most = $answer['most'] ?? null;
            $least = $answer['least'] ?? null;

            if (! in_array($most, [0, 1, 2, 3], true) || ! in_array($least, [0, 1, 2, 3], true)) {
                throw ValidationException::withMessages([
                    "answers.{$index}" => __('errors.assessment_invalid_answer'),
                ]);
            }

            if ($most === $least) {
                throw ValidationException::withMessages([
                    "answers.{$index}" => __('errors.assessment_same_choice'),
                ]);
            }
        }
    }
}
