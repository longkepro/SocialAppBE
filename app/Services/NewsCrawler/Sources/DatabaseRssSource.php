<?php

namespace App\Services\NewsCrawler\Sources;

use App\Models\CrawlerSource;
use App\Services\NewsCrawler\Contracts\NewsSourceInterface;
use App\Services\NewsCrawler\DTO\CrawledArticleData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DatabaseRssSource implements NewsSourceInterface
{
    public function getSourceName(): string
    {
        return 'Database RSS Sources';
    }

    public function getSourceKey(): string
    {
        return 'database_rss';
    }

    /**
     * @param int $limit Số lượng bài tối đa cho mỗi nguồn RSS
     * @return CrawledArticleData[]
     */
    public function fetchArticles(int $limit = 10): array
    {
        if (!Schema::hasTable('crawler_sources')) {
            return [];
        }

        $sources = CrawlerSource::where('is_active', true)->get();
        if ($sources->isEmpty()) {
            return [];
        }

        $articles = [];

        foreach ($sources as $sourceModel) {
            $feedArticles = $this->fetchFromSingleSource($sourceModel, $limit);
            $articles = array_merge($articles, $feedArticles);

            // Cập nhật mốc thời gian cào thành công
            $sourceModel->update(['last_crawled_at' => now()]);
        }

        return $articles;
    }

    /**
     * Thu thập tin từ một CrawlerSource cụ thể trong Database
     *
     * @return CrawledArticleData[]
     */
    public function fetchFromSingleSource(CrawlerSource $source, int $limit = 10): array
    {
        $articles = [];

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) SocialAppNewsBot/1.0',
                ])
                ->get($source->feed_url);

            if (!$response->successful()) {
                Log::warning("RSS request failed for URL [{$source->feed_url}]: Status {$response->status()}");
                return [];
            }

            $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$xml || !isset($xml->channel->item)) {
                return [];
            }

            foreach ($xml->channel->item as $item) {
                if (count($articles) >= $limit) {
                    break;
                }

                $title = trim((string) $item->title);
                $link = trim((string) $item->link);
                $descriptionRaw = (string) $item->description;

                if (empty($title) || empty($link)) {
                    continue;
                }

                // 1. Trích xuất thumbnail: ưu tiên thẻ img trong description, sau đó kiểm tra enclosure
                $thumbnailUrl = null;
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $descriptionRaw, $matches)) {
                    $thumbnailUrl = $matches[1];
                } elseif (isset($item->enclosure) && isset($item->enclosure['url'])) {
                    $thumbnailUrl = (string) $item->enclosure['url'];
                }

                // 2. Làm sạch đoạn văn bản tóm tắt
                $cleanSummary = trim(strip_tags($descriptionRaw));

                // 3. Xử lý thời gian xuất bản
                $publishedInfoHtml = '';
                if (isset($item->pubDate) && !empty((string) $item->pubDate)) {
                    try {
                        $formattedDate = Carbon::parse((string) $item->pubDate)
                            ->timezone('Asia/Ho_Chi_Minh')
                            ->format('H:i \n\g\à\y d/m/Y');
                        $publishedInfoHtml = "<p><em>Xuất bản lúc {$formattedDate} trên {$source->source_name}</em></p>";
                    } catch (\Throwable) {
                        // Bỏ qua nếu định dạng ngày khác chuẩn
                    }
                }

                // 4. Tạo nội dung HTML hoàn chỉnh
                $contentHtml = "<p><strong>{$cleanSummary}</strong></p>";
                if ($thumbnailUrl) {
                    $contentHtml .= "<p><img src=\"{$thumbnailUrl}\" alt=\"{$title}\" style=\"max-width:100%; height:auto; border-radius:8px;\" /></p>";
                }
                if ($publishedInfoHtml) {
                    $contentHtml .= $publishedInfoHtml;
                }
                $contentHtml .= "<p><em>Xem chi tiết bài viết gốc tại <a href=\"{$link}\" target=\"_blank\" rel=\"noopener noreferrer\">{$source->source_name}</a>.</em></p>";

                $articles[] = new CrawledArticleData(
                    title: $title,
                    contentHtml: $contentHtml,
                    sourceUrl: $link,
                    sourceName: $source->source_name,
                    thumbnailUrl: $thumbnailUrl,
                    categoryId: $source->category_id
                );
            }
        } catch (\Throwable $e) {
            Log::error("Error parsing RSS [{$source->feed_url}]: " . $e->getMessage());
        }

        return $articles;
    }
}
