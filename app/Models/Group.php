<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $table = 'groups';
    public $timestamps = false;

    protected $fillable = [
        'career_id',
        'cycle_id',
        'cuatrimestre',
        'group_number',
        'group_code',
    ];

    public function career()
    {
        return $this->belongsTo(Career::class, 'career_id');
    }

    public function cycle()
    {
        return $this->belongsTo(AcademicCycle::class, 'cycle_id');
    }
}
