<?php
namespace App\Models\AiLead\Account;

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
        'user_id' => 'integer',
        'name' => 'string',
        'value' => 'string',
        'domain' => 'string',
        'path' => 'string',
        'expiry' => 'integer',
        'secure' => 'boolean',
        'httpOnly' => 'boolean',
        'sameSite' => 'string',
    ];

}
