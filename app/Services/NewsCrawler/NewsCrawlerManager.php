<?php

namespace App\Services\NewsCrawler;

use App\Jobs\ProcessCrawledArticleJob;
use App\Models\CrawlerSource;
use App\Models\Post;
use App\Services\NewsCrawler\Contracts\NewsSourceInterface;
use App\Services\NewsCrawler\Sources\DatabaseRssSource;
use App\Services\NewsCrawler\Sources\VnExpressSource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NewsCrawlerManager
{
    /**
     * @var NewsSourceInterface[]
     */
    protected array $sources = [];
    protected DatabaseRssSource $dbSource;

    public function __construct()
    {
        $this->dbSource = new DatabaseRssSource();
        // Đăng ký nguồn fallback
        $this->registerSource(new VnExpressSource());
    }

    /**
     * Đăng ký một nguồn báo mới
     */
    public function registerSource(NewsSourceInterface $source): self
    {
        $this->sources[$source->getSourceKey()] = $source;
        return $this;
    }

    /**
     * Lấy danh sách các nguồn báo khả dụng
     */
    public function getAvailableSources(): array
    {
        if (Schema::hasTable('crawler_sources')) {
            $dbSources = CrawlerSource::where('is_active', true)
                ->pluck('source_name')
                ->unique()
                ->values()
                ->toArray();
            if (!empty($dbSources)) {
                return $dbSources;
            }
        }

        return array_keys($this->sources);
    }

    /**
     * Thực hiện cào tin và dispatch vào Queue hoặc xử lý trực tiếp
     *
     * @param string|null $sourceKey Chỉ định nguồn (hoặc null để cào tất cả)
     * @param int $limit Số lượng bài mỗi nguồn
     * @param bool $sync Chạy trực tiếp (true) hay đưa vào Queue Job (false)
     * @return array Kết quả thống kê
     */
    public function crawlAndProcess(?string $sourceKey = null, int $limit = 10, bool $sync = false): array
    {
        $articles = [];

        // 1. Ưu tiên kiểm tra nguồn động từ bảng crawler_sources trong CSDL
        $hasDbSources = false;
        if (Schema::hasTable('crawler_sources')) {
            $query = CrawlerSource::where('is_active', true);
            if ($sourceKey) {
                $query->where('source_name', 'LIKE', "%{$sourceKey}%");
            }
            $activeSources = $query->get();

            if ($activeSources->isNotEmpty()) {
                $hasDbSources = true;
                foreach ($activeSources as $sourceModel) {
                    $sourceArticles = $this->dbSource->fetchFromSingleSource($sourceModel, $limit);
                    $articles = array_merge($articles, $sourceArticles);

                    // Cập nhật timestamp lần cào gần nhất
                    $sourceModel->update(['last_crawled_at' => now()]);
                }
            }
        }

        // 2. Nếu CSDL chưa có nguồn nào, sử dụng các nguồn tĩnh fallback
        if (!$hasDbSources) {
            $selectedSources = [];
            if ($sourceKey && isset($this->sources[$sourceKey])) {
                $selectedSources[] = $this->sources[$sourceKey];
            } else {
                $selectedSources = array_values($this->sources);
            }

            foreach ($selectedSources as $source) {
                $sourceArticles = $source->fetchArticles($limit);
                $articles = array_merge($articles, $sourceArticles);
            }
        }

        $stats = [
            'total_fetched' => count($articles),
            'skipped_duplicates' => 0,
            'processed' => 0,
        ];

        foreach ($articles as $articleData) {
            // Kiểm tra xem bài báo đã tồn tại trong DB chưa
            if (Post::where('source_url', $articleData->sourceUrl)->exists()) {
                $stats['skipped_duplicates']++;
                continue;
            }

            if ($sync) {
                (new ProcessCrawledArticleJob($articleData))->handle();
            } else {
                dispatch(new ProcessCrawledArticleJob($articleData));
            }

            $stats['processed']++;
        }

        Log::info("NewsCrawler finished: Fetched {$stats['total_fetched']}, Processed {$stats['processed']}, Skipped {$stats['skipped_duplicates']}");

        return $stats;
    }
}
