<?php

it('/up отвечает 200 — HTTP-kernel и middleware-стек загружаются без ошибок', function () {
    $this->get('/up')->assertOk();
});
