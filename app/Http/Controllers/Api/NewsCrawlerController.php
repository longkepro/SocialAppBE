<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NewsCrawler\NewsCrawlerManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsCrawlerController extends Controller
{
    /**
     * Kích hoạt cào tin tức chủ động bằng Secret Token hoặc Admin Auth
     */
    public function trigger(Request $request, NewsCrawlerManager $manager): JsonResponse
    {
        // 1. Kiểm tra quyền truy cập: Hỗ trợ Secret Token hoặc đã đăng nhập Admin
        $providedToken = $request->input('token') ?? $request->header('X-Crawler-Token');
        $validToken = config('services.crawler.secret');

        $isTokenValid = $providedToken && hash_equals((string) $validToken, (string) $providedToken);
        $isAdminUser = $request->user() && in_array($request->user()->role, ['admin', 'superadmin']);

        if (!$isTokenValid && !$isAdminUser) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token bảo mật không chính xác hoặc bạn không có quyền truy cập.',
            ], 403);
        }

        // 2. Lấy các tham số tùy chọn (giới hạn tối đa 20 bài mỗi lần để đảm bảo thời gian phản hồi)
        $limit = min(max((int) $request->input('limit', 10), 1), 20);
        $source = $request->input('source'); // null hoặc 'vnexpress'
        $sync = $request->boolean('sync', true); // Mặc định chạy đồng bộ để phản hồi kết quả ngay lập tức

        // 3. Thực thi cào bài và lưu trữ vào database
        $stats = $manager->crawlAndProcess(
            sourceKey: $source,
            limit: $limit,
            sync: $sync
        );

        // 4. Trả về kết quả JSON chi tiết
        return response()->json([
            'status'  => 'success',
            'message' => 'Đã thực hiện cào tin tức thành công!',
            'data'    => [
                'stats'       => $stats,
                'source'      => $source ?: 'all',
                'limit'       => $limit,
                'executed_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Tra cứu danh sách các nguồn báo qua Secret Token
     */
    public function sources(Request $request): JsonResponse
    {
        $providedToken = $request->input('token') ?? $request->header('X-Crawler-Token');
        $validToken = config('services.crawler.secret');

        $isTokenValid = $providedToken && hash_equals((string) $validToken, (string) $providedToken);
        $isAdminUser = $request->user() && in_array($request->user()->role, ['admin', 'superadmin']);

        if (!$isTokenValid && !$isAdminUser) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token bảo mật không chính xác hoặc bạn không có quyền truy cập.',
            ], 403);
        }

        $sources = \App\Models\CrawlerSource::with('category:id,name,slug')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $sources,
        ]);
    }
}
