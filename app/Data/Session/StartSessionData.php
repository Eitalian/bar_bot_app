<?php

namespace App\Data\Session;

use Spatie\LaravelData\Data;

/**
 * Старт сессии не принимает параметров — DTO нужен только для Bus-маппинга
 * (один формат для всех мутирующих команд проекта).
 */
final class StartSessionData extends Data {}
