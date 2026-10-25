<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An AI research prompt, kept in versions. One template serves every language: the language is
 * a variable, so the research routine is written once (option PT3). Created with version 1, as
 * a guide is.
 */
class PromptTemplate extends Model
{
    /**
     * The research prompt's name (`PromptTemplateSeeder`): the only template a request's
     * "Preparar prompt" offers. Renamed, it is still a template; the popup just stops offering it.
     */
    public const RESEARCH = 'Ruta de aprendizaje — investigación';

    /**
     * The cover prompt's name (`CoverPromptTemplateSeeder`): each guide's Presentation screen
     * offers it filled from the guide. Renamed, it is still a template; the screen just stops
     * offering it.
     */
    public const COVER = 'Portada de guía — imagen';

    /**
     * The guide flyer prompt's name (`GuideFlyerPromptTemplateSeeder`), offered on the same screen,
     * filled from the guide, as the cover's is.
     */
    public const GUIDE_FLYER = 'Flyer de guía — imagen';

    protected $table = 'guide_prompt_templates';

    protected $fillable = ['name', 'description'];

    protected static function booted(): void
    {
        static::created(fn (self $template) => $template->versions()->create(['number' => 1]));
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PromptVersion::class, 'template_id')->orderBy('number');
    }

    public function latestVersion(): PromptVersion
    {
        return $this->versions()->reorder('number', 'desc')->firstOrFail();
    }
}
