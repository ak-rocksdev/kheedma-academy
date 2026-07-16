<?php

namespace Database\Seeders;

use App\Models\ContentSection;
use Illuminate\Database\Seeder;

/**
 * Moves the community intro that used to be hard-coded in
 * funnel/community.blade.php into managed content. Run once at deploy;
 * skips when community sections already exist so re-runs never duplicate
 * or overwrite team edits.
 */
class ContentSectionSeeder extends Seeder
{
    public function run(): void
    {
        if (ContentSection::forCommunity()->exists()) {
            return;
        }

        $sections = [
            [
                'heading' => 'Komunitas belajar, bukan sekadar kelas jualan.',
                'body' => '<p>Kami mendampingimu membangun habit dan rutinitas harian sebagai affiliator yang solid, konsisten, dan berkelanjutan.</p>'
                    .'<ul><li><strong>Mentor pribadi, gratis.</strong> Dedicated personal manager yang membimbing, membantu mengurai kendala affiliate, dan menjaga konsistensi konten kreatifmu.</li>'
                    .'<li><strong>Akses komunitas, gratis.</strong> Grup koordinasi tanpa biaya supaya kamu selalu up to date dengan program strategis yang akan dijalankan ke depannya.</li></ul>',
            ],
            [
                'heading' => 'Belajar daring dan luring.',
                'body' => '<ul><li>Sesi Pagi Daring (Perempuan): 09.30 WIB</li>'
                    .'<li>Sesi Siang Luring (Laki-laki): 13.30 WIB</li>'
                    .'<li>Lokasi: Kantor Kheedma Indonesia, Pasar Kliwon, Surakarta, atau via Zoom/Google Meet</li></ul>'
                    .'<p><strong>Silabus program:</strong></p>'
                    .'<ol><li>Fondasi Dasar dan Teknis Awal Affiliate TikTok</li>'
                    .'<li>Akselerasi Penjualan dan Strategi Scale Up</li>'
                    .'<li>Optimalisasi Konten dan Iklan TikTok Affiliate</li>'
                    .'<li>Membangun Personal Branding Digital</li></ol>',
            ],
            [
                'heading' => 'Komitmen dan etika belajar.',
                'body' => '<p>Kami mencari rekan yang siap berkomitmen untuk:</p>'
                    .'<ol><li>Alokasi waktu minimal 1 jam per hari untuk mempraktikkan materi dan menyelesaikan task.</li>'
                    .'<li>Menjaga vibrasi positif, saling support antar anggota, dan membangun circle belajar yang sehat.</li>'
                    .'<li>Saling menghargai dan menjaga etika, kepada sesama rekan belajar maupun mentor.</li></ol>'
                    .'<p>Kami tidak menjanjikan keberhasilan instan atau target angka tertentu. Fokus utama komunitas ini adalah membentuk mindset, kebiasaan produktif, dan framework strategi agar kamu dapat mengelola profesi affiliator secara efektif dan berjangka panjang.</p>',
            ],
        ];

        foreach ($sections as $order => $section) {
            ContentSection::create([
                'page' => 'community',
                'heading' => $section['heading'],
                'body' => $section['body'],
                'sort_order' => $order,
            ]);
        }
    }
}
