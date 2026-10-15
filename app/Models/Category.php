<?php

namespace App\Models;

use App\Enums\CategoryKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'user_id',
        'parent_id',
        'name',
        'slug',
        'kind',
        'icon',
        'color',
        'is_system',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CategoryKind::class,
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CategoryRule::class);
    }
}
