<?php
/** @cond ALL */

namespace Luminous\Formatters;

use Luminous\Utils\ColorUtils;
use Luminous\Utils\CssParser;

/**
 * ANSI output formatter for Luminous.
 */
class AnsiFormatter extends Formatter
{
    // xterm256color color codes. Retrieved from https://git.io/xterm256color on 2015-09-26 and converted to Lab
    const XTERM256COLOR = array(
        "000" => array(  0.000000,   0.000000,   0.000000),
        "001" => array( 25.535531,  48.045128,  38.057296),
        "002" => array( 46.227431, -51.698496,  49.896846),
        "003" => array( 51.868943, -12.929464,  56.674579),
        "004" => array( 12.971967,  47.502281, -64.702162),
        "005" => array( 29.784667,  58.927896, -36.487077),
        "006" => array( 48.254093, -28.846304,  -8.476886),
        "007" => array( 77.704367,  -0.000013,   0.000005),
        "008" => array( 53.585016,  -0.000010,   0.000004),
        "009" => array( 53.240794,  80.092460,  67.203197),
        "010" => array( 87.734722, -86.182716,  83.179321),
        "011" => array( 97.139267, -21.553748,  94.477975),
        "012" => array( 32.297011,  79.187520, -107.860162),
        "013" => array( 60.324212,  98.234312, -60.824892),
        "014" => array( 91.113220, -48.087528, -14.131186),
        "015" => array(100.000004,  -0.000017,   0.000007),
        "016" => array(  0.000000,   0.000000,   0.000000),
        "017" => array(  7.460692,  38.390899, -52.344041),
        "018" => array( 14.108800,  49.366227, -67.241015),
        "019" => array( 20.416780,  59.708756, -81.328423),
        "020" => array( 26.461219,  69.619186, -94.827275),
        "021" => array( 32.297011,  79.187520, -107.860162),
        "022" => array( 34.362921, -41.841471,  40.383330),
        "023" => array( 36.003172, -23.346362,  -6.860652),
        "024" => array( 37.721074,  -8.280292, -28.838129),
        "025" => array( 40.044712,   8.050351, -49.077929),
        "026" => array( 42.896244,  24.232072, -67.665859),
        "027" => array( 46.179103,  39.611555, -84.835619),
        "028" => array( 48.669178, -53.727096,  51.854752),
        "029" => array( 49.680825, -41.468213,  12.871276),
        "030" => array( 50.775364, -29.978206,  -8.809511),
        "031" => array( 52.309747, -16.087685, -29.668380),
        "032" => array( 54.271652,  -0.984531, -49.346593),
        "033" => array( 56.628677,  14.436593, -67.825764),
        "034" => array( 62.217771, -64.983255,  62.718643),
        "035" => array( 62.913963, -56.274791,  30.552786),
        "036" => array( 63.677487, -47.533738,   9.989760),
        "037" => array( 64.765216, -36.258826, -10.655158),
        "038" => array( 66.184274, -23.179986, -30.659176),
        "039" => array( 67.928678,  -9.021871, -49.792238),
        "040" => array( 75.200318, -75.769144,  73.128652),
        "041" => array( 75.714081, -69.238116,  46.415771),
        "042" => array( 76.281325, -62.437099,  27.358874),
        "043" => array( 77.096125, -53.317791,   7.414754),
        "044" => array( 78.170587, -42.277048, -12.423696),
        "045" => array( 79.508487, -29.803889, -31.743841),
        "046" => array( 87.734722, -86.182716,  83.179321),
        "047" => array( 88.132543, -81.079314,  60.784276),
        "048" => array( 88.573418, -75.649889,  43.369240),
        "049" => array( 89.209664, -68.192330,  24.408752),
        "050" => array( 90.053903, -58.903863,   5.054882),
        "051" => array( 91.113220, -48.087528, -14.131186),
        "052" => array( 17.616214,  38.884668,  27.208148),
        "053" => array( 21.055194,  47.692487, -29.530317),
        "054" => array( 24.265489,  55.109279, -50.109929),
        "055" => array( 28.188460,  63.497258, -68.189398),
        "056" => array( 32.565034,  72.278448, -84.495140),
        "057" => array( 37.209055,  81.157734, -99.539334),
        "058" => array( 38.928802, -10.464285,  45.868796),
        "059" => array( 40.317682,  -0.000008,   0.000003),
        "060" => array( 41.792415,   9.716881, -22.184768),
        "061" => array( 43.816568,  21.358548, -42.829511),
        "062" => array( 46.341283,  33.910621, -61.915173),
        "063" => array( 49.295490,  46.651030, -79.609352),
        "064" => array( 51.565360, -31.106941,  55.362293),
        "065" => array( 52.493892, -22.366057,  17.186391),
        "066" => array( 53.502318, -13.755714,  -4.459620),
        "067" => array( 54.922246,  -2.860324, -25.412901),
        "068" => array( 56.747662,   9.522785, -45.263794),
        "069" => array( 58.953975,  22.669708, -63.961919),
        "070" => array( 64.235031, -48.203292,  65.170137),
        "071" => array( 64.897084, -41.171043,  33.487406),
        "072" => array( 65.624132, -33.963343,  13.012989),
        "073" => array( 66.661569, -24.464553,  -7.626328),
        "074" => array( 68.017831, -13.189300, -27.680081),
        "075" => array( 69.689139,  -0.708182, -46.900093),
        "076" => array( 76.698001, -62.880681,  74.951857),
        "077" => array( 77.195429, -57.221495,  48.537298),
        "078" => array( 77.744943, -51.270878,  29.570908),
        "079" => array( 78.534816, -43.206116,   9.664419),
        "080" => array( 79.577356, -33.321295, -10.175418),
        "081" => array( 80.876952, -22.010117, -29.524777),
        "082" => array( 88.898351, -75.968373,  84.597226),
        "083" => array( 89.287443, -71.354717,  62.392493),
        "084" => array( 89.718758, -66.422134,  45.055576),
        "085" => array( 90.341414, -59.608536,  26.140485),
        "086" => array( 91.167986, -51.063898,   6.804468),
        "087" => array( 92.205709, -41.038767, -12.384583),
        "088" => array( 27.165347,  49.930374,  40.136678),
        "089" => array( 29.358410,  55.725044, -15.903001),
        "090" => array( 31.581214,  61.240172, -37.918796),
        "091" => array( 34.491549,  68.043425, -57.611837),
        "092" => array( 37.945003,  75.652936, -75.432962),
        "093" => array( 41.798486,  83.706896, -91.791834),
        "094" => array( 43.266004,   9.134592,  50.930049),
        "095" => array( 44.465039,  16.311047,   6.512751),
        "096" => array( 45.750668,  23.372978, -15.766712),
        "097" => array( 47.534439,  32.300943, -36.702982),
        "098" => array( 49.787278,  42.444465, -56.184495),
        "099" => array( 52.457917,  53.224022, -74.320646),
        "100" => array( 54.532058, -13.436804,  58.898437),
        "101" => array( 55.385516,  -6.768114,  21.580884),
        "102" => array( 56.315467,  -0.000010,   0.000004),
        "103" => array( 57.630008,   8.825705, -21.021347),
        "104" => array( 59.328134,  19.179536, -41.022229),
        "105" => array( 61.391858,  30.508200, -59.920728),
        "106" => array( 66.374922, -33.335627,  67.745825),
        "107" => array( 67.003415, -27.527170,  36.582861),
        "108" => array( 67.694487, -21.482419,  16.212526),
        "109" => array( 68.682127, -13.384693,  -4.410654),
        "110" => array( 69.975892,  -3.594073, -24.507224),
        "111" => array( 71.574010,   7.447858, -43.809975),
        "112" => array( 78.315904, -50.585277,  76.909139),
        "113" => array( 78.796543, -45.651434,  50.818542),
        "114" => array( 79.327805, -40.421790,  31.953767),
        "115" => array( 80.091978, -33.269832,  12.092110),
        "116" => array( 81.101528, -24.409844,  -7.745063),
        "117" => array( 82.361425, -14.154994, -27.121909),
        "118" => array( 90.168532, -65.770182,  86.138290),
        "119" => array( 90.548420, -61.599052,  64.141611),
        "120" => array( 90.969646, -57.119911,  46.891522),
        "121" => array( 91.577948, -50.900805,  28.027875),
        "122" => array( 92.385840, -43.052445,   8.713289),
        "123" => array( 93.400696, -33.779293, -10.477090),
        "124" => array( 36.208754,  60.391097,  50.573730),
        "125" => array( 37.739975,  64.495259,  -2.438323),
        "126" => array( 39.353431,  68.650313, -25.128730),
        "127" => array( 41.549773,  74.070366, -45.863018),
        "128" => array( 44.264011,  80.458448, -64.848646),
        "129" => array( 47.410429,  87.520359, -82.356598),
        "130" => array( 48.637025,  27.330267,  57.029239),
        "131" => array( 49.649655,  32.345900,  14.536338),
        "132" => array( 50.745209,  37.483199,  -7.743369),
        "133" => array( 52.280931,  44.249599, -28.930913),
        "134" => array( 54.244425,  52.280310, -48.806029),
        "135" => array( 56.603189,  61.178271, -67.411894),
        "136" => array( 58.455996,   5.073270,  63.495100),
        "137" => array( 59.223239,  10.069966,  27.347971),
        "138" => array( 60.062286,  15.267320,   5.894811),
        "139" => array( 61.253487,  22.225763, -15.176012),
        "140" => array( 62.800706,  30.633519, -35.336720),
        "141" => array( 64.692889,  40.111206, -54.465103),
        "142" => array( 69.308960, -16.251898,  71.238024),
        "143" => array( 69.895406, -11.599340,  40.796840),
        "144" => array( 70.541246,  -6.687200,  20.584981),
        "145" => array( 71.466005,  -0.000013,   0.000005),
        "146" => array( 72.680407,   8.238495, -20.139565),
        "147" => array( 74.184959,  17.716313, -39.540668),
        "148" => array( 80.579920, -35.513903,  79.627400),
        "149" => array( 81.038448, -31.346986,  53.992269),
        "150" => array( 81.545637, -26.892900,  35.276028),
        "151" => array( 82.275842, -20.742163,  15.484099),
        "152" => array( 83.241675, -13.032556,  -4.342368),
        "153" => array( 84.448794,  -3.993322, -23.750841),
        "154" => array( 91.967824, -52.701251,  88.309654),
        "155" => array( 92.335220, -49.036719,  66.608006),
        "156" => array( 92.742744, -45.081868,  49.483561),
        "157" => array( 93.331530, -39.558175,  30.696050),
        "158" => array( 94.113989, -32.535991,  11.415203),
        "159" => array( 95.097663, -24.169464,  -7.773705),
        "160" => array( 44.874337,  70.414781,  59.082945),
        "161" => array( 46.012582,  73.488282,  10.528988),
        "162" => array( 47.236695,  76.706186, -12.348562),
        "163" => array( 48.940884,  81.051413, -33.681818),
        "164" => array( 51.101856,  86.364529, -53.475339),
        "165" => array( 53.674597,  92.446330, -71.879038),
        "166" => array( 54.695304,  43.548940,  63.726908),
        "167" => array( 55.544895,  47.195327,  23.494868),
        "168" => array( 56.470786,  51.029166,   1.345906),
        "169" => array( 57.779848,  56.225393, -20.000213),
        "170" => array( 59.471313,  62.597129, -40.204567),
        "171" => array( 61.527524,  69.897355, -59.241373),
        "172" => array( 63.159654,  22.859865,  68.897396),
        "173" => array( 63.839588,  26.634208,  34.185583),
        "174" => array( 64.585756,  30.632858,  12.941230),
        "175" => array( 65.649581,  36.098152,  -8.134153),
        "176" => array( 67.038832,  42.864229, -28.433915),
        "177" => array( 68.748596,  50.691293, -47.788927),
        "178" => array( 73.772876,   4.940386,  76.470142),
        "179" => array( 74.302964,   8.462953,  47.139095),
        "180" => array( 74.887904,  12.235241,  27.200224),
        "181" => array( 75.727537,  17.455650,   6.706370),
        "182" => array( 77.087368,  25.473894, -17.428061),
        "183" => array( 78.209600,  31.731328, -32.985806),
        "184" => array( 86.238525, -19.477088,  85.375211),
        "185" => array( 86.648004, -16.200582,  61.248531),
        "186" => array( 87.101640, -12.661096,  43.085524),
        "187" => array( 87.756009,  -7.711972,  23.607536),
        "188" => array( 88.823635,  -0.000015,   0.000006),
        "189" => array( 89.711805,   6.106712, -15.490142),
        "190" => array( 94.826696, -34.506139,  91.732558),
        "191" => array( 95.175471, -31.448211,  70.499621),
        "192" => array( 95.562547, -28.128368,  53.580735),
        "193" => array( 96.122168, -23.458420,  34.921715),
        "194" => array( 97.038346, -16.118921,  11.850715),
        "195" => array( 97.803417, -10.255393,  -3.476476),
        "196" => array( 53.240794,  80.092460,  67.203197),
        "197" => array( 54.125781,  82.492192,  22.910970),
        "198" => array( 55.088767,  85.054618,   0.168144),
        "199" => array( 56.447798,  88.591017, -21.450672),
        "200" => array( 58.595844,  94.009359, -45.675219),
        "201" => array( 60.324212,  98.234312, -60.824892),
        "202" => array( 61.177753,  58.007184,  70.725237),
        "203" => array( 61.892577,  60.769076,  32.940064),
        "204" => array( 62.675958,  63.722867,  11.059157),
        "205" => array( 63.790979,  67.805180, -10.333124),
        "206" => array( 65.574700,  74.067043, -34.735550),
        "207" => array( 67.027700,  78.950491, -50.165199),
        "208" => array( 68.456202,  39.347025,  74.858462),
        "209" => array( 69.054426,  42.256401,  41.778310),
        "210" => array( 69.712953,  45.379691,  20.832601),
        "211" => array( 70.655381,  49.714784,  -0.184657),
        "212" => array( 72.174957,  56.401627, -24.560814),
        "213" => array( 73.423104,  61.643523, -40.132156),
        "214" => array( 77.236080,  18.715563,  80.467683),
        "215" => array( 77.727829,  21.651859,  52.000981),
        "216" => array( 78.271171,  24.819797,  32.297655),
        "217" => array( 79.052359,  29.242703,  11.899654),
        "218" => array( 80.320594,  36.120189, -12.244697),
        "219" => array( 81.369962,  41.554730, -27.861347),
        "220" => array( 88.940967,  -5.949435,  88.556758),
        "221" => array( 89.329745,  -3.127867,  64.952969),
        "222" => array( 89.760716,  -0.064977,  46.989362),
        "223" => array( 90.382882,   4.242886,  27.623647),
        "224" => array( 91.399180,  11.011808,   4.061457),
        "225" => array( 92.245761,  16.418199, -11.438094),
        "226" => array( 97.139267, -21.553748,  94.477975),
        "227" => array( 97.473993, -18.866927,  73.623332),
        "228" => array( 97.845623, -15.939407,  56.875584),
        "229" => array( 98.383182, -11.803189,  38.326889),
        "230" => array( 99.263912,  -5.261198,  15.318244),
        "231" => array(100.000004,  -0.000017,   0.000007),
        "232" => array(  2.193408,  -0.000001,   0.000000),
        "233" => array(  5.463911,  -0.000002,   0.000001),
        "234" => array( 10.268185,  -0.000004,   0.000002),
        "235" => array( 15.159721,  -0.000004,   0.000002),
        "236" => array( 19.865535,  -0.000005,   0.000002),
        "237" => array( 24.421321,  -0.000006,   0.000002),
        "238" => array( 28.851904,  -0.000006,   0.000003),
        "239" => array( 33.175474,  -0.000007,   0.000003),
        "240" => array( 37.405892,  -0.000008,   0.000003),
        "241" => array( 41.554045,  -0.000008,   0.000003),
        "242" => array( 45.628691,  -0.000009,   0.000004),
        "243" => array( 49.637017,  -0.000009,   0.000004),
        "244" => array( 53.585016,  -0.000010,   0.000004),
        "245" => array( 57.477759,  -0.000011,   0.000004),
        "246" => array( 61.319585,  -0.000011,   0.000004),
        "247" => array( 65.114248,  -0.000012,   0.000005),
        "248" => array( 68.865021,  -0.000012,   0.000005),
        "249" => array( 72.574786,  -0.000013,   0.000005),
        "250" => array( 76.246094,  -0.000013,   0.000005),
        "251" => array( 79.881220,  -0.000014,   0.000006),
        "252" => array( 83.482203,  -0.000014,   0.000006),
        "253" => array( 87.050883,  -0.000015,   0.000006),
        "254" => array( 90.588923,  -0.000015,   0.000006),
        "255" => array( 94.097838,  -0.000016,   0.000006)
    );

