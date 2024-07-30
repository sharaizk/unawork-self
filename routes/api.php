<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\apicontroller;
use App\Http\Controllers\Api\PodController;
use App\Http\Controllers\Api\PodUserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\JobVacancyController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectCategoryController;
use App\Http\Controllers\Api\JobApplicationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
//Route::get('login', [LoginController::class, 'login']);

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);


Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('/get-projects', [apicontroller::class, 'getProjects']);
    Route::get('/get-workspace', [apicontroller::class, 'getworkspace']);
    Route::post('add-tracker', [apicontroller::class, 'addTracker']);
    Route::post('stop-tracker', [apicontroller::class, 'stopTracker']);
    Route::post('upload-photos', [apicontroller::class, 'uploadImage']);




    // Route::post('logout', 'apicontroller@logout');
    // Route::get('/get-projects', 'apicontroller@getProjects');
    //  Route::get('/get-workspace', 'apicontroller@getworkspace');
    // Route::post('add-tracker', 'apicontroller@addTracker');
    // Route::post('stop-tracker', 'apicontroller@stopTracker');
    // Route::post('upload-photos', 'apicontroller@uploadImage')

Route::resource('skill', SkillController::class)->middleware(['auth:sanctum']);
Route::post('/skill', [SkillController::class, 'store'])->name('api.skill.store');


Route::get('/pod', [PodController::class, 'index']);
Route::post('/pod', [PodController::class, 'store']);
Route::get('/pod/{id}', [PodController::class, 'show']);
Route::post('/pod/{id}', [PodController::class, 'update']);
Route::delete('/pod/{id}', [PodController::class, 'destroy']);


Route::post('/pod/{id}/users', [PodUserController::class, 'inviteExistedUser']);
Route::post('/pod/{id}/users/new', [PodUserController::class, 'inviteNewUser']);

Route::post('/pods/{id}/requests', [PodUserController::class, 'join']);
Route::get('/pods/{id}/requests', [PodUserController::class, 'listing']);
Route::post('pods/{id}/requests/{requestId}', [PodUserController::class, 'update']);

Route::get('/profile', [ProfileController::class, 'index']);
Route::post('/profile', [ProfileController::class, 'update']);
Route::delete('/profile/{id}', [ProfileController::class, 'destroy']);


Route::get('/employees', [EmployeeController::class, 'index']);
Route::get('/employees/{id}', [EmployeeController::class, 'show']);


Route::resource('news', NewsController::class)->except('update', 'store', 'delete');
Route::post('/news', [NewsController::class, 'store'])->name('api.news.store');
Route::post('/news/{id}', [NewsController::class, 'update'])->name('api.news.update');
Route::delete('/news/{id}', [NewsController::class, 'update'])->name('api.news.delete');


Route::get('/project', [ProjectController::class, 'index']);
Route::post('/project', [PodController::class, 'store']);
Route::get('/project/{id}', [PodController::class, 'show']);
Route::post('/project/{id}', [PodController::class, 'update']);
Route::delete('/project/{id}', [PodController::class, 'destroy']);

Route::apiResource('project-categories', ProjectCategoryController::class)->except('store');

Route::post('/project-category', [ProjectCategoryController::class, 'store'])->name('api.project-category.store');

Route::apiResource('job-vacancies', JobVacancyController::class)->except('store','show', 'update', 'delete');
Route::post('job-vacancies', [JobVacancyController::class, 'store'])->name('api.job_vacancies.store');
Route::post('job-vacancies/{job_vacancy}', [JobVacancyController::class, 'update'])->name('api.job_vacancies.update');
Route::get('job-vacancies/{job_vacancy}', [JobVacancyController::class, 'show'])->name('api.job_vacancies.show');
Route::delete('job-vacancies/{job_vacancy}', [JobVacancyController::class, 'destroy'])->name('api.job_vacancies.destroy');


Route::prefix('job-applications')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [JobApplicationController::class, 'index'])->name('job-applications.index');
    Route::post('/', [JobApplicationController::class, 'store'])->name('job-applications.store');
    Route::patch('/{jobApplication}/accept', [JobApplicationController::class, 'accept'])->name('job-applications.accept');
    Route::patch('/{jobApplication}/reject', [JobApplicationController::class, 'reject'])->name('job-applications.reject');
});

});