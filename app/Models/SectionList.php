<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The subdomain sections (admin, booking, ...) each preview of a Cloud
 * application gets, edited on the router's landing page.
 */
#[Fillable(['application_id', 'application_name', 'sections'])]
class SectionList extends Model
{
    protected function casts(): array
    {
        return ['sections' => 'array'];
    }

    /**
     * @return list<string>
     */
    public static function for(string $applicationId): array
    {
        return static::where('application_id', $applicationId)->value('sections') ?? [];
    }

    /**
     * Normalize free-form input ("admin, Booking portal") into DNS labels.
     *
     * @return list<string>
     */
    public static function parse(string $input): array
    {
        return collect(preg_split('/[\s,]+/', strtolower($input)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
