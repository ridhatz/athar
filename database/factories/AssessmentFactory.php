<?php

namespace Database\Factories;

use App\Enums\AssessmentRecommendation;
use App\Models\Assessment;
use App\Models\User;
use App\Services\AssessmentScoring;
use App\Support\AssessmentQuestions;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        // A plausible, internally-consistent set of answers (every "most" is D or I; everything
        // else loses) rather than random picks, so a factory-made row scores through the real
        // service instead of carrying numbers nobody would actually produce.
        $answers = array_fill(0, AssessmentQuestions::COUNT, ['most' => 0, 'least' => 2]);
        $result = app(AssessmentScoring::class)->score($answers);

        return [
            'user_id' => User::factory(),
            'answers' => $answers,
            ...$result,
            'submitted_at' => now(),
        ];
    }

    public function recommending(AssessmentRecommendation $recommendation): static
    {
        return match ($recommendation) {
            // All D "most", C "least": sales_index (D+I) high and positive, marketing_index (I+C) low.
            AssessmentRecommendation::Sales => $this->state(fn () => $this->fromPicks(most: 0, least: 3)),
            // All C "most", D "least": the opposite — marketing_index clears sales_index.
            AssessmentRecommendation::Marketing => $this->state(fn () => $this->fromPicks(most: 3, least: 0)),
            // Half D/least-I, half C/least-I: D and C land equal and I cancels out, so
            // sales_index (D+I) and marketing_index (I+C) come out exactly equal — within any
            // margin, never just "happens to be close".
            AssessmentRecommendation::Hybrid => $this->state(fn () => $this->evenlySplit()),
        };
    }

    /** @return array<string, mixed> */
    private function fromPicks(int $most, int $least): array
    {
        $answers = array_fill(0, AssessmentQuestions::COUNT, ['most' => $most, 'least' => $least]);

        return ['answers' => $answers, ...app(AssessmentScoring::class)->score($answers)];
    }

    /** @return array<string, mixed> */
    private function evenlySplit(): array
    {
        $half = intdiv(AssessmentQuestions::COUNT, 2);
        $answers = [
            ...array_fill(0, $half, ['most' => 0, 'least' => 1]),
            ...array_fill(0, AssessmentQuestions::COUNT - $half, ['most' => 3, 'least' => 1]),
        ];

        return ['answers' => $answers, ...app(AssessmentScoring::class)->score($answers)];
    }
}