    private $css = null;

    public function setTheme($theme)
    {
        $this->css = new CssParser();
        $this->css->convert($theme);
    }

    protected function linkify($src)
    {
        return $src;
    }

    protected function colorSequence($hex, $background = false)
    {
        $rgb = ColorUtils::hex2rgb($hex);
        $escSeq = "\033[" . ($background ? "48;" : "38;");
        if ($this->colorDistanceAlgorithm !== 'none') {
            $lab = ColorUtils::xyz2lab(ColorUtils::rgb2xyz(ColorUtils::normalizeRgb($rgb)));
            $nearestColor = ColorUtils::nearestColor(
                static::XTERM256COLOR,
                $lab,
                $this->colorDistanceAlgorithm,
                true,
                "xterm256color"
            );
            return $escSeq . "5;" . $nearestColor . "m";
        }
        return $escSeq . "2;" . implode(';', $rgb) . "m";
    }

    public function insertEscapeSequences($matches)
    {
        $match = strtolower($matches[1]);
        $rules = $this->css->rules();
        $escSeq = '';

        if (isset($rules[$match])) {
            if ($this->css->value($match, 'bold', false) === true) {
                $escSeq .= "\033[1m";
            }
            if ($this->css->value($match, 'italic', false) === true) {
                $escSeq .= "\033[3m";
            }
            if ($this->css->value($match, 'underline', false) === true) {
                $escSeq .= "\033[4m";
            }
            if ($this->css->value($match, 'strikethrough', false) === true) {
                $escSeq .= "\033[9m";
            }
            if (($color = $this->css->value($match, 'color', null)) !== null) {
                if (preg_match('/^#[a-f0-9]{6}$/i', $color)) {
                    $escSeq .= static::colorSequence($color);
                }
            }
            if (($bgColor = $this->css->value($match, 'bgcolor', null)) !== null) {
                if (preg_match('/^#[a-f0-9]{6}$/i', $bgColor)) {
                    $escSeq .= static::colorSequence($bgColor, true);
                }
            }
        }
        return $escSeq;
    }

    public function format($str)
    {
        if ($this->css === null) {
            throw new Exception('ANSI formatter has not been set a theme');
        }
        $out = '';

        $s = '';
        $str = preg_replace('%<([^/>]+)>\s*</\\1>%', '', $str);
        $str = str_replace("\t", '  ', $str);

        $lines = explode("\n", $str);

        if ($this->wrapLength > 0) {
            $str = '';
            foreach ($lines as $i => $l) {
                $this->wrapLine($l, $this->wrapLength);
                $str .= $l;
            }
        }

        $str_ = preg_split('/(<[^>]+>)/', $str, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        foreach ($str_ as $s_) {
            if ($s_[0] === '<') {
                $s_ = preg_replace('%</[^>]+>%', "\033[0m", $s_);
                $s_ = preg_replace_callback('%<([^>]+)>%', array($this, 'insertEscapeSequences'), $s_);
            } else {
                $s_ = str_replace('&gt;', '>', $s_);
                $s_ = str_replace('&lt;', '<', $s_);
                $s_ = str_replace('&amp;', '&', $s_);
            }
            $s .= $s_;
        }
        unset($str_);

        $out .= $s;
        return $out;
    }
}
/** @endcond */
