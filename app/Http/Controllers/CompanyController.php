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

            $companys = User::role('company')
            ->whereHas('profile', function($query) use ($country) {
                $query->where('country', $country);
            })
            ->get();
        } else {
            $companys = User::role('company')->get();
        }

        $countries = Profile::distinct()->pluck('country');

        return view('company.landing.list', compact('companys', 'countries'));
    }
}
