<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * The only HTML that can be saved: bold, italic, underline, links, ordered and
 * unordered lists, a horizontal rule, paragraphs, line breaks and two heading levels. Everything else (images,
 * scripts, styles, event handlers, javascript: links) is removed on the way in,
 * so what's stored is safe to show as it is.
 */
final class RichText
{
    private static ?HTMLPurifier $purifier = null;

    /** Cleaned HTML, or null when nothing is left */
    public static function clean(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        // Browsers make a <div> for each line: treat them as paragraphs. There are two heading
        // levels: the page's own title is the biggest, so h1 and h2 become a heading, the rest a subheading.
        $html = preg_replace(['#<(/?)div\b#i', '#<(/?)h[12]\b#i', '#<(/?)h[3-6]\b#i'], ['<$1p', '<$1h2', '<$1h3'], $html);
        $clean = trim(self::purifier()->purify($html));

        // Pressing Enter twice leaves empty lines, and empty bullets, holding only a <br>
        do {
            $before = $clean;
            $clean = preg_replace([
                '#<(b|strong|i|em|u)>\s*(?:<br\s*/?>\s*)*</\1>#i',
                '#<(p|li|h2|h3)>\s*(?:<br\s*/?>\s*)*</\1>#i',
                '#<(ul|ol)>\s*</\1>#i',
            ], '', $clean);
        } while ($clean !== $before);
        $clean = trim($clean);

        return $clean === '' ? null : $clean;
    }

    public static function hasContent(?string $html): bool
    {
        return self::clean($html) !== null;
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null); // short text, so no cache folder is needed
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,a[href],ol,ul,li,hr,h2,h3');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('HTML.TargetBlank', true);   // links open in a new tab, safely
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.AutoParagraph', true);

        return self::$purifier = new HTMLPurifier($config);
    }
}
