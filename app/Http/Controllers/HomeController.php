<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Leva cada usuário para a área do seu papel: Staff Weega ou Cliente.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->isStaff() ? 'staff.dashboard' : 'app.dashboard');
    }
}
