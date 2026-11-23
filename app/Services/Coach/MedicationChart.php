<?php

namespace App\Services\Coach;

/** SVG geometry for the 14-days-back / 7-days-ahead estimate chart (past solid, future dashed). */
class MedicationChart
{
    public const W = 330;
    public const H = 150;
    public const PAD = 15;

    /** @return array{past:?string,future:?string,todayX:float,hasData:bool} */
    public static function build(MedicationLevel $m): array
    {
        $curve = $m->curve;

        if (! $curve) {
            return ['past' => null, 'future' => null, 'todayX' => 0.0, 'hasData' => false];
        }

        $first = $curve[0]['t'];
        $span = (end($curve)['t'] - $first) ?: 1;
        $x = fn (float $t) => round(self::PAD + ($t - $first) / $span * (self::W - self::PAD * 2), 1);
        $pt = fn (array $p) => $x($p['t']).','.round(14 + (1 - $p['level']) * (self::H - 34), 1);

        $past = collect($curve)->filter(fn ($p) => $p['t'] <= 0)->map($pt)->implode(' ');
        $future = collect($curve)->filter(fn ($p) => $p['t'] >= 0)->map($pt)->implode(' ');

        return ['past' => $past ?: null, 'future' => $future ?: null, 'todayX' => $x(0.0), 'hasData' => true];
    }
}