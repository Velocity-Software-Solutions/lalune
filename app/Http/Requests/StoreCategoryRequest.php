<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                'name' => 'required|string|max:255',
            ];
        }

        if ($this->isMethod('put')) {
            $id = $this->route('id');
            return [
                'name_' . $id => 'required|string|max:255',
            ];
        }

        return [];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The category name is required.',
            'name.string' => 'The category name must be a valid string.',
            'name.max' => 'The category name must not exceed 255 characters.',

            // For update
            // We'll return a generic message for all dynamic name_* keys
            '*.required' => 'This field is required.',
            '*.string' => 'This field must be text.',
            '*.max' => 'This field cannot exceed 255 characters.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $id = $this->route('id');

        throw new HttpResponseException(
            redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('category_id', $id)
        );
    }
}