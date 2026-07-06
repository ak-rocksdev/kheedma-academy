<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\View\View;

class ProgramPageController extends Controller
{
    /** Program promo landing; Task 6 gives it its real view. */
    public function show(Program $program): View
    {
        abort_if($program->status === 'draft', 404);

        return view('funnel.program', ['program' => $program]);
    }
}
