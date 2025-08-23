<?php

declare(strict_types=1);

namespace Misakstvanu\LaravelFortifyPasskeys\Security\Storage;

interface OptionsStorage
{
    public function store(Item $item): void;

    public function get(string $challenge): Item;
}
