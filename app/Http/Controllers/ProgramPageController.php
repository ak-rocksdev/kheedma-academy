<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\View\View;

class ProgramPageController extends Controller
{
    /** Two-door chooser: open programs + the community invitation. */
    public function chooser(): View
    {
        $programs = Program::openForRegistration()->latest()->get();

        return view('funnel.chooser', compact('programs'));
    }

    /** Program promo landing. Open: CTA to the form. Closed: invite to community. */
    public function show(Program $program): View
    {
        abort_if($program->status === 'draft', 404);

        return view('funnel.program', ['program' => $program, 'isOpen' => $program->isOpen()]);
    }
}
