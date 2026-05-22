<?php

namespace App\Services\Bpjs;

class ICareService extends BpjsBaseService
{
    protected string $module = 'icare';

    /**
     * Validasi data ICare RS.
     * GET /api/rs/validate
     */
    public function validate(): array
    {
        return $this->get('/api/rs/validate');
    }
}
