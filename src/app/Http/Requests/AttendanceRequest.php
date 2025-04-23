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
        return [
            'clock_in' => ['required', 'date_format:H:i'],
            'clock_out' => ['required', 'date_format:H:i'],
            'break_start_1' => ['nullable', 'date_format:H:i'],
            'break_end_1' => ['nullable', 'date_format:H:i'],
            'break_start_2' => ['nullable', 'date_format:H:i'],
            'break_end_2' => ['nullable', 'date_format:H:i'],
            'note' => ['required_if:is_correction,true', 'string'],
            'is_correction' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required_if' => '備考を記入してください。',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $clockIn = $this->input('clock_in');
            $clockOut = $this->input('clock_out');

            $breakStart1 = $this->input('break_start_1');
            $breakEnd1 = $this->input('break_end_1');
            $breakStart2 = $this->input('break_start_2');
            $breakEnd2 = $this->input('break_end_2');

            if ($clockIn && $clockOut && Carbon::parse($clockIn)->gt(Carbon::parse($clockOut))) {
                $validator->errors()->add('clock_in', '出勤時間もしくは退勤時間が不適切な値です。');
            }

            $this->validateBreakTime($validator, $breakStart1, $breakEnd1, $clockIn, $clockOut, 'break_start_1');
            $this->validateBreakTime($validator, $breakStart2, $breakEnd2, $clockIn, $clockOut, 'break_start_2');
        });
    }

    private function validateBreakTime($validator, $start, $end, $clockIn, $clockOut, $field)
    {
        if ($start && Carbon::parse($start)->lt(Carbon::parse($clockIn))) {
            $validator->errors()->add($field, '休憩時間が勤務時間外です。');
        }

        if ($end && Carbon::parse($end)->gt(Carbon::parse($clockOut))) {
            $validator->errors()->add($field, '休憩時間が勤務時間外です。');
        }
    }
}
