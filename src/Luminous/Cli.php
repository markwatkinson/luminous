<?php
/** @cond ALL */

namespace Luminous;

use Luminous as LuminousUi;

/* command line interface */
class Cli
{
    private $options = array(
        'input-file' => null,
        'output-file' => null,
        'lang' => null,
        'format' => 'html-full',
        'height' => 0,
        'theme' => 'geonyx',
        'code' => null,
        'line-numbers' => true,
    );
    private $cmdOptionMap = array(
        '-i' => 'input-file',
        '-o' => 'output-file',
        '-l' => 'lang',
        '-f' => 'format',
        '-h' => 'height',
        '-t' => 'theme',
    );

    public static function printHelp()
    {
        global $argv;

        echo <<<EOF
Usage:
  {$argv[0]} [OPTIONS] [SOURCE_CODE]

SOURCE_CODE may be omitted if you specify -i. Use '-' to read code from stdin.

Options:
  -f <format>           Output format. This can be:
                        'html' - An HTML snippet (div). CSS is not included
                          on the page, the style-sheets must be included by
                          hand.
                        'html-full' - A full HTML page. CSS is embedded.
                        'latex' - A LaTeX document.
                        Default: 'html-full'
  -h <height>           Constrains the height of the widget with 'html'
                        formatter. Has no effect on other formatters.
                        Default: 0
  -i <filename>         Input file. If this is omitted, SOURCE_CODE is used.
  -l <language>         Language code. If this is omitted, the language is
                        guessed.
  -o <filename>         Output file to write. If this is omitted, stdout is
                        used.
  -t <theme>            Theme to use. See --list-themes for valid themes
  --no-numbers          Disables line numbering

  --list-codes          Lists valid language codes and exits
  --list-themes         Lists valid themes and exits

  --help                Display this text and exit
  --version             Display version number and exit

EOF;
    }

    public function error($string)
    {
        echo "Error: $string
see --help for help
";
        exit(1);
    }

    public function setLookahead($option, $i)
    {
        global $argv;
        if (isset($argv[$i + 1])) {
            $this->options[$this->cmdOptionMap[$option]] = $argv[$i + 1];
        } else {
            self::error('Missing option for ' . $option);
        }
    }


    public function listCodes()
    {
        foreach (LuminousUi::scanners() as $name => $codes) {
            echo sprintf("%s: %s\n", $name, join(', ', $codes));
        }
        exit(0);
    }

    public function listThemes()
    {
        echo preg_replace('/\.css$/m', '', join("\n", LuminousUi::themes()) . "\n");
        exit(0);
    }

    public function parseArgs()
    {
        global $argv, $argc;
        for ($i = 1; $i < $argc; $i++) {
            $a = $argv[$i];

            if (isset($this->cmdOptionMap[$a])) {
                $this->setLookahead($a, $i++);
            } elseif ($a === '--list-codes') {
                self::listCodes();
            } elseif ($a === '--list-themes') {
                self::listThemes();
            } elseif ($a === '--help') {
                self::printHelp();
                exit(0);
            } elseif ($a === '--version') {
                echo LUMINOUS_VERSION;
                echo "\n";
                exit(0);
            } elseif ($a === '--no-numbers') {
                $this->options['line-numbers'] = false;
            } else {
                if ($this->options['code'] !== null) {
                    self::error('Unknown option: ' . $a);
                } else {
                    $this->options['code'] = $a;
                }
            }
        }
    }

    public function highlight()
    {
        $this->parseArgs();

        // figure out the code

        // error cases are:
        if ($this->options['code'] === null && $this->options['input-file'] === null) {
            // no input file or source code,
            $this->error('No input file or source code specified');
        } elseif ($this->options['code'] !== null && $this->options['input-file'] !== null) {
            // or both input file and source code
            $this->error('Input file (-i) and source code specified. You probably didn\'t mean this');
        }

        if ($this->options['input-file'] !== null) {
            // is there an input file? use that.
            $c = @file_get_contents($this->options['input-file']);
            if ($c === false) {
                $this->error('Could not read from ' . $this->options['input-file']);
            } else {
                $this->options['code'] = $c;
            }
        } elseif ($this->options['code'] === '-') {
            // else we're expecting code to have been given on the command line,
            // but it might be '-' which means read stdin
            $code = '';
            while (($line = fgets(STDIN)) !== false) {
                $code .= $line;
            }
            $this->options['code'] = $code;
        }

        // set the formatter
        LuminousUi::set('format', $this->options['format']);
        // lame check that the formatter is okay
        try {
            LuminousUi::formatter();
        } catch (Exception $e) {
            $this->error('Unknown formatter ' . $this->options['format']);
        }

        // set the theme
        $validThemes = LuminousUi::themes();
        $theme = $this->options['theme'];
        if (!preg_match('/\.css$/', $theme)) {
            $theme .= '.css';
        }
        if (!LuminousUi::themeExists($theme)) {
            $this->error('No such theme: ' . $theme);
        } else {
            LuminousUi::set('theme', $theme);
        }

        // set the language
        if ($this->options['lang'] === null) {
            // guessing
            $this->options['lang'] = LuminousUi::guessLanguage($this->options['code']);
        }

        // user provided language
        $scanners = LuminousUi::scanners();
        $validScanner = false;
        foreach ($scanners as $lang => $codes) {
            if (in_array($this->options['lang'], $codes)) {
                $validScanner = true;
                break;
            }
        }
        if (!$validScanner) {
            $this->error('No such language: ' . $this->options['lang']);
        }

        // other options
        LuminousUi::set('max-height', $this->options['height']);
        LuminousUi::set('line-numbers', $this->options['line-numbers']);

        $h = LuminousUi::highlight($this->options['lang'], $this->options['code']);
        if ($this->options['output-file'] !== null) {
            $r = @file_put_contents($this->options['output-file'], $h, LOCK_EX);
            if ($r === false) {
                $this->error('Could not write to ' . $this->options['output-file']);
            }
        } else {
            echo $h;
        }
        exit(0);
    }
}

/** @endcond */
