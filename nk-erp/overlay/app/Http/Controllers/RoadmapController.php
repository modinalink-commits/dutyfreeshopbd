<?php

namespace App\Http\Controllers;

use App\Support\Roadmap;

class RoadmapController extends Controller
{
    public function soon(string $key)
    {
        $item = Roadmap::find($key);
        abort_unless($item && ! $item['route'], 404);
        return view('soon', ['item' => $item]);
    }

    public function index()
    {
        return view('roadmap', ['menu' => Roadmap::MENU, 'apps' => Roadmap::APPS]);
    }
}
