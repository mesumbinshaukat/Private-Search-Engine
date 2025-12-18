<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndexMetadata extends Model
{
    protected $fillable = [
        'category',
        'date',
        'record_count',
        'file_path',
        'google_drive_file_id',
        'checksum',
        'uploaded_at',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'uploaded_at' => 'datetime',
    ];

    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('date', $date);
    }

    public function scopeUploaded($query)
    {
        return $query->whereNotNull('uploaded_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('uploaded_at');
    }
}
