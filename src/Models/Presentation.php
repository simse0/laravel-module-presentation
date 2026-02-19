<?php

namespace Trafficdesign\Presentation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Presentation extends Model
{
    protected $fillable = [
        'presentable_type',
        'presentable_id',
        'user_id',
        'title',
        'slide_order',
        'text_overrides',
        'settings',
    ];

    protected $casts = [
        'slide_order' => 'array',
        'text_overrides' => 'array',
        'settings' => 'array',
    ];

    /**
     * Polymorphe Relation zum Subject (Feedback, Report, etc.).
     */
    public function presentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('presentation.user_model', 'App\\Models\\User'));
    }

    public function getOverride(string $key, ?string $default = null): ?string
    {
        return data_get($this->text_overrides, $key, $default);
    }

    public function isSlideActive(string $slideId): bool
    {
        if ($this->slide_order === null) return true;
        return in_array($slideId, $this->slide_order);
    }
}
