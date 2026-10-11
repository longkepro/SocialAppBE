<?php

namespace Database\Seeders;

use App\Models\CrawlerSource;
use Illuminate\Database\Seeder;

class CrawlerSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = [
            [
                'source_name' => 'VnExpress',
                'feed_url'    => 'https://vnexpress.net/rss/so-hoa.rss',
                'description' => 'Số hóa - Công nghệ',
                'category_id' => 3, // Công nghệ
                'is_active'   => true,
            ],
            [
                'source_name' => 'VnExpress',
                'feed_url'    => 'https://vnexpress.net/rss/khoa-hoc.rss',
                'description' => 'Khoa học & Đời sống',
                'category_id' => 4, // Khoa học
                'is_active'   => true,
            ],
            [
                'source_name' => 'VnExpress',
                'feed_url'    => 'https://vnexpress.net/rss/giai-tri.rss',
                'description' => 'Giải trí, Âm nhạc & Phim',
                'category_id' => 5, // Giải trí
                'is_active'   => true,
            ],
            [
                'source_name' => 'VnExpress',
                'feed_url'    => 'https://vnexpress.net/rss/du-lich.rss',
                'description' => 'Du lịch & Trải nghiệm',
                'category_id' => 1, // Du lịch - Khám phá
                'is_active'   => true,
            ],
            [
                'source_name' => 'VnExpress',
                'feed_url'    => 'https://vnexpress.net/rss/tin-moi-nhat.rss',
                'description' => 'Văn hóa & Tin mới nhất',
                'category_id' => 2, // Văn hóa - Xã hội
                'is_active'   => true,
            ],
        ];

        foreach ($sources as $source) {
            CrawlerSource::firstOrCreate(
                ['feed_url' => $source['feed_url']],
                $source
            );
        }
    }
}
