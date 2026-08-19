<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function index()
    {
        return view('instructor.profile', [
            'currentPage' => 'profile',
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        // This is a web route, not under api/*, so $request->validate()'s
        // failure path would try to redirect back instead of returning
        // JSON (see bootstrap/app.php's shouldRenderJsonWhen()) - the JS
        // here expects JSON either way, so validate manually.
        $validator = Validator::make($request->all(), [
            'max_teams' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $expert = auth('expert')->user();
        $expert->update(['max_teams' => $validator->validated()['max_teams']]);

        return response()->json([
            'success' => true,
            'message' => 'Team capacity updated.',
            'max_teams' => $expert->max_teams,
        ]);
    }
}
