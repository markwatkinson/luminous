<?php

namespace Luminous\Utils;

class ColorUtils
{
    protected static $nearestColors = array();

    /**
     * Converts a hexadecimal string in the form #ABCDEF to an RGB array
     */
    public static function hex2rgb($hex)
    {
        $x = hexdec(substr($hex, 1));
        $b = $x % 256;
        $g = ($x >> 8) % 256;
        $r = ($x >> 16) % 256;

        $rgb = array($r, $g, $b);
        return $rgb;
    }

    /**
     * Normalises each element of an RGB array to the range 0-1
     */
    public static function normalizeRgb($rgb)
    {
        return array_map(function ($n) {
            return $n / 255.0;
        }, $rgb);
    }

    /**
     * Converts an normalised RGB array to an XYZ array
     * Algorithm from http://www.brucelindbloom.com/Eqn_RGB_to_XYZ.html
     * It is assumed that the sRGB color space is used
     */
    public static function rgb2xyz($rgb)
    {
        // Inverse sRGB Companding
        $rgb = array_map(function ($n) {
            if ($n <= 0.04045) {
                return $n / 12.92;
            }
            return pow(($n + 0.055) / 1.055, 2.4);
        }, $rgb);

        // Linear RGB to XYZ
        return array(
            0.4124564 * $rgb[0] + 0.3575761 * $rgb[1] + 0.1804375 * $rgb[2],
            0.2126729 * $rgb[0] + 0.7151522 * $rgb[1] + 0.0721750 * $rgb[2],
            0.0193339 * $rgb[0] + 0.1191920 * $rgb[1] + 0.9503041 * $rgb[2]
        );
    }

    /**
     * Converts an XYZ array to an Lab array
     * Algorithm from http://www.brucelindbloom.com/Eqn_XYZ_to_Lab.html
     * Reference whites from http://brucelindbloom.com/Eqn_ChromAdapt.html#CommonChromAdapt
     */
    public static function xyz2lab($xyz)
    {
        $x = $xyz[0] / 0.95047;
        $y = $xyz[1] / 1.00000;
        $z = $xyz[2] / 1.08883;

        $f = function ($n) {
            if ($n > 0.008856) {
                return pow($n, 1/3);
            }
            return (903.3 * $n + 16) / 116;
        };
        $fx = $f($x);
        $fy = $f($y);
        $fz = $f($z);
        return array(
            116 * $fy - 16,
            500 * ($fx - $fy),
            200 * ($fy - $fz)
        );
    }

    /**
     * Calculates the distance between two colors using the CIE76 algorithm
     * Algorithm from http://www.brucelindbloom.com/Eqn_DeltaE_CIE76.html
     */
    public static function cie76($refLab, $lab)
    {
        return sqrt(
            pow($refLab[0] - $lab[0], 2) +
            pow($refLab[1] - $lab[1], 2) +
            pow($refLab[2] - $lab[2], 2)
        );
    }

    /**
     * Calculates the distance between two colors using the CIE94 algorithm
     * Algorithm from http://www.brucelindbloom.com/Eqn_DeltaE_CIE94.html
     */
    public static function cie94($refLab, $lab)
    {
        $C1 = sqrt(pow($refLab[1], 2) + pow($refLab[2], 2));
        $C2 = sqrt(pow($lab[1], 2) + pow($lab[2], 2));
        $SL = 1;
        $SC = 1 + 0.045 * $C1;
        $SH = 1 + 0.015 * $C1;
        $ΔL = $refLab[0] - $lab[0];
        $ΔC = $C1 - $C2;
        $Δa = $refLab[1] - $lab[1];
        $Δb = $refLab[2] - $lab[2];
        $ΔH = pow($Δa, 2) + pow($Δb, 2) + pow($ΔC, 2);
        return sqrt(pow($ΔL / $SL, 2) + pow($ΔC / $SC, 2) + $ΔH / pow($SH, 2));
    }

