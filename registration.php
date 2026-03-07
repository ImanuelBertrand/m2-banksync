<?php

use Magento\Framework\Component\ComponentRegistrar;

// Class check to make standalone linter tools work
if (class_exists(ComponentRegistrar::class)) {
    ComponentRegistrar::register(
        ComponentRegistrar::MODULE,
        'Ibertrand_BankSync',
        __DIR__
    );
}
