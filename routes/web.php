<?php

use Illuminate\Support\Facades\Route;

/*
  |--------------------------------------------------------------------------
  | Web Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register web routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | contains the "web" middleware group. Now create something great!
  |
 */


Route::namespace("Web")
        ->name("web")
        ->prefix('report')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\Web\FaceController::class, 'report_formatted'])->name('report');
            Route::get('/raw', [\App\Http\Controllers\Web\FaceController::class, 'report'])->name('report_raw');
            Route::get('/data', [\App\Http\Controllers\Web\FaceController::class, 'getData'])->name('data');
            Route::get('/data_beautifullify', [\App\Http\Controllers\Web\FaceController::class, 'getDataFormatted'])->name('data_pretty');
        });
Route::namespace("Web")
        ->name("web")
        ->prefix('log')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\Web\LogController::class, 'report_formatted'])->name('log');
            Route::get('/rawlog', [\App\Http\Controllers\Web\LogController::class, 'report'])->name('report_raw_log');
            Route::get('/datalog', [\App\Http\Controllers\Web\LogController::class, 'getData'])->name('datalog');
            Route::get('/data_beautifullify_log', [\App\Http\Controllers\Web\LogController::class, 'getDataFormatted'])->name('data_pretty_log');
        });
//Route::namespace("Web")
//        ->name("web")
//        ->prefix('report')
//        ->group(function () {
//            Route::get('/', [\App\Http\Controllers\Web\ExportController::class, 'index'])->name('report');
//            Route::get('/draw', [\App\Http\Controllers\Web\ExportController::class, 'draw'])->name('report_draw');
//        });
//            Route::resource('report', \App\Http\Controllers\Web\ExportController::class);
Route::get('', [\App\Http\Controllers\Web\SettingController::class, 'index'])->name('index');
Route::get('/home', [\App\Http\Controllers\Web\SettingController::class, 'index'])->name('home');
Route::get('/group', [\App\Http\Controllers\Web\GroupController::class, 'index'])->name('group');
Route::get('/group.data', [\App\Http\Controllers\Web\GroupController::class, 'list_formatted'])->name('group.data');
Route::post('/group.pull', [\App\Http\Controllers\Web\GroupController::class, 're_pull'])->name('group.pull');
Route::get('/person', [\App\Http\Controllers\Web\PersonController::class, 'index'])->name('person');
Route::get('/person.data', [\App\Http\Controllers\Web\PersonController::class, 'list_formatted'])->name('person.data');
Route::post('/person.pull', [\App\Http\Controllers\Web\PersonController::class, 're_pull'])->name('person.pull');
Route::resource('setting', \App\Http\Controllers\Web\SettingController::class);
Route::get('sendsap', [\App\Http\Controllers\Web\SendSapController::class,'index'])->name('sendsapindex');
Route::post('sendsap.transfer', [\App\Http\Controllers\Web\SendSapController::class, 'transfer_sap'])->name('sendsap.transfer');
Route::post('setting.check', [\App\Http\Controllers\Web\SettingController::class, 'check_connection'])->name('setting.check');

