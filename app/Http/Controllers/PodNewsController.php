<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\Pod;
use Illuminate\Http\Request;

class PodNewsController extends Controller
{
    public function list(Pod $pod)
    {
        abort_unless($pod->isPublished(), 404); // UNA-011: drafts are never public
        $news = $pod->news()->latest('created_at')->get();
        return view('news.list', compact('pod', 'news'));
    }

    public function show(Pod $pod, News $news)
    {
        abort_unless($pod->isPublished(), 404);
        $post = $news;
        return view('news.one_post', compact('pod', 'post'));
    }
}
