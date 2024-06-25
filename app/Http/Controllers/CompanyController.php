<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function list(Request $request)
    {
        if ($request->has('country')) {
            $country = $request->input('country');

            $companies = User::role('company')
            ->whereHas('profile', function($query) use ($country) {
                $query->where('country', $country);
            })
            ->get();
        } else {
            $companies = User::role('company')->get();
        }

        $countries = Profile::distinct()->pluck('country');

        return view('company.landing.list', compact('companies', 'countries'));
    }
}
