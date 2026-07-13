<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LeaveCalculatorController extends Controller
{
    public function index(): View
    {
        return view('tools.leave-calculator');
    }
}
