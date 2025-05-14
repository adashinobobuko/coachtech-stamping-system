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
            $baseDate = Carbon::today(); // ← ここで日付を固定

            $clockIn = $this->input('clock_in');
            $clockOut = $this->input('clock_out');

            $clockInTime = $clockIn ? Carbon::parse($baseDate->format('Y-m-d') . ' ' . $clockIn) : null;
            $clockOutTime = $clockOut ? Carbon::parse($baseDate->format('Y-m-d') . ' ' . $clockOut) : null;

            if ($clockInTime && $clockOutTime && $clockInTime->gt($clockOutTime)) {
                $validator->errors()->add('clock_in', '出勤時間もしくは退勤時間が不適切な値です。');
            }

            $breakStarts = $this->only(array_filter(array_keys($this->all()), fn($key) => str_starts_with($key, 'break_start_')));

            foreach ($breakStarts as $key => $start) {
                $index = str_replace('break_start_', '', $key);
                $end = $this->input('break_end_' . $index);

                $startTime = $start ? Carbon::parse($baseDate->format('Y-m-d') . ' ' . $start) : null;
                $endTime = $end ? Carbon::parse($baseDate->format('Y-m-d') . ' ' . $end) : null;

                if ($startTime && $endTime && $startTime->gt($endTime)) {
                    $validator->errors()->add($key, '休憩時間が勤務時間外です。');
                }

                $this->validateBreakTime(
                    $validator,
                    $startTime,
                    $endTime,
                    $clockInTime,
                    $clockOutTime,
                    'break_start_' . $index,
                    'break_end_' . $index
                );

            }
        });
    }

    private function validateBreakTime($validator, $startTime, $endTime, $clockInTime, $clockOutTime, $startField, $endField)
    {
        if ($startTime && $clockInTime && $startTime->lt($clockInTime)) {
            $validator->errors()->add($startField, '休憩時間が勤務時間外です。');
        }

        if ($startTime && $clockOutTime && $startTime->gt($clockOutTime)) {
            $validator->errors()->add($startField, '休憩時間が勤務時間外です。');
        }

        if ($endTime && $clockInTime && $endTime->lt($clockInTime)) {
            $validator->errors()->add($endField, '休憩時間が勤務時間外です。');
        }

        if ($endTime && $clockOutTime && $endTime->gt($clockOutTime)) {
            $validator->errors()->add($endField, '休憩時間が勤務時間外です。');
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
