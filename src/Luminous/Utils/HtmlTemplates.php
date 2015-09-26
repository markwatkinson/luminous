<?php

/** @cond ALL */

namespace Luminous\Utils;

/**
 * Collection of templates and templating utilities
 */
class HtmlTemplates
{
    // NOTE Don't worry about whitespace in the templates - it gets stripped from the innerHTML,
    // so the <pre>s aren't affected. Make it readable :)

    /**
     * Normal container
     */
    const CONTAINER_TEMPLATE = '
        <div
            class="luminous"
            data-language="{language}"
            style="{height_css}"
        >
            {subelement}
        </div>';

    /**
     * Inline code container
     */
    const INLINE_TEMPLATE = '
        <div
            class="luminous inline"
            data-language="{language}"
        >
            {subelement}
        </div>';

    /**
     * line number-less
     */
    const NUMBERLESS_TEMPLATE = '
        <pre
            class="code"
        >
            {code}
        </pre>';

    /**
     * line numbered
     */
    // NOTE: there's a good reason we use tables here and that's because
    // nothing else works reliably.
    const NUMBERED_TEMPLATE = '
        <table>
            <tbody>
                <tr>
                    <td>
                        <pre class="line-numbers">
                            {line_numbers}
                        </pre>
                    </td>

                    <td class="code-container">
                        <pre class="code numbered"
                            data-startline="{start_line}"
                            data-highlightlines="{highlight_lines}"
                        >
                            {code}
                        </pre>
                    </td>
                </tr>
            </tbody>
        </table>';

    private static function stripTemplateWhitespaceCb($matches)
    {
        return ($matches[0][0] === '<') ? $matches[0] : '';
    }

    private static function stripTemplateWhitespace($string)
    {
        return preg_replace_callback('/\s+|<[^>]++>/', array('self', 'stripTemplateWhitespaceCb'), $string);
    }

    /**
     * Formats a string with a given set of values
     * The format syntax uses {xyz} as a placeholder, which will be
     * substituted from the 'xyz' key from $variables
     *
     * @param $template The template string
     * @param $variables An associative (keyed) array of values to be substituted
     * @param $strip_whitespace_from_template If @c TRUE, the template's whitespace is removed.
     *   This allows templates to be written to be easeier to read, without having to worry about
     *   the pre element inherting any unintended whitespace
     */
    public static function format($template, $variables, $stripWhitespaceFromTemplate = true)
    {
        if ($stripWhitespaceFromTemplate) {
            $template = self::stripTemplateWhitespace($template);
        }

        foreach ($variables as $search => $replace) {
            $template = str_replace("{" . $search . "}", $replace, $template);
        }
        return $template;
    }
}

/** @endcond */
