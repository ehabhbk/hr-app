<?php

namespace App\Services;

class ArabicPdfService
{
    /**
     * Fix the Arabic "عبدالله" rendering bug in TCPDF.
     *
     * TCPDF's shaping algorithm collapses the sequence "الله" (alef-lam-lam-heh,
     * U+0627 U+0644 U+0644 U+0647) into the single Allah ligature glyph U+FDF2.
     * The bundled "dejavusans" font does NOT contain that glyph, so the "لله"
     * portion renders as a missing box (e.g. "عبداا" instead of "عبدالله").
     *
     * Inserting a ZWNJ (U+200C) prevents the collapse but leaves a visible gap,
     * which is ugly. Instead we pre-shape the affected letters into their
     * proper Arabic presentation forms (all of which exist in dejavusans), so
     * "الله" renders correctly joined with no gap and no missing glyph.
     */
    public static function fixAllah(string $text): string
    {
        // "الله" = ا(0627) ل(0644) ل(0644) ه(0647)
        // -> ا-final(FEDD) ل-initial(FEDF) ل-medial(FEE0) ه-final(FEEA)
        static $alllahForms = null;
        if ($alllahForms === null) {
            $alllahForms = self::str(array(0xFEDD, 0xFEDF, 0xFEE0, 0xFEEA));
        }

        // "اللّه" (with shadda) = ا(0627) ل(0644) ل(0644) ّ(0651) ه(0647)
        static $alllahShForms = null;
        if ($alllahShForms === null) {
            $alllahShForms = self::str(array(0xFEDD, 0xFEDF, 0xFEE0, 0x0651, 0xFEEA));
        }

        // raw UTF-8 for the base sequences
        $plain = "\xD8\xA7\xD9\x84\xD9\x84\xD9\x87";   // الله
        $shadda = "\xD8\xA7\xD9\x84\xD9\x84\xD9\x91\xD9\x87"; // اللّه

        $text = str_replace($shadda, $alllahShForms, $text);
        $text = str_replace($plain, $alllahForms, $text);

        return $text;
    }

    private static function str(array $codepoints): string
    {
        $out = '';
        foreach ($codepoints as $cp) {
            $out .= mb_chr($cp, 'UTF-8');
        }
        return $out;
    }
}
