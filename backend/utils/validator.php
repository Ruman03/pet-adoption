<?php
class Validator {
    public static function validate($data, $rules) {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $ruleParts = explode('|', $ruleString);

            // If the field is not present in the data and not 'required', skip validation for this field
            if (!array_key_exists($field, $data) && !in_array('required', $ruleParts)) {
                continue;
            }

            foreach ($ruleParts as $rule) {
                $params = [];
                if (strpos($rule, ':') !== false) {
                    list($rule, $paramString) = explode(':', $rule, 2);
                    $params = explode(',', $paramString);
                }

                switch ($rule) {
                    case 'required':
                        if (empty($value)) {
                            $errors[$field][] = "The $field field is required.";
                        }
                        break;
                    case 'string':
                        if (!is_string($value)) {
                            $errors[$field][] = "The $field field must be a string.";
                        }
                        break;
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = "The $field field must be a valid email address.";
                        }
                        break;
                    case 'min':
                        if (strlen($value) < $params[0]) {
                            $errors[$field][] = "The $field field must be at least $params[0] characters.";
                        }
                        break;
                    case 'max':
                        if (strlen($value) > $params[0]) {
                            $errors[$field][] = "The $field field may not be greater than $params[0] characters.";
                        }
                        break;
                    case 'in':
                        if (!in_array($value, $params)) {
                            $allowed = implode(', ', $params);
                            $errors[$field][] = "The selected $field is invalid. Allowed values are: $allowed.";
                        }
                        break;
                    case 'integer':
                        if (!is_null($value) && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
                            $errors[$field][] = "The $field field must be an integer.";
                        }
                        break;
                    case 'date':
                        if (!is_null($value) && $value !== '' && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $value)) {
                            $errors[$field][] = "The $field field must be a valid date in YYYY-MM-DD format.";
                        }
                        break;
                    case 'datetime':
                        if (!self::validateDatetime($value)) {
                            $errors[$field][] = "The $field field must be a valid datetime.";
                        }
                        break;
                    case 'phone':
                        if (!self::validatePhone($value)) {
                            $errors[$field][] = "The $field field must be a valid phone number.";
                        }
                        break;
                    case 'array':
                        if (!self::validateArray($value)) {
                            $errors[$field][] = "The $field field must be an array.";
                        }
                        break;
                    case 'json':
                        if (!self::validateJson($value)) {
                            $errors[$field][] = "The $field field must be a valid JSON string.";
                        }
                        break;
                    case 'url':
                        if (!self::validateUrl($value)) {
                            $errors[$field][] = "The $field field must be a valid URL.";
                        }
                        break;
                    // Add more validation rules as needed (e.g., numeric, date, etc.)
                }
            }
        }
        return $errors;
    }

    private static function validateDatetime($value) {
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'];
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return true;
            }
        }
        return false;
    }

    private static function validatePhone($value) {
        // Basic phone validation - allows various formats
        return preg_match('/^[\+]?[1-9][\d]{0,15}$/', preg_replace('/[\s\-\(\)]/', '', $value));
    }

    private static function validateArray($value) {
        return is_array($value);
    }

    private static function validateJson($value) {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private static function validateUrl($value) {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
?>
