<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Deployment;

class DashboardController extends Controller
{
    public function index()
    {
        $deployments = Deployment::latest()->take(10)->get();

        return Inertia::render('Dashboard', [
            'deployments' => $deployments,
        ]);
    }
}
