<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\User;
use App\Models\Category;
use App\Services\NewsCrawler\DTO\CrawledArticleData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Mews\Purifier\Facades\Purifier;

class ProcessCrawledArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param CrawledArticleData $data DTO bài viết đã được bóc tách
     */
    public function __construct(
        public CrawledArticleData $data
    ) {}

    /**
     * Xử lý lưu bài viết vào CSDL
     */
    public function handle(): void
    {
        // 1. Kiểm tra lại lần nữa trong Queue để tránh race condition
        if (Post::where('source_url', $this->data->sourceUrl)->exists()) {
            return;
        }

        try {
            // 2. Lấy hoặc tạo tài khoản Bot hệ thống cho nguồn báo này
            $botEmail = 'newsbot_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $this->data->sourceName)) . '@system.local';
            $botUser = User::firstOrCreate(
                ['email' => $botEmail],
                [
                    'name' => 'Bot ' . $this->data->sourceName,
                    'password' => bcrypt('system-bot-news-token-' . rand(1000, 9999)),
                    'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($this->data->sourceName) . '&background=0D8ABC&color=fff',
                ]
            );

            // Đảm bảo bot được đánh dấu đã xác thực email
            if ($botUser->email_verified_at === null) {
                $botUser->email_verified_at = now();
                $botUser->save();
            }

            // 3. Xác định category_id hợp lệ
            $categoryId = $this->data->categoryId;
            if ($categoryId && !Category::where('id', $categoryId)->exists()) {
                $categoryId = null;
            }
            if (!$categoryId) {
                // Lấy category đầu tiên làm mặc định nếu không khớp
                $categoryId = Category::first()?->id;
            }

            // 4. Bóc tách toàn văn bài báo từ link gốc (Full Content Scraping)
            $rawContent = $this->data->contentHtml; // Mặc định nội dung tóm tắt RSS dự phòng
            try {
                $fullArticleHtml = (new \App\Services\NewsCrawler\Extractors\ArticleContentExtractor())
                    ->extract($this->data->sourceUrl, $this->data->sourceName);

                if ($fullArticleHtml) {
                    $footerHtml = "<p><em>Xem chi tiết bài viết gốc tại <a href=\"{$this->data->sourceUrl}\" target=\"_blank\" rel=\"noopener noreferrer\">{$this->data->sourceName}</a>.</em></p>";
                    $rawContent = $fullArticleHtml . "\n" . $footerHtml;
                }
            } catch (\Throwable $extractorEx) {
                Log::warning("Could not extract full content for [{$this->data->sourceUrl}], using RSS summary: " . $extractorEx->getMessage());
            }

            // 5. Làm sạch nội dung HTML chống XSS bằng Purifier
            $safeContent = Purifier::clean($rawContent);

            // 5. Lưu vào bảng posts
            // Chú ý: KHÔNG kích hoạt bắn notification hàng loạt ra ngoài để tránh nghẽn hàng đợi
            $post = Post::create([
                'user_id'       => $botUser->id,
                'title'         => $this->data->title,
                'content_html'  => $safeContent,
                'category_id'   => $categoryId,
                'thumbnail_url' => $this->data->thumbnailUrl,
                'status'        => 'published',
                'source_url'    => $this->data->sourceUrl,
                'source_name'   => $this->data->sourceName,
            ]);

            Log::info("Successfully saved crawled post #{$post->id} from {$this->data->sourceName}: '{$post->title}'");
        } catch (\Throwable $e) {
            Log::error("Failed to process crawled article [{$this->data->sourceUrl}]: " . $e->getMessage());
        }
    }
}
