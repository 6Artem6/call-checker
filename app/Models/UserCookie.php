<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Builder
 */
class UserCookie extends Model
{

    protected $table = 'user_cookies';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'name',
        'value',
        'domain',
        'path',
        'expiry',
        'secure',
        'httpOnly',
        'sameSite',
    ];
    protected $hidden = [
        'id',
    ];
    protected $casts = [
        'secure' => 'boolean',
        'httpOnly' => 'boolean',
        'expiry' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
