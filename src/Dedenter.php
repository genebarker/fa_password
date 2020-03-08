<?php

namespace madman\Password;

class Dedenter
{
    public static function dedent($string)
    {
        $block_margin = self::calculateBlockMargin($string);
        return self::stripBlockMargin($block_margin, $string);
    }

    public static function calculateBlockMargin($string)
    {
        $block_margin = null;
        $line_arr = explode("\n", $string);
        foreach ($line_arr as $line_index => $line) {
            if (!self::lineIsMarginable($line_index, $line)) {
                continue;
            }
            $line_margin = strlen($line) - strlen(ltrim($line));
            if (!isset($block_margin) || $line_margin < $block_margin) {
                $block_margin = $line_margin;
            }
        }
        return isset($block_margin) ? $block_margin : 0;
    }

    public static function lineIsMarginable($line_index, $line)
    {
        return ($line_index > 0 && trim($line) != '');
    }

    public static function stripBlockMargin($margin, $string)
    {
        $line_arr = explode("\n", $string);
        $last_line_index = count($line_arr) - 1;
        if ($last_line_index == 0 || $margin == 0) {
            return $string;
        }
        $stripped_block = '';
        foreach ($line_arr as $line_index => $line) {
            if (self::lineIsMarginable($line_index, $line)) {
                $line_text = rtrim(substr($line, $margin));
            } else {
                $line_text = rtrim($line);
            }
            $line_end = $line_index < $last_line_index ? "\n" : '';
            $stripped_block .= $line_text . $line_end;
        }
        return $stripped_block;
    }
}
