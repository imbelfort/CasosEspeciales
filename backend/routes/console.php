<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Minishlink\WebPush\VAPID;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('webpush:claves', function () {
    $claves = VAPID::createVapidKeys();
    $this->line('WEB_PUSH_PUBLIC_KEY='.$claves['publicKey']);
    $this->line('WEB_PUSH_PRIVATE_KEY='.$claves['privateKey']);
})->purpose('Generar las claves VAPID para los avisos del navegador');
