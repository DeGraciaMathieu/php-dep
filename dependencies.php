<?php

use App\Core\Config\Config;
use App\Core\Rules\Rules;

return Config::make()
    ->paths(['src'])
    ->rules([
        Rules::noCycles(),
    ]);
