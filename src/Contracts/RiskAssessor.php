<?php

declare(strict_types=1);

namespace Gcego\Sentinel\Contracts;

use Gcego\Sentinel\Risk\RiskAssessment;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Scores a sign-in that has already proved its credentials. Signals are
 * hints, never proof: Sentinel uses the assessment to notify and audit,
 * and never refuses a correct sign-in on risk alone.
 */
interface RiskAssessor
{
    public function assess(Request $request, Authenticatable $user, string $method): RiskAssessment;
}
