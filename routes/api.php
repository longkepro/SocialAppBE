<?php

use App\Http\Controllers\Api\Admin\AdminAdvertisementController;
use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminCrawlerSourceController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\AdminSettingController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\AdvertisementController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\ImageUploadController;
use App\Http\Controllers\Api\Moderator\AdminAuthController;
use App\Http\Controllers\Api\Moderator\ModerationController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostVoteController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\Superadmin\UserRoleController;
use App\Http\Controllers\Api\NewsCrawlerController;
use App\Http\Controllers\Api\UserProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

require __DIR__.'/auth.php';

// API Đăng nhập riêng cho Admin (Frontend sẽ gọi cái này ở trang /admin/login)
Route::post('/admin/login', [AdminAuthController::class, 'store']);

// --- Route Public (Ai cũng xem được) ---
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
//  Lấy (Get) Bình luận (Comment)cho một (a) Bài viết (Post)
Route::get('/posts/{post}/comments', [CommentController::class, 'getForPost']);

// API Lấy các phản hồi của 1 bình luận
Route::get('/comments/{comment}/replies', [CommentController::class, 'getReplies']);

// Lấy danh sách chuyên mục
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

// Route kích hoạt cào tin tức chủ động qua Secret Token
Route::match(['GET', 'POST'], '/crawler/trigger', [NewsCrawlerController::class, 'trigger']);
Route::get('/crawler/sources', [NewsCrawlerController::class, 'sources']);

// Routes xem Hồ sơ (Profile) công khai
// (Dùng 'user' làm tên tham số cho Model Binding)
Route::get('/profiles/{user}', [ProfileController::class, 'show']);
Route::get('/profiles/{user}/followers', [ProfileController::class, 'getFollowers']);
Route::get('/profiles/{user}/following', [ProfileController::class, 'getFollowing']);

// Search (Tìm kiếm) User (Người dùng)
Route::get('/users/search', [ProfileController::class, 'search'])->name('users.search');

// API (API) (Giao diện lập trình ứng dụng) Lấy (Get) Quảng cáo (Ad) (Public (Công khai))
Route::get('/advertisements', [AdvertisementController::class, 'index']);

// Lấy logo
Route::get('/settings/logo', [SettingController::class, 'getLogo']);
// Lấy background
Route::get('/settings/background', [SettingController::class, 'getBackground']);
// Lấy footer
Route::get('/settings/footer', [SettingController::class, 'getFooter']);

