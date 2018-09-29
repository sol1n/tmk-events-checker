<?php

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

Route::get('/', 'BaseController@index')->name('index');
Route::get('/{date?}', 'BaseController@index')->name('index')->where('date', '[0-9/.]+');;
Route::get('/event/{event}', 'BaseController@event')->name('event');
Route::post('/event/{event}', 'BaseController@save')->name('save');
Route::post('/login', 'BaseController@login')->name('login');
