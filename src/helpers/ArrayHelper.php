<?php

namespace thejoshsmith\craftcommercemultivendor\helpers;

/**
 * @author    nystudio107
 * @package   Seomatic
 * @since     3.0.0
 */
class ArrayHelper extends \craft\helpers\ArrayHelper
{
    /**
     * Blatantly stolen from https://stackoverflow.com/questions/16585502/array-splice-preserving-keys#answer-16591537
     */
    public static function array_splice_assoc(&$input, $offset, $length, $replacement = array()) {
        $replacement = (array) $replacement;
        $key_indices = array_flip(array_keys($input));
        if (isset($input[$offset]) && is_string($offset)) {
                $offset = $key_indices[$offset];
        }
        if (isset($input[$length]) && is_string($length)) {
                $length = $key_indices[$length] - $offset;
        }
        $input = array_slice($input, 0, $offset, TRUE)
                + $replacement
                + array_slice($input, $offset + $length, NULL, TRUE); 
    }
}
