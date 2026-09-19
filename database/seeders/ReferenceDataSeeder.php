<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\RequestCategory;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Data the application cannot run without. Safe in every environment and idempotent:
 * running it again updates by natural key (code / slug / name) instead of duplicating.
 *
 * Production: php artisan db:seed --class=ReferenceDataSeeder
 *
 * Departments and teams are starting points; administrators rename or extend them later.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->roles();
        $this->categories();
        $this->teams();
        $this->departments();
    }

    private function roles(): void
    {
        $names = [
            RoleCode::Requester->value => 'Requester',
            RoleCode::Staff->value => 'PIC / Staff',
            RoleCode::TeamLead->value => 'Team Lead',
            RoleCode::Admin->value => 'Admin',
            RoleCode::Management->value => 'Management',
        ];

        foreach ($names as $code => $name) {
            Role::query()->updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }

    private function categories(): void
    {
        $categories = [
            ['design', 'Desain', 'Design',
                'Sebutkan ukuran/format (mis. poster A3, feed Instagram), teks yang harus dimuat, dan lampirkan referensi atau logo.',
                'State the size/format (e.g. A3 poster, Instagram feed), the text to include, and attach references or logos.'],
            ['content-article', 'Konten / Artikel', 'Content / Article',
                'Jelaskan topik, target pembaca, panjang tulisan, dan narasumber atau bahan yang tersedia.',
                'Describe the topic, target audience, length, and any sources or material available.'],
            ['documentation', 'Dokumentasi', 'Documentation',
                'Sebutkan acara/kegiatan, tanggal, lokasi, dan jenis dokumentasi yang dibutuhkan (foto, video, notulen).',
                'Give the event or activity, date, location, and the kind of documentation needed (photo, video, minutes).'],
            ['web-development', 'Web / Pengembangan', 'Web / Development',
                'Sebutkan halaman atau sistem yang dimaksud, perubahan yang diinginkan, dan alamat (URL) terkait.',
                'Name the page or system concerned, the change wanted, and the related URL.'],
            ['it-support', 'Dukungan IT', 'IT Support',
                'Sebutkan perangkat/aplikasi yang bermasalah, lokasi, pesan kesalahan, dan sejak kapan terjadi.',
                'State the device/application affected, location, any error message, and since when it happens.'],
            ['video-multimedia', 'Video / Multimedia', 'Video / Multimedia',
                'Sebutkan tujuan video, durasi, platform tujuan, dan bahan (footage, naskah, musik) yang sudah ada.',
                'State the video\'s purpose, duration, target platform, and existing material (footage, script, music).'],
            ['data-analytics', 'Data / Analitik', 'Data / Analytics',
                'Jelaskan pertanyaan yang ingin dijawab, sumber data, dan format hasil yang diharapkan.',
                'Describe the question to answer, the data source, and the output format expected.'],
            ['consultation', 'Konsultasi', 'Consultation',
                'Jelaskan topik yang ingin dikonsultasikan dan hasil yang diharapkan dari konsultasi.',
                'Describe the topic to discuss and the outcome you expect from the consultation.'],
            ['other', 'Lainnya', 'Other',
                'Jelaskan kebutuhan Anda sejelas mungkin agar dapat diarahkan ke tim yang tepat.',
                'Describe what you need as clearly as you can so it can be routed to the right team.'],
        ];

        foreach ($categories as $i => [$slug, $nameId, $nameEn, $guidanceId, $guidanceEn]) {
            RequestCategory::query()->updateOrCreate(['slug' => $slug], [
                'name_id' => $nameId,
                'name_en' => $nameEn,
                'guidance_id' => $guidanceId,
                'guidance_en' => $guidanceEn,
                'sort_order' => ($i + 1) * 10,
                'is_active' => true,
            ]);
        }
    }

    private function teams(): void
    {
        $teams = [
            'IT' => 'Infrastruktur, dukungan, dan sistem',
            'Creative' => 'Desain dan multimedia',
            'Content' => 'Penulisan dan konten',
            'Digital' => 'Kanal dan media digital',
            'Documentation' => 'Dokumentasi kegiatan',
        ];

        foreach ($teams as $name => $description) {
            Team::query()->updateOrCreate(['name' => $name], ['description' => $description, 'is_active' => true]);
        }
    }

    private function departments(): void
    {
        foreach (['Humas', 'Akademik', 'Keuangan', 'SDM', 'Kemahasiswaan', 'Teknologi Informasi'] as $name) {
            Department::query()->updateOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
