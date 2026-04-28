<?php

use App\Enums\UserRole;

it('bartender can manage', fn() => expect(UserRole::Bartender->canManage())->toBeTrue());
it('owner can manage', fn() => expect(UserRole::Owner->canManage())->toBeTrue());
it('guest cannot manage', fn() => expect(UserRole::Guest->canManage())->toBeFalse());
