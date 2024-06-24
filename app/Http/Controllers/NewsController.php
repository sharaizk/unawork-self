<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\Pod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NewsController extends Controller
{
    public function index(int $podId)
    {
        $currentWorkspace = Auth::user()->currentWorkspace;
        $pod = Pod::find($podId);
        $news = $pod->news;
        return view('pod.manage.news', compact('currentWorkspace', 'pod', 'news'));
    }

    public function edit(int $podId, int $newsId)
    {
        $post = News::find($newsId);

        return view('news.edit_form', compact('post'));
    }

    public function create(int $podId)
    {
        return view('news.create_form', compact('podId'));
    }
}
