<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class AttendanceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'clock_in' => ['required', 'date_format:H:i'],
            'clock_out' => ['required', 'date_format:H:i'],
            'note' => ['required', 'string'],
            'is_correction' => ['required', 'boolean'],
        ];

        // 休憩のフィールドも動的に検出して追加
        foreach ($this->breakFields() as $field) {
            $rules[$field] = ['nullable', 'date_format:H:i'];
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $clockIn = $this->input('clock_in');
            $clockOut = $this->input('clock_out');

            if ($clockIn && $clockOut && Carbon::parse($clockIn)->gt(Carbon::parse($clockOut))) {
                $validator->errors()->add('clock_in', '出勤時間もしくは退勤時間が不適切な値です。');
            }

            $breakStarts = $this->only(array_filter(array_keys($this->all()), fn($key) => str_starts_with($key, 'break_start_')));
            
            foreach ($breakStarts as $key => $start) {
                $index = str_replace('break_start_', '', $key);
                $end = $this->input('break_end_' . $index);

                // ★ここ追加
                if ($start && $end && Carbon::parse($start)->gt(Carbon::parse($end))) {
                    $validator->errors()->add($key,  '休憩時間が勤務時間外です。');
                }

                $this->validateBreakTime($validator, $start, $end, $clockIn, $clockOut, $key);
            }
        });
    }

    private function validateBreakTime($validator, $start, $end, $clockIn, $clockOut, $field)
    {
        if ($start && $clockIn && Carbon::parse($start)->lt(Carbon::parse($clockIn))) {
            $validator->errors()->add($field, '休憩時間が勤務時間外です。');
        }

        if ($end && $clockOut && Carbon::parse($end)->gt(Carbon::parse($clockOut))) {
            $validator->errors()->add($field, '休憩時間が勤務時間外です。');
        }
    }

    private function breakFields()
    {
        return array_filter(array_keys($this->all()), function ($key) {
            return str_starts_with($key, 'break_start_') || str_starts_with($key, 'break_end_');
        });
    }

    public function messages(): array
    {
        return [
            'note.required' => '備考を記入してください。',
        ];
    }
}
