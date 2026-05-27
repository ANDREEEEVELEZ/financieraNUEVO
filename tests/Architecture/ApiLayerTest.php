<?php

test('Api controllers extend base Controller')
    ->expect('App\Http\Controllers\Api')
    ->toExtend('App\Http\Controllers\Controller');

test('Api resources extend JsonResource')
    ->expect('App\Http\Resources\Api')
    ->toExtend('Illuminate\Http\Resources\Json\JsonResource');

test('Api requests extend FormRequest')
    ->expect('App\Http\Requests\Api')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

test('ApiResponse is final')
    ->expect('App\Http\Responses\ApiResponse')
    ->toBeFinal();
