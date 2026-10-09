<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotOption extends Model
{
    public const KIND_PRESENTATION = 'presentation';
    public const KIND_RECEPTION = 'reception';
    public const KINDS = [self::KIND_PRESENTATION, self::KIND_RECEPTION];

    protected $fillable = ['kind', 'start_time', 'end_time'];

    /**
     * The full set of consultant-selectable slot IDs derivable from the current
     * slot options: each presentation option is offered in-person only (video
     * conference participation is no longer allowed), while reception options
     * are offered once.
     *
     * @return string[]
     */
    public static function validSlotIds(): array
    {
        return static::all()
            ->flatMap(fn (self $option) => $option->kind === self::KIND_PRESENTATION
                ? ["in_person_{$option->id}"]
                : ["reception_{$option->id}"]
            )
            ->all();
    }
}
