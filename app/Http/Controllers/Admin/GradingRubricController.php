<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\AdminGradingRubricController;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class GradingRubricController extends Controller
{
    /**
     * The rubrics come with the page, in the shape the page's own API answers
     * with, so the list is drawn at once. Fetched after the page was on screen,
     * the list grew in and pushed the page controls down under it.
     */
    public function index(AdminGradingRubricController $api): View
    {
        return view('capsa.rubric_management', [
            'initialRubrics' => $api->index()->getData(true)['data'],
        ]);
    }
}
