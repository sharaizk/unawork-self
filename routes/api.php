<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\apicontroller;
use App\Http\Controllers\Api\PodController;
use App\Http\Controllers\Api\PodUserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SkillController;

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
Route::post('/profile', [ProfileController::class, 'store']);
Route::post('/profile/{id}', [ProfileController::class, 'update']);
Route::delete('/profile/{id}', [ProfileController::class, 'destroy']);
    
});