Route::middleware('auth:sanctum')->group(function () {
    // API của Post
    Route::post('/posts', [PostController::class, 'store']);
    Route::put('/posts/{post}', [PostController::class, 'update']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);

    //API CỦA COMMENT
    Route::post('/comments', [CommentController::class, 'store']);
    Route::patch('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    // *** API UPLOAD ẢNH  ***
    // Dùng cho trình soạn thảo văn bản
    Route::post('/image-upload', [ImageUploadController::class, 'upload'])->name('image.upload');

    // --- Module Vote  ---
    Route::post('/posts/{post}/upvote', [PostVoteController::class, 'upvote']);
    Route::post('/posts/{post}/downvote', [PostVoteController::class, 'downvote']);

    // ---  CẬP NHẬT AVATAR, COVER-PHOTO ---
    Route::post('/user/avatar', [UserProfileController::class, 'updateAvatar']);
    Route::post('/user/cover-photo', [UserProfileController::class, 'updateCoverPhoto']);

    // Cập nhật (Update) chi tiết (details) (tên, v.v.)
    Route::patch('/profile/details', [UserProfileController::class, 'updateProfile']);
    //  đổi (change) mật khẩu (password)
    Route::patch('/profile/password', [UserProfileController::class, 'updatePassword']);

    // xóa tài khoản
    Route::delete('/profile/delete', [UserProfileController::class, 'destroy']);

    // --- MODULE FOLLOW ---
    Route::post('/users/{user}/follow', [FollowController::class, 'toggleFollow']);

    // Module 8: Báo cáo (Gửi báo cáo)

    Route::post('/posts/{post}/report', [ReportController::class, 'storePostReport'])
         ->name('posts.report');

    Route::post('/comments/{comment}/report', [ReportController::class, 'storeCommentReport'])
         ->name('comments.report');

    Route::post('/users/{user}/report', [ReportController::class, 'storeUserReport'])
         ->name('users.report');
});

Route::middleware(['auth:sanctum', 'role:moderator'])
    ->prefix('moderator') // Tiền tố /api/moderator
    ->name('moderator.')
    ->group(function () {
        // 2. Thay thế Controller
        Route::get('/reports/posts', [ModerationController::class, 'getPostReports']);
        Route::get('/reports/comments', [ModerationController::class, 'getCommentReports']);
        Route::get('/reports/users', [ModerationController::class, 'getUserReports']);

        // Xử lý/Xóa một báo cáo (sau khi đã hành động)
        Route::delete('/reports/posts/{reportPost}', [ModerationController::class, 'resolvePostReport']);
        Route::delete('/reports/comments/{reportComment}', [ModerationController::class, 'resolveCommentReport']);
        Route::delete('/reports/users/{reportUser}', [ModerationController::class, 'resolveUserReport']);

        // Láy danh sách các post và comment bị gỡ
        Route::get('/content/removed-posts', [ModerationController::class, 'getRemovedPosts']);
        Route::get('/content/removed-comments', [ModerationController::class, 'getRemovedComments']);

        // 3. (MỚI) Khôi phục nội dung
        Route::post('/posts/{post}/restore', [ModerationController::class, 'restorePost']);
        Route::post('/comments/{comment}/restore', [ModerationController::class, 'restoreComment']);

        // Route Thống kê (Dashboard Stats)
        Route::get('/dashboard/stats', [DashboardController::class, 'index']);  

});

Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin') // Tiền tố /api/admin
    ->name('admin.')
    ->group(function () {
           // Quản lý chuyên mục (Thêm/Sửa/Xóa)
        // Dùng apiResource để tạo nhanh các route:
        // GET /admin/categories -> index
        // POST /admin/categories -> store
        // GET /admin/categories/{category} -> show
        // PUT/PATCH /admin/categories/{category} -> update
        // DELETE /admin/categories/{category} -> destroy
        Route::apiResource('categories', AdminCategoryController::class);

        // Quản lý User (Ban/Unban)
        Route::post('/users/{user}/ban', [UserManagementController::class, 'ban']);
        Route::post('/users/{user}/unban', [UserManagementController::class, 'unban']);

        // Xem Lịch sử Kiểm duyệt (Moderation History) của 1 User
        Route::get('/users/{user}/moderation-history', [UserManagementController::class, 'getModerationHistory']);
        // Lấy (Get) danh sách (list) user (người dùng) bị ban (khóa)
        Route::get('/users/banned', [UserManagementController::class, 'getBannedList']);

        // để xử lý (handle) file (tệp) upload (tải lên) trên (on) 'update' (cập nhật)
        Route::get('/advertisements', [AdminAdvertisementController::class, 'index']);
        Route::post('/advertisements', [AdminAdvertisementController::class, 'store']);
        // (SỬA) Dùng (Use) POST (Gửi) cho update (cập nhật) để hỗ trợ (support) `form-data` (dữ liệu biểu mẫu) (file (tệp) upload (tải lên))
        Route::post('/advertisements/{advertisement}', [AdminAdvertisementController::class, 'update']);
        Route::delete('/advertisements/{advertisement}', [AdminAdvertisementController::class, 'destroy']);

        //Cập nhật logo cho admin
        Route::post('/settings/logo', [AdminSettingController::class, 'updateLogo']);
        // Cập nhật backgound
        Route::post('/settings/background', [AdminSettingController::class, 'updateBackground']);
        // Cập nhật footer
        Route::post('/settings/footer', [AdminSettingController::class, 'updateFooter']);

        // Kích hoạt cào tin tức chủ động cho admin
        Route::post('/crawler/trigger', [NewsCrawlerController::class, 'trigger']);

        // Quản lý các nguồn cào tin tức tự động (CRUD)
        Route::apiResource('crawler-sources', AdminCrawlerSourceController::class);
        Route::patch('/crawler-sources/{crawler_source}/toggle', [AdminCrawlerSourceController::class, 'toggle']);
});

Route::middleware(['auth:sanctum', 'role:superadmin'])
    ->prefix('superadmin') // Tiền tố /api/superadmin
    ->name('superadmin.')
    ->group(function () {
        Route::patch('/users/{user}/role', [UserRoleController::class, 'updateRole'])
             ->name('users.updateRole');
        Route::get('/adminlist',[UserRoleController::class,'searchAdmin']);
});