    /**
     * Calculates the distance between two colors using the CIEDE2000 algorithm
     * Algorithm from http://www.brucelindbloom.com/Eqn_DeltaE_CIE2000.html
     */
    public static function ciede2000($refLab, $lab)
    {
        $LP = ($refLab[0] + $lab[0]) / 2;
        $C1 = sqrt(pow($refLab[1], 2) + pow($refLab[2], 2));
        $C2 = sqrt(pow($lab[1], 2) + pow($lab[2], 2));
        $C = ($C1 + $C2) / 2;
        $G = (1 - sqrt(pow($C, 7) / (pow($C, 7) + 6103515625))) / 2;
        $a1P = $refLab[1] * (1 + $G);
        $a2P = $lab[1] * (1 + $G);
        $C1P = sqrt(pow($a1P, 2) + pow($refLab[2], 2));
        $C2P = sqrt(pow($a2P, 2) + pow($lab[2], 2));
        $CP = ($C1P + $C2P) / 2;
        $h1P = rad2deg(atan2($refLab[2], $a1P));
        if ($h1P < 0) {
            $h1P += 360;
        }
        $h2P = rad2deg(atan2($lab[2], $a2P));
        if ($h2P < 0) {
            $h2P += 360;
        }
        $HP = ($h1P + $h2P) / 2;
        if (abs($h1P - $h2P) > 180) {
            $HP += 180;
        }
        $T = 1 -
            0.17 * rad2deg(cos(deg2rad($HP - 30))) +
            0.24 * rad2deg(cos(deg2rad(2 * $HP))) +
            0.32 * rad2deg(cos(deg2rad(3 * $HP + 6))) -
            0.20 * rad2deg(cos(deg2rad(4 * $HP - 63)));
        $ΔhP = $h2P - $h1P;
        if (abs($ΔhP) > 180) {
            $ΔhP += 360;
            if ($h2P > $h1P) {
                $ΔhP -= 720;
            }
        }
        $ΔLP = $lab[0] - $refLab[0];
        $ΔCP = $C2P - $C1P;
        $ΔHP = 2 * sqrt($C1P * $C2P) * rad2deg(sin(deg2rad($ΔhP / 2)));
        $SL = 1 + (0.015 * pow($LP - 50, 2)) / sqrt(20 + pow($LP - 50, 2));
        $SC = 1 + 0.045 * $CP;
        $SH = 1 + 0.015 * $CP * $T;
        $Δθ = 30 * exp(-pow(($HP - 275) / 25, 2));
        $RC = 2 * sqrt(pow($CP, 7) / (pow($CP, 7) + 6103515625));
        $RT = -$RC * rad2deg(sin(deg2rad(2 * $Δθ)));
        return sqrt(pow($ΔLP / $SL, 2) + pow($ΔCP / $SC, 2) + pow($ΔHP / $SH, 2) + $RT * ($ΔCP / $SC) * ($ΔHP / $SH));
    }

    /**
     * Searches a list of colors for the one with the shortest distance to a given color
     */
    public static function nearestColor($refLabs, $lab, $algorithm, $returnArrayKey = false, $cacheKey = "")
    {
        // Calculating a unique cache key takes even longer than calculating the distance, so the caller needs to craft
        // a cache key by hand
        $subKey = $lab[0] . $lab[1] . $lab[2];
        $result = array();
        if (empty($cacheKey) || !isset(static::$nearestColors[$cacheKey]) ||
            !isset(static::$nearestColors[$cacheKey][$subKey])) {
            // Nearest color not yet in cache, needs to be calculated
            $nearestColor = [0, 10000];
            foreach ($refLabs as $key => $refLab) {
                $distance = static::{$algorithm}($refLab, $lab);
                if ($distance < $nearestColor[1]) {
                    // New low
                    $nearestColor = [$key, $distance];
                }
            }
            $result = array($nearestColor[0], $refLabs[$nearestColor[0]]);
            if (!empty($cacheKey)) {
                // Put the result into the cache
                if (!isset(static::$nearestColors[$cacheKey])) {
                    static::$nearestColors[$cacheKey] = array();
                }
                static::$nearestColors[$cacheKey][$subKey] = $result;
            }
        }
        if (empty($result)) {
            // Fetch nearest color from cache
            $result = static::$nearestColors[$cacheKey][$subKey];
        }
        if ($returnArrayKey) {
            return $result[0];
        }
        return $result[1];
    }
}
