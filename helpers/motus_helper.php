<?php

/**
 * Compare a guess with the secret word and return one letter per position:
 *   R = red (right letter, right place)
 *   J = yellow (letter in the word, wrong place)
 *   B = blue (letter not in the word)
 *
 * A letter is never coloured more times than it appears in the secret word,
 * so red letters are counted first, then yellow ones with what is left.
 */
function computeGuessResult(string $secret, string $guess): string
{
    $length = strlen($secret);
    $result = array_fill(0, $length, 'B');
    $remaining = []; // secret letters not yet matched, with their count

    // 1st pass: right letter, right place
    for ($i = 0; $i < $length; $i++) {
        if ($guess[$i] === $secret[$i]) {
            $result[$i] = 'R';
        } else {
            $remaining[$secret[$i]] = ($remaining[$secret[$i]] ?? 0) + 1;
        }
    }

    // 2nd pass: letter present elsewhere, as long as some are left
    for ($i = 0; $i < $length; $i++) {
        if ($result[$i] === 'B' && ($remaining[$guess[$i]] ?? 0) > 0) {
            $result[$i] = 'J';
            $remaining[$guess[$i]]--;
        }
    }

    return implode('', $result);
}
