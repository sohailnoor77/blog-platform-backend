<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class IsStringOrImage implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            true;
        }

        $this->validateMimes($attribute, $value, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp']);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must be a string or an image.';
    }

    /**
     * Validate the guessed extension of a file upload is in a set of file extensions.
     *
     * @param string $attribute
     * @param  mixed  $value
     * @param array $parameters
     * @return bool
     */
    public function validateMimes(string $attribute, $value, array $parameters): bool
    {
        if (!$this->isValidFileInstance($value)) {
            return false;
        }

        if ($this->shouldBlockPhpUpload($value, $parameters)) {
            return false;
        }

        if (in_array('jpg', $parameters, true) || in_array('jpeg', $parameters, true)) {
            $parameters = array_unique(array_merge($parameters, ['jpg', 'jpeg']));
        }

        return $value->getPath() !== '' && in_array($value->guessExtension(), $parameters, true);
    }

    /**
     * Check that the given value is a valid file instance.
     *
     * @param  mixed  $value
     * @return bool
     */
    public function isValidFileInstance($value): bool
    {
        if ($value instanceof UploadedFile && !$value->isValid()) {
            return false;
        }

        return $value instanceof File;
    }

    /**
     * Check if PHP uploads are explicitly allowed.
     *
     * @param  mixed  $value
     * @param array $parameters
     * @return bool
     */
    protected function shouldBlockPhpUpload($value, array $parameters): bool
    {
        if (in_array('php', $parameters, true)) {
            return false;
        }

        $phpExtensions = [
            'php',
            'php3',
            'php4',
            'php5',
            'phtml',
            'phar',
        ];

        return ($value instanceof UploadedFile)
            ? in_array(strtolower(trim($value->getClientOriginalExtension())), $phpExtensions)
            : in_array(strtolower(trim($value->getExtension())), $phpExtensions);
    }
}