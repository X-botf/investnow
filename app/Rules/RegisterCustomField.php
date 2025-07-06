<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

use function getPageSetting;

class RegisterCustomField implements Rule
{
    private $isEdit;

    private $errorMessage;

    public function __construct($isEdit = false)
    {
        $this->isEdit = $isEdit;
    }

    public function passes($attribute, $value)
    {
        $regiCustomFields = json_decode(getPageSetting('register_custom_fields'), true);

        if ($regiCustomFields) {
            foreach ($regiCustomFields as $field) {
                $fieldName = $field['name'] ?? null;

                if ($field['validation'] == 'required' && ! $this->isEdit && ! isset($value[$fieldName])) {
                    $this->errorMessage = __('The :attribute field is required.', ['attribute' => $fieldName]);

                    return false;
                }

                if (in_array($field['type'], ['file', 'camera']) && isset($value[$fieldName])) {
                    $mimeType = $value[$fieldName]?->getMimeType();
                    if (! in_array($mimeType, ['image/jpg', 'image/jpeg', 'image/png', 'image/gif'])) {
                        $this->errorMessage = __('The :attribute field must be a file of type: jpg, jpeg, png, gif.', ['attribute' => $fieldName]);

                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function message()
    {
        return $this->errorMessage ?? 'The :attribute field is invalid.';
    }
}
