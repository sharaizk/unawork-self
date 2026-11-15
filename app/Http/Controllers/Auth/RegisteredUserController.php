<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Utility;
use App\Models\UserWorkspace;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Models\Mail\SendLoginDetail;
use Illuminate\Support\Facades\DB;
use App\Enums\User\UserRole;
use Illuminate\Validation\Rules\Enum;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Services\Provisioning\ProvisionUserData;
use App\Services\Provisioning\ProvisioningSource;
use App\Services\Provisioning\UserProvisioningService;


class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * @return \Illuminate\View\View
     */


    public function __construct()
    {
        $this->middleware('guest');
    }


    public function create()
    {
        // return view('auth.register');
    }

    public function showRegistrationForm($ref = '', $lang = '')
    {
        $setting = Utility::getAdminPaymentSetting();
        if ($lang == '') {
            $lang = $setting['default_admin_lang'] ? $setting['default_admin_lang'] : 'en';
        }
        $langList = Utility::langList();
        $lang = array_key_exists($lang, $langList) ? $lang : 'en';
        if (empty($lang)) {
            $lang = Utility::getValByName('default_language');
        }
        \App::setLocale($lang);

        if ($ref == '') {
            $ref = 0;
        }
        $refCode = User::where('referral_code', '=', $ref)->first();

        if ($refCode->referral_code != $ref) {
            return redirect()->route('register');
        }

        if ($setting['signup_button'] == 'on') {
            return view('auth.register', compact('lang','ref'));
        } else {
            return abort('404', 'Page not found');
        }

        return view('auth.register', compact('lang', 'ref'));
    }

    // public function store(Request $request)
    // {
    //     if(env('RECAPTCHA_MODULE') == 'on')
    //     {
    //         $validation['g-recaptcha-response'] = 'required|captcha';
    //     }else{
    //         $validation = [];
    //     }
    //     $this->validate($request, $validation);
    //     $request->validate([
    //         'name' => 'required|string|max:255',
    //         'workspace' => 'required', 'string', 'max:255',
    //         'email' => 'required|string|email|max:255|unique:users',
    //         'password' => ['required','string', 'min:8', 'confirmed', Rules\Password::defaults()],
    //     ]);

    //     $user = User::create([
    //         'name' => $request->name,
    //         'email' => $request->email,
    //          'workspace'=>$request->workspace,
    //         'password' => Hash::make($request->password),
    //         'plan'=>1,
    //     ]);

    //     $objWorkspace = Workspace::create(['created_by'=>$user->id,'name'=>$request->workspace, 'currency_code' => 'USD', 'paypal_mode' => 'sandbox']);

    //     $setting = Utility::getAdminPaymentSetting();

    //      $userWorkspace               =   new UserWorkspace();
    //         $userWorkspace->user_id      =     $user->id;
    //         $userWorkspace->workspace_id =    $objWorkspace->id;
    //         $userWorkspace->permission  = 'Owner';

    //            if(empty($userWorkspace))
    //         {
    //             $errorArray[] = $userWorkspace;
    //         }
    //         else
    //         {
    //             $userWorkspace->save();
    //         }

    //     $user->currant_workspace = $objWorkspace->id;
    //     $user->save();
    //     Auth::login($user);

    //     if($setting['email_verification'] == 'on'){


    //         try{

    //             event(new Registered($user));
    //             // UserWorkspace::create(['user_id'=> $user->id,'workspace_id'=>$objWorkspace->id,'permission'=>'Owner']);
    //             if(empty($lang))
    //             {
    //                 $lang = env('DEFAULT_LANG');
    //             }
    //             \App::setLocale($lang);


    //         }catch(\Exception $e){

    //             $user->delete();

    //             // dd($user);
    //             return redirect('/register/lang?')->with('statuss', __('Email SMTP settings does not configure so please contact to your site admin.'));
    //         }

    //         return view('auth.verify-email', compact('lang'));
    //     }else{

    //         $user->email_verified_at = date('h:i:s');
    //         // UserWorkspace::create(['user_id'=> $user->id,'workspace_id'=>$objWorkspace->id,'permission'=>'Owner']);
    //         $user->save();
    //         return redirect(RouteServiceProvider::HOME);
    //     }

    // }

    /**
     * Web self-registration (UNA-012): validation here, account structure in
     * UserProvisioningService (one transaction), then login, verification/welcome email and the
     * onboarding redirect - all after the account has committed.
     */
    public function store(RegisterUserRequest $request, UserProvisioningService $provisioning)
    {
        $setting = Utility::getAdminPaymentSetting();
        $verify = ($setting['email_verification'] ?? 'off') === 'on';
        $lang = $setting['default_admin_lang'] ?: 'en';

        $result = $provisioning->provision(new ProvisionUserData(
            source: ProvisioningSource::WEB_REGISTRATION,
            email: $request->validated('email'),
            name: $request->validated('name'),
            role: UserRole::from($request->validated('role')),
            password: $request->validated('password'),
            emailVerified: !$verify,
            locale: $lang,
            workspaceName: $request->validated('name'),
            referralUsed: $request->validated('ref_code'),
            sendLoginDetails: !$verify, // after commit; the password is never included
        ));
        $user = $result->user;

        Auth::login($user);

        if ($verify) {
            \App::setLocale($lang);
            Utility::setMailConfig();
            try {
                event(new Registered($user));
            } catch (\Exception $e) {
                report($e);
                // The account exists; the user can resend the verification email from this page.
                session()->flash('statuss', __('Email SMTP settings does not configure so please contact to your site admin.'));
            }

            return view('auth.verify-email', compact('lang'));
        }

        // New employees and companies start onboarding straight away (UNA-007, UNA-010).
        $onboarding = app(\App\Services\Onboarding\OnboardingRedirect::class)->for($user);
        if ($onboarding) {
            return redirect()->route($onboarding['route']);
        }

        return redirect(RouteServiceProvider::HOME);
    }
}
