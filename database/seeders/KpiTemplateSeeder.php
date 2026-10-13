<?php

namespace Database\Seeders;

use App\Enums\KpiDirection;
use App\Enums\KpiFrequency;
use App\Models\KpiTemplate;
use App\Models\KpiTemplateItem;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Starting KPI templates, one per Tazkia team, so a month can be generated on day one.
 *
 * These are a plausible starting point, not Tazkia's agreed targets — the numbers are meant to be
 * edited. Weights add up to 100 per template, which is the convention the screens assume.
 *
 * Safe to run repeatedly: a template that already exists keeps the targets it has, so re-seeding
 * never quietly resets numbers somebody has since corrected.
 */
class KpiTemplateSeeder extends Seeder
{
    /**
     * team name => [name, target, direction, unit, weight]
     *
     * @var array<string, array<int, array{0: string, 1: float, 2: KpiDirection, 3: string|null, 4: float}>>
     */
    private const TEMPLATES = [
        'Admisi' => [
            ['Jumlah pendaftar baru', 500, KpiDirection::HigherBetter, null, 35],
            ['Persentase pendaftar yang menyelesaikan daftar ulang', 80, KpiDirection::HigherBetter, '%', 30],
            ['Biaya akuisisi per pendaftar', 250000, KpiDirection::LowerBetter, null, 20],
            ['Waktu respons pertama ke calon mahasiswa (jam)', 4, KpiDirection::LowerBetter, null, 15],
        ],
        'Edu Consultant' => [
            ['Jumlah sesi konsultasi', 120, KpiDirection::HigherBetter, null, 30],
            ['Persentase konsultasi berlanjut ke pendaftaran', 35, KpiDirection::HigherBetter, '%', 35],
            ['Kepuasan calon mahasiswa (skala 100)', 85, KpiDirection::HigherBetter, null, 20],
            ['Konsultasi tanpa tindak lanjut', 10, KpiDirection::LowerBetter, '%', 15],
        ],
        'Media' => [
            ['Jumlah konten terbit', 40, KpiDirection::HigherBetter, null, 30],
            ['Pertumbuhan jangkauan media sosial', 15, KpiDirection::HigherBetter, '%', 30],
            ['Biaya iklan terhadap jangkauan', 35, KpiDirection::LowerBetter, '%', 20],
            ['Konten terlambat dari jadwal', 5, KpiDirection::LowerBetter, '%', 20],
        ],
        'Kerjasama & CDC' => [
            ['Jumlah mitra kerja sama baru', 6, KpiDirection::HigherBetter, null, 30],
            ['Persentase lulusan terserap kerja', 70, KpiDirection::HigherBetter, '%', 35],
            ['Jumlah kegiatan pengembangan karier', 4, KpiDirection::HigherBetter, null, 20],
            ['Mitra tidak aktif dalam setahun', 20, KpiDirection::LowerBetter, '%', 15],
        ],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $teamName => $lines) {
            $team = Team::query()->where('name', $teamName)->first();

            if ($team === null) {
                continue;
            }

            $template = KpiTemplate::query()->firstOrCreate(
                ['team_id' => $team->id],
                ['name' => 'KPI '.$team->name, 'is_active' => true],
            );

            // Only fill a template that is still empty; an edited one is left exactly as it is.
            if ($template->items()->exists()) {
                continue;
            }

            foreach ($lines as $i => [$name, $target, $direction, $unit, $weight]) {
                KpiTemplateItem::query()->create([
                    'kpi_template_id' => $template->id,
                    'name' => $name,
                    'target_value' => $target,
                    'direction' => $direction,
                    'unit' => $unit,
                    'weight' => $weight,
                    'frequency' => KpiFrequency::Monthly,
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
