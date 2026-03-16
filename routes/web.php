<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    Log::channel('telegram')->info('Тест лога в Telegram!');
    return 'welcome';
});
