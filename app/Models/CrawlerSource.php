<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlerSource extends Model
{
    use HasFactory;

    protected $table = 'crawler_sources';

    protected $fillable = [
        'source_name',
        'feed_url',
        'description',
        'category_id',
        'is_active',
        'last_crawled_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_crawled_at' => 'datetime',
    ];

    /**
     * Chuyên mục liên kết với nguồn tin này
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
