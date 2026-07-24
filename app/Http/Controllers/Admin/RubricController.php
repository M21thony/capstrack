<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rubric;
use Illuminate\Http\Request;

class RubricController extends Controller
{
    public function index()
    {
        $rubrics = Rubric::with('criteria')->orderByDesc('created_at')->get();

        return view('admin.rubric_management', [
            'rubrics' => $rubrics,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'criteria' => 'required|array|min:1',
            'criteria.*.name' => 'required|string|max:255',
            'criteria.*.max_score' => 'required|numeric|min:1|max:100',
            'criteria.*.weight' => 'required|numeric|min:0|max:100',
        ]);

        $rubric = Rubric::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'admin_id' => auth()->guard('expert')->id(),
        ]);

        foreach ($data['criteria'] as $criterion) {
            $rubric->criteria()->create($criterion);
        }

        return response()->json($rubric->load('criteria'), 201);
    }

    public function update(Request $request, Rubric $rubric)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'criteria' => 'required|array|min:1',
            'criteria.*.name' => 'required|string|max:255',
            'criteria.*.max_score' => 'required|numeric|min:1|max:100',
            'criteria.*.weight' => 'required|numeric|min:0|max:100',
        ]);

        $rubric->update(['name' => $data['name'], 'type' => $data['type']]);
        $rubric->criteria()->delete();

        foreach ($data['criteria'] as $criterion) {
            $rubric->criteria()->create($criterion);
        }

        return response()->json($rubric->load('criteria'));
    }

    public function destroy(Rubric $rubric)
    {
        $rubric->criteria()->delete();
        $rubric->delete();

        return response()->json(['success' => true]);
    }
}
