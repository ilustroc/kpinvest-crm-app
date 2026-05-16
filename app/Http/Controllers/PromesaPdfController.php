<?php

namespace App\Http\Controllers;

use App\Actions\Promesa\GeneratePromesaAgreementAction;
use App\Models\PromesaPago;

class PromesaPdfController extends Controller
{
    public function __construct(private readonly GeneratePromesaAgreementAction $generateAgreement)
    {
    }

    public function acuerdo(PromesaPago $promesa)
    {
        return $this->generateAgreement->execute(auth()->user(), $promesa);
    }
}
