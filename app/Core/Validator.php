<?php
namespace App\Core;

class Validator {
    public static function required(array $data, array $fields): array {
        $errors = [];
        foreach ($fields as $f) {
            if (!isset($data[$f]) || trim((string)$data[$f]) === '') $errors[$f] = '必填';
        }
        return $errors;
    }

    public static function length(string $s, int $min, int $max): bool {
        $len = mb_strlen($s);
        return $len >= $min && $len <= $max;
    }

    public static function email(string $s): bool {
        return filter_var($s, FILTER_VALIDATE_EMAIL) !== false;
    }
}
?>