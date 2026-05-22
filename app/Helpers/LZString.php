<?php

namespace App\Helpers;

/**
 * PHP port dari LZString untuk dekompresi response BPJS.
 * Ref: https://pieroxy.net/blog/pages/lz-string/index.html
 */
class LZString
{
    private static string $keyStrUriSafe = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-$';

    public static function decompressFromEncodedURIComponent(?string $input): ?string
    {
        if ($input === null || $input === '') {
            return '';
        }

        $input = str_replace(' ', '+', $input);

        return self::decompress(strlen($input), 32, function (int $index) use ($input) {
            return self::getBaseValue(self::$keyStrUriSafe, $input[$index] ?? '');
        });
    }

    private static function getBaseValue(string $alphabet, string $character): int
    {
        $pos = strpos($alphabet, $character);
        return $pos !== false ? $pos : 0;
    }

    private static function decompress(int $length, int $resetValue, callable $getNextValue): ?string
    {
        $dictionary = [];
        $enlargeIn = 4;
        $dictSize = 4;
        $numBits = 3;
        $result = [];
        $w = '';
        $entry = '';

        $data = [
            'val' => $getNextValue(0),
            'position' => $resetValue,
            'index' => 1,
        ];

        $bits = 0;
        $maxpower = pow(2, 2);
        $power = 1;

        while ($power != $maxpower) {
            $resb = $data['val'] & $data['position'];
            $data['position'] >>= 1;
            if ($data['position'] == 0) {
                $data['position'] = $resetValue;
                $data['val'] = $getNextValue($data['index']++);
            }
            $bits |= ($resb > 0 ? 1 : 0) * $power;
            $power <<= 1;
        }

        switch ($bits) {
            case 0:
                $bits = 0;
                $maxpower = pow(2, 8);
                $power = 1;
                while ($power != $maxpower) {
                    $resb = $data['val'] & $data['position'];
                    $data['position'] >>= 1;
                    if ($data['position'] == 0) {
                        $data['position'] = $resetValue;
                        $data['val'] = $getNextValue($data['index']++);
                    }
                    $bits |= ($resb > 0 ? 1 : 0) * $power;
                    $power <<= 1;
                }
                $c = chr($bits);
                break;
            case 1:
                $bits = 0;
                $maxpower = pow(2, 16);
                $power = 1;
                while ($power != $maxpower) {
                    $resb = $data['val'] & $data['position'];
                    $data['position'] >>= 1;
                    if ($data['position'] == 0) {
                        $data['position'] = $resetValue;
                        $data['val'] = $getNextValue($data['index']++);
                    }
                    $bits |= ($resb > 0 ? 1 : 0) * $power;
                    $power <<= 1;
                }
                $c = self::fromCharCode($bits);
                break;
            case 2:
                return '';
        }

        $dictionary[3] = $c;
        $w = $c;
        $result[] = $c;

        while (true) {
            if ($data['index'] > $length) {
                return '';
            }

            $bits = 0;
            $maxpower = pow(2, $numBits);
            $power = 1;
            while ($power != $maxpower) {
                $resb = $data['val'] & $data['position'];
                $data['position'] >>= 1;
                if ($data['position'] == 0) {
                    $data['position'] = $resetValue;
                    $data['val'] = $getNextValue($data['index']++);
                }
                $bits |= ($resb > 0 ? 1 : 0) * $power;
                $power <<= 1;
            }

            $c_val = $bits;
            switch ($c_val) {
                case 0:
                    $bits = 0;
                    $maxpower = pow(2, 8);
                    $power = 1;
                    while ($power != $maxpower) {
                        $resb = $data['val'] & $data['position'];
                        $data['position'] >>= 1;
                        if ($data['position'] == 0) {
                            $data['position'] = $resetValue;
                            $data['val'] = $getNextValue($data['index']++);
                        }
                        $bits |= ($resb > 0 ? 1 : 0) * $power;
                        $power <<= 1;
                    }
                    $dictionary[$dictSize++] = chr($bits);
                    $c_val = $dictSize - 1;
                    $enlargeIn--;
                    break;
                case 1:
                    $bits = 0;
                    $maxpower = pow(2, 16);
                    $power = 1;
                    while ($power != $maxpower) {
                        $resb = $data['val'] & $data['position'];
                        $data['position'] >>= 1;
                        if ($data['position'] == 0) {
                            $data['position'] = $resetValue;
                            $data['val'] = $getNextValue($data['index']++);
                        }
                        $bits |= ($resb > 0 ? 1 : 0) * $power;
                        $power <<= 1;
                    }
                    $dictionary[$dictSize++] = self::fromCharCode($bits);
                    $c_val = $dictSize - 1;
                    $enlargeIn--;
                    break;
                case 2:
                    return implode('', $result);
            }

            if ($enlargeIn == 0) {
                $enlargeIn = pow(2, $numBits);
                $numBits++;
            }

            if (isset($dictionary[$c_val])) {
                $entry = $dictionary[$c_val];
            } else {
                if ($c_val === $dictSize) {
                    $entry = $w . mb_substr($w, 0, 1);
                } else {
                    return null;
                }
            }

            $result[] = $entry;
            $dictionary[$dictSize++] = $w . mb_substr($entry, 0, 1);
            $enlargeIn--;

            if ($enlargeIn == 0) {
                $enlargeIn = pow(2, $numBits);
                $numBits++;
            }

            $w = $entry;
        }
    }

    private static function fromCharCode(int $code): string
    {
        return mb_chr($code, 'UTF-8');
    }
}
