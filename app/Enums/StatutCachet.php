<?php

namespace App\Enums;

enum StatutCachet: string
{
    case Du = 'du';
    case DeclareePayee = 'declaree_payee';
    case DeclareeNonPayee = 'declaree_non_payee';
    case ValideePayee = 'validee_payee';
    case Annule = 'annule';
}
