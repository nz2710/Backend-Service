<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

use App\Traits\HasPermissions;
use App\Traits\HasRoles;


class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasRoles;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'profession',
        'first_name',
        'last_name',
        'location',
        'status',
        'apikey',
        'partner_id'
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'updated_at',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];


    public function roles()
    {
        return $this->belongsToMany(Role::class, RoleUser::class, 'user_id', 'role_id')->withTimestamps();
    }

    // This code will be called every time a new user is inserted into the system
    // protected static function booted()
    // {
    //     static::created(function ($user) {
    //         $user->wallets()->create([
    //             'user_id' => $user->id,
    //             'balance' => 0
    //         ]);

    //         // Adding 1000$ for every new account
    //         $user->deposit(1000, 0, 'usd','deposit' ,'Create Account','create new account', null);
    //     });
    // }
}
