<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrawlerSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCrawlerSourceController extends Controller
{
    /**
     * Danh sách tất cả các nguồn báo đang có
     */
    public function index(): JsonResponse
    {
        $sources = CrawlerSource::with('category:id,name,slug')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $sources,
        ]);
    }

    /**
     * Thêm một nguồn báo mới
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_name' => 'required|string|max:100',
            'feed_url'    => 'required|url|unique:crawler_sources,feed_url|max:255',
            'description' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'is_active'   => 'nullable|boolean',
        ], [
            'source_name.required' => 'Vui lòng nhập tên nguồn báo.',
            'feed_url.required'    => 'Vui lòng nhập đường dẫn RSS feed.',
            'feed_url.url'         => 'Đường dẫn RSS feed không đúng định dạng URL.',
            'feed_url.unique'      => 'Đường dẫn RSS feed này đã tồn tại trong hệ thống.',
            'category_id.exists'   => 'Chuyên mục được chọn không tồn tại.',
        ]);

        $source = CrawlerSource::create([
            'source_name' => $validated['source_name'],
            'feed_url'    => $validated['feed_url'],
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        $source->load('category:id,name,slug');

        return response()->json([
            'status'  => 'success',
            'message' => 'Thêm nguồn báo thành công!',
            'data'    => $source,
        ], 201);
    }

    /**
     * Xem chi tiết một nguồn báo
     */
    public function show(CrawlerSource $crawlerSource): JsonResponse
    {
        $crawlerSource->load('category:id,name,slug');

        return response()->json([
            'status' => 'success',
            'data'   => $crawlerSource,
        ]);
    }

    /**
     * Cập nhật thông tin nguồn báo
     */
    public function update(Request $request, CrawlerSource $crawlerSource): JsonResponse
    {
        $validated = $request->validate([
            'source_name' => 'sometimes|required|string|max:100',
            'feed_url'    => 'sometimes|required|url|max:255|unique:crawler_sources,feed_url,' . $crawlerSource->id,
            'description' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'is_active'   => 'sometimes|boolean',
        ], [
            'source_name.required' => 'Tên nguồn báo không được để trống.',
            'feed_url.required'    => 'Đường dẫn RSS không được để trống.',
            'feed_url.url'         => 'Đường dẫn RSS không đúng định dạng URL.',
            'feed_url.unique'      => 'Đường dẫn RSS này đã tồn tại.',
            'category_id.exists'   => 'Chuyên mục được chọn không tồn tại.',
        ]);

        $crawlerSource->update($validated);
        $crawlerSource->load('category:id,name,slug');

        return response()->json([
            'status'  => 'success',
            'message' => 'Cập nhật nguồn báo thành công!',
            'data'    => $crawlerSource,
        ]);
    }

    /**
     * Bật hoặc Tắt nhanh trạng thái cào của một nguồn báo
     */
    public function toggle(CrawlerSource $crawlerSource): JsonResponse
    {
        $crawlerSource->is_active = !$crawlerSource->is_active;
        $crawlerSource->save();

        return response()->json([
            'status'    => 'success',
            'message'   => $crawlerSource->is_active ? 'Đã kích hoạt nguồn báo.' : 'Đã tạm dừng nguồn báo.',
            'is_active' => $crawlerSource->is_active,
            'data'      => $crawlerSource,
        ]);
    }

    /**
     * Xóa một nguồn báo khỏi hệ thống
     */
    public function destroy(CrawlerSource $crawlerSource): JsonResponse
    {
        $sourceName = $crawlerSource->source_name;
        $crawlerSource->delete();

        return response()->json([
            'status'  => 'success',
            'message' => "Đã xóa nguồn báo '{$sourceName}' thành công!",
        ]);
    }
}
