<?php

namespace App\Strategies;

interface StrategyInterface
{
    /**
     * Verilen mum serisinin SON mumu üzerinden bir sinyal adayı üretir.
     * Yeterli veri yoksa ya da bir setup oluşmuyorsa null döner.
     */
    public function evaluate(CandleSeries $candles): ?SignalCandidate;
}
