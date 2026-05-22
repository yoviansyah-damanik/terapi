<?php

namespace App\Services\Bpjs\Erm\Resources;

use App\Services\Bpjs\Erm\ErmBuildContext;

abstract class BaseResourceBuilder
{
    public function __construct(protected ErmBuildContext $ctx) {}

    /** Builders wajib (tunggal) return 1 entry; opsional/multi return array of entries */
    abstract public function build(): array;
}
