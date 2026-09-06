<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class GradingRubricController extends Controller
{
    public function index(): View
    {
        return view('capsa.rubric_management');
    }
}
