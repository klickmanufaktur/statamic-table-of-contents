<?php

namespace Doefom\StatamicTableOfContents;

use Doefom\StatamicTableOfContents\Modifiers\WithIds;
use Doefom\StatamicTableOfContents\Modifiers\Toc;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $modifiers = [
        Toc::class,
        WithIds::class,
    ];

    public function bootAddon() {}
}
