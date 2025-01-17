<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Profit;
use App\Models\Project;
use App\Models\Utility;
use Auth;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $currentWorkspace = $user->currentWorkspace;

        if (!$currentWorkspace) {
            return redirect()->back()->with('error', 'Access Denied!');
        }

        $projects = Project::where('workspace', $currentWorkspace->id)
            ->where('created_by', $user->id)
            ->get();

        return view('finance.index', compact('currentWorkspace', 'projects'));
    }

    public function financeReport(Request $request)
    {

        $user = Auth::user();

        // Get all projects owned by the user in the current workspace
        $projects = Project::where('created_by', $user->id)
            ->pluck('id');

        // Fetch all expenses and profits for these projects
        $expenses = Expense::whereIn('project_id', $projects)
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('date', [$request->start_date, $request->end_date]);
            })
            ->get(['date', 'amount', 'project_id']);

        $profits = Profit::whereIn('project_id', $projects)
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('date', [$request->start_date, $request->end_date]);
            })
            ->get(['date', 'amount', 'project_id']);

        return response()->json([
            'expenses' => $expenses,
            'profits' => $profits,
        ]);
    }
}
