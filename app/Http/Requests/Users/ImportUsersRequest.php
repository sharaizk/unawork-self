<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

/** Administrator CSV import (UNA-012). Row contents are validated by CsvAccountImporter. */
class ImportUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdministrator();
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']];
    }
}
