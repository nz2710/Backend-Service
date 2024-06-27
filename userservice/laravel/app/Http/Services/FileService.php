<?php

namespace App\Http\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    public function slug($content)
    {
        $content = trim(mb_strtolower($content));
        $content = preg_replace('/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/', 'a', $content);
        $content = preg_replace('/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/', 'e', $content);
        $content = preg_replace('/(ì|í|ị|ỉ|ĩ)/', 'i', $content);
        $content = preg_replace('/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/', 'o', $content);
        $content = preg_replace('/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/', 'u', $content);
        $content = preg_replace('/(ỳ|ý|ỵ|ỷ|ỹ)/', 'y', $content);
        $content = preg_replace('/(đ)/', 'd', $content);
        $content = preg_replace('/[^a-z0-9-\s]/', '', $content);
        $content = preg_replace('/([\s]+)/', '-', $content);
        return $content;
    }

    public function storePublicFile($path, $file){
        $storage = Storage::put($path, $file);

        return Str::replaceFirst('public/', '/storage/', $storage);
    }

    public function storePublicArrayUrl(Request $request, $namePath)
    {
        $path = self::slug($namePath);

        $preview = [];

        foreach ($request->preview as $key => $step) {
            if ($request->hasFile('preview.' . $key)) {
                $file = self::storePublicFile(
                    "public/products/$path/preview",
                    $request->file('preview.' . $key)
                );
                $arrayKey = (($key < 10) ? "0" : "") . ($key + 1);
                $preview[$arrayKey] = $file;
            }
        }

        return $preview;
    }

    public function storeLocalFile($file, $path, $name){
        $url = $file->storeAs($path, $name);
        return $url;
    }

    public function deletePublicFile($url){
        $url = Str::replaceFirst('/storage', 'public', $url);
        Storage::delete($url);
        return $url;
    }

    public function deleteArrayPublicFile($arrayUrl)
    {
        $preview = [];

        foreach ($arrayUrl as $key => $url) {

            $file = self::deletePublicFile($url);

            array_push($preview, $file);
        }

        return $preview;
    }

    public function deleteLocalFile($url){
        Storage::delete($url);
    }

    public function deleteArrayLocalFile($arrayUrl)
    {
        $urls = [];

        foreach ($arrayUrl as $key => $url) {

            $file = $this->deleteLocalFile($url);

            array_push($urls, $file);
        }

        return $urls;
    }

}
