<?php

namespace App\Strategies;

/**
 * Bir stratejinin ürettiği ham sinyal adayı. Henüz DB'ye yazılmış bir Signal
 * DEĞİL — RiskRules doğrulamasından geçmeden gerçek sinyale dönüşmez.
 */
class SignalCandidate
{
    public function __construct(
        public readonly string $direction, // 'buy' | 'sell'
        public readonly float $entryPrice,
        public readonly float $slPips,
        public readonly float $tpPips,
        public readonly ?float $confidencePct = null,
        public readonly array $meta = [],
    ) {}
}
