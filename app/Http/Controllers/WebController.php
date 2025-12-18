<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ParsedRecord;
use App\Models\IndexMetadata;

class WebController extends Controller
{
    public function index()
    {
        return view('search');
    }
}
