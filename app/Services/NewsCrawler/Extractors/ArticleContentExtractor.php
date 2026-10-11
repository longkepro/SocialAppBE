<?php

namespace App\Services\NewsCrawler\Extractors;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArticleContentExtractor
{
    /**
     * Bóc tách toàn văn nội dung và hình ảnh từ URL bài báo gốc
     *
     * @param string $url Đường dẫn bài báo gốc
     * @param string $sourceName Tên nguồn báo (VnExpress, Tuổi Trẻ...)
     * @return string|null Chuỗi HTML toàn văn hoặc null nếu thất bại
     */
    public function extract(string $url, string $sourceName = ''): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
                ])
                ->get($url);

            if (!$response->successful() || empty($response->body())) {
                return null;
            }

            $html = $response->body();
            $doc = new DOMDocument();
            // Nạp mã HTML với xử lý bảng mã tiếng Việt
            @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($doc);

            // 1. Danh sách các vùng chứa nội dung bài viết phổ biến của báo chí Việt Nam
            $containerQueries = [
                // VnExpress
                '//*[contains(@class, "fck_detail")]',
                '//article[contains(@class, "fck_detail")]',
                // Tuổi Trẻ
                '//*[@id="main-detail-body"]',
                '//*[contains(@class, "detail-content")]',
                // Dân Trí
                '//*[contains(@class, "singular-content")]',
                '//*[contains(@class, "dt-news__content")]',
                // VietnamNet & Thanh Niên
                '//*[contains(@class, "maincontent")]',
                '//*[contains(@class, "content-detail")]',
                '//*[contains(@class, "detail-cmain")]',
                // Thẻ article chuẩn chung
                '//article',
            ];

            $targetNode = null;
            foreach ($containerQueries as $query) {
                $nodes = $xpath->query($query);
                if ($nodes->length > 0) {
                    $targetNode = $nodes->item(0);
                    break;
                }
            }

            if (!$targetNode) {
                return null;
            }

            // 2. Dọn dẹp các thành phần rác: quảng cáo, bài liên quan, script, mạng xã hội
            $junkQueries = [
                './/script',
                './/style',
                './/iframe',
                './/*[contains(@class, "box-tinlienquan")]',
                './/*[contains(@class, "box-relation")]',
                './/*[contains(@class, "related")]',
                './/*[contains(@class, "banner")]',
                './/*[contains(@class, "ads")]',
                './/*[contains(@class, "advertisement")]',
                './/*[contains(@class, "author-info")]',
                './/*[contains(@class, "social-share")]',
                './/*[contains(@class, "tag")]',
                './/*[contains(@class, "tracking")]',
            ];

            foreach ($junkQueries as $jq) {
                $junkNodes = $xpath->query($jq, $targetNode);
                foreach ($junkNodes as $jn) {
                    $jn->parentNode?->removeChild($jn);
                }
            }

            // 3. Chuẩn hóa hình ảnh: Chuyển data-src/data-original thành src thật và style responsive
            $imgNodes = $xpath->query('.//img', $targetNode);
            foreach ($imgNodes as $img) {
                $realSrc = $img->getAttribute('data-src') 
                    ?: $img->getAttribute('data-original') 
                    ?: $img->getAttribute('src');

                if ($realSrc && !str_starts_with($realSrc, 'data:image')) {
                    $img->setAttribute('src', $realSrc);
                }
                $img->setAttribute('style', 'max-width:100%; height:auto; border-radius:8px; display:block; margin: 12px auto;');
            }

            // 4. Trích xuất các khối văn bản, tiêu đề phụ và ảnh
            $contentElements = $xpath->query('.//p | .//h2 | .//h3 | .//figure | .//blockquote | .//ul | .//ol', $targetNode);
            if ($contentElements->length === 0) {
                return null;
            }

            $extractedHtml = '';
            foreach ($contentElements as $el) {
                $nodeHtml = $doc->saveHTML($el);
                if (!empty(trim(strip_tags($nodeHtml))) || str_contains($nodeHtml, '<img')) {
                    $extractedHtml .= $nodeHtml . "\n";
                }
            }

            $extractedHtml = trim($extractedHtml);

            // Đảm bảo nội dung thu được có độ dài có ý nghĩa (tối thiểu 150 ký tự)
            return strlen($extractedHtml) > 150 ? $extractedHtml : null;
        } catch (\Throwable $e) {
            Log::warning("Error scraping full article [{$url}]: " . $e->getMessage());
            return null;
        }
    }
}
