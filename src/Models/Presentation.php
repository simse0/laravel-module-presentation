<?php

namespace Trafficdesign\Presentation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Presentation extends Model
{
    protected $fillable = [
        'name',
        'presentable_type',
        'presentable_id',
        'user_id',
        'title',
        'slides_data',
        'report_data',
        'version_name',
        'slide_order',
        'text_overrides',
        'settings',
    ];

    protected $casts = [
        'slides_data' => 'array',
        'report_data' => 'array',
        'slide_order' => 'array',
        'text_overrides' => 'array',
        'settings' => 'array',
    ];

    public function presentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('presentation.user_model', 'App\\Models\\User'));
    }

    public function scopeByName(Builder $query, string $name): Builder
    {
        return $query->where('name', $name);
    }

    public function hasSnapshot(): bool
    {
        return ! empty($this->slides_data);
    }

    public function getSlides(): array
    {
        return $this->slides_data ?? [];
    }

    public function getReportData(): array
    {
        return $this->report_data ?? [];
    }

    public function getOverride(string $key, ?string $default = null): ?string
    {
        return data_get($this->text_overrides, $key, $default);
    }

    public function isSlideActive(string $slideId): bool
    {
        if ($this->slide_order === null) {
            return true;
        }

        return in_array($slideId, $this->slide_order, true);
    }
}
