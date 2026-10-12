<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

arch('no debugging calls are left in the code')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('models are Eloquent models')
    ->expect('App\Models')
    ->toExtend(Model::class)
    ->ignoring('App\Models\Concerns');

arch('enums are backed enums')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('form requests extend the framework base')
    ->expect('App\Http\Requests')
    ->toExtend(FormRequest::class);

arch('controllers are not used from the domain layer')
    ->expect('App\Services')
    ->not->toUse('App\Http\Controllers');

arch('environment variables are read only in configuration')
    ->expect('env')
    ->not->toBeUsedIn('App');
