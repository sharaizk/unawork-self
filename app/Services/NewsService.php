<?php

namespace App\Services;

use App\Models\News;
use App\Models\Pod;

class NewsService
{
    public function store(array $data)
    {
        $newsableType = $data['newsable_type'];
        $newsableId = $data['newsable_id'];

        if ($newsableType == 'pod') {
            $newsable = Pod::find($newsableId);
        } else {
            throw new \Exception('Invalid newsable type');
        }

        if (!$newsable) {
            throw new \Exception('Newsable entity not found');
        }

        $newsObj = new News($data);
        $news = $newsable->news()->save($newsObj);
        return $news;
    }

    public function update(array $data, News $news)
    {
        if ($news) {
            $news->update($data);
            return $news;
        } else {
            throw new \Exception('News not found');
        }
    }

    public function destroy(News $news)
    {
        if ($news) {
            $news->delete();
            return true;
        } else {
            throw new \Exception('News not found');
        }
    }
}