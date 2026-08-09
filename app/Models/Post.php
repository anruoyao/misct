<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;
    public $table = "posts";

    protected $fillable = [
        'user_id',
        'desc',
        'tags',
        'link_preview_json',
        'interest_ids',
    ];
    
    public function content()
    {
        return $this->hasMany(PostContent::class, 'post_id', 'id');
    }
    
    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public static function processPosts($posts, $userId)
    {
        $postIds = $posts->pluck('id');

        $likedPosts = Like::whereIn('post_id', $postIds)
                        ->where('user_id', $userId)
                        ->pluck('post_id')
                        ->toArray();

        foreach ($posts as $post) {
            $post->is_like = in_array($post->id, $likedPosts) ? 1 : 0;
        }

        return $posts;
    }

}
