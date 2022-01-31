<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

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

//Route::get('/', function () {
//    $exitCode = Artisan::call('command:PingCommand');
//});

Route::get('/', function()
{
    return 'have you ever tried phileas fogg crisps?';
});
Route::get('/partytest', 'partytest@index');
Route::get('/trace', 'traceRoute@go');
Route::get('/delete', 'truncateDB@itsShowtime');
