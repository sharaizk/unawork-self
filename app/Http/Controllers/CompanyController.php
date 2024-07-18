<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Services\CompanyService;

class CompanyController extends Controller
{
    protected $companyService;

    public function __construct(CompanyService $companyService)
    {
        $this->companyService = $companyService;
    }

    public function show(Workspace $company)
    {
        return view('company.landing.index', compact('company'));
    }


    public function list(Request $request)
    {
        $country = $request->input('country');
        $companies = $this->companyService->getCompanies($country);     

        $countries = $this->companyService->getCompanies()->pluck('country')->filter()->unique()->values();

        return view('company.landing.list', compact('companies', 'countries'));
    }
}