<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use App\Services\CompanyService;

class CompanyController extends Controller
{
    protected $companyService;

    public function __construct(CompanyService $companyService)
    {
        $this->companyService = $companyService;
    }

    public function list(Request $request)
    {
        $country = $request->input('country');
        $companies = $this->companyService->getCompanies($country);     

        $countries = $this->companyService->getCompanies()->pluck('country')->filter()->unique()->values();

        return view('company.landing.list', compact('companies', 'countries'));
    }
}