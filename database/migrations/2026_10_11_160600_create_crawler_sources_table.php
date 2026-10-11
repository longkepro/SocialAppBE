<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crawler_sources', function (Blueprint $table) {
            $table->id();
            $table->string('source_name', 100)->comment('Tên nguồn báo, ví dụ: VnExpress, Tuổi Trẻ');
            $table->string('feed_url', 255)->unique()->comment('Đường dẫn RSS Feed của nguồn');
            $table->string('description', 255)->nullable()->comment('Mô tả chuyên mục cào');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete()->comment('Chuyên mục gán cho bài cào');
            $table->boolean('is_active')->default(true)->comment('Trạng thái kích hoạt cào');
            $table->timestamp('last_crawled_at')->nullable()->comment('Thời gian cào gần nhất');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crawler_sources');
    }
};
