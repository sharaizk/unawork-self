<?php

namespace App\Services;

use App\Models\News;
use App\Models\Pod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

        if (isset($data['image'])) {
            $data['image'] = $this->uploadImage($data['image']);
        }

        $newsObj = new News($data);
        $news = $newsable->news()->save($newsObj);
        return $news;
    }

    public function update(array $data, News $news)
    {
        if (isset($data['image'])) {
            //del old image if it was
            if ($news->image) {
                Storage::delete($news->image);
            }
            
            $data['image'] = $this->uploadImage($data['image']);    
        }

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

    protected function uploadImage(UploadedFile $image): string
    {
        $dir = 'news-images';
        $imgName = uniqid() . '.png';
        $path = $image->storeAs($dir, $imgName, 'public');

        if ($path) {
            return $path;
        } else {
            throw new \Exception('Failed to upload image.');
        }
    }
}