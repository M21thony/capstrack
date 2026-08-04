<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;

class ProfileController extends Controller
{
    public function index()
    {
        return view('instructor.profile', [
            'currentPage' => 'profile',
        ]);
    }
}
