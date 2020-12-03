<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name', 'email', 'password',
        'telegram_chat_id',
        'fio_from_telegram',
        'phone',
        'is_admin',
        'age',
        'sex',
        'need_meeting',
        'city',
        'latitude',
        'longitude',
        'last_search',
        'settings',
        'meet_in_week',
        'prefer_meet_in_week',
        'updated_at',
        'created_at',
    ];

    protected $casts = [
        "location" => "array",
        'id' => "string",
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function circles()
    {
        return $this->belongsToMany(Circle::class, 'user_in_circles', 'user_id', 'circle_id')
            /*            ->withPivot([
                            'inviter_id',
                        ])*/
            ->withTimestamps();
    }

    public function events()
    {
        return $this->belongsToMany(MeetEvents::class, 'user_on_events', 'user_id', 'event_id')
            ->withTimestamps();
    }


    private function meets1()
    {
        return $this->hasMany(Meet::class, 'user1_id', 'id');
    }

    private function meets2()
    {
        return $this->hasMany(Meet::class, 'user2_id', 'id');
    }

    private static function filterUsers($user_list)
    {
        $tmp_users_array_ids = [];

        foreach ($user_list as $user) {
            $in_ignored = !is_null(IgnoreList::where("ignored_user_id", $user->id)
                ->orWhere("main_user_id", $user->id)
                ->first());

            if (!in_array($user->id, $tmp_users_array_ids) && !$in_ignored)
                array_push($tmp_users_array_ids, $user->id);

        }

        return User::whereIn('id', $tmp_users_array_ids)
            ->get();
    }

    public static function getCityUsers($userId, $city, $update_time = 5)
    {

        $user_list = User::where("city", $city)
            ->where("last_search", ">=", Carbon::now("+3")->subMinute($update_time))
            ->where("id", "<>", $userId)
            ->get();

        return User::filterUsers($user_list);

    }

    public static function prepareUsersWithDist($userId, $user_list)
    {

        $user = User::where("id", $userId)->first();

        $tmp_users_with_dist = [];


        foreach ($user_list as $user_item) {
            $dist = pow(
                pow($user_item->latitude - $user->latitude, 2) +
                pow($user_item->longitude - $user->longitude, 2), 0.5);

            Log::info($user_item->id . " ~" . round($dist * 1000) . " метров");
            array_push($tmp_users_with_dist, [
                "user" => $user_item,
                "dist" => round($dist * 1000),
                "metric" => "метр",
                "last_seen" => Carbon::now()->diffInHours($user_item->updated_at),
            ]);
        }

        return $tmp_users_with_dist;
    }

    public static function getNearestUsers($userId, $latitude, $longitude, $dist = 1000/*0.5км*/, $update_time = 5)
    {
        $dist = $dist / 1000;

        $lon1 = $longitude - $dist / abs(cos(rad2deg($latitude)) * 111.0); # 1 градус широты = 111 км
        $lon2 = $longitude + $dist / abs(cos(rad2deg($latitude)) * 111.0);
        $lat1 = $latitude - ($dist / 111.0);
        $lat2 = $latitude + ($dist / 111.0);

        $user_list = User::whereBetween('latitude', [$lat1, $lat2])
            ->whereBetween('longitude', [$lon1, $lon2])
            ->where("last_search", ">=", Carbon::now("+3")->subMinute($update_time))
            ->where("id", "<>", $userId)
            ->get();

        return User::filterUsers($user_list);

    }

}
