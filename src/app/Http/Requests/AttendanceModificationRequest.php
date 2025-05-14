<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class AttendanceModificationRequest extends FormRequest
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
            'note' => ['required', 'string', 'max:255'],
        ];

        // 無限に休憩をチェックできるように動的に追加
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
            $baseDate = now()->toDateString();

            if ($clockIn && $clockOut) {
                $clockInTime = Carbon::parse("$baseDate $clockIn");
                $clockOutTime = Carbon::parse("$baseDate $clockOut");

                if ($clockInTime->gt($clockOutTime)) {
                    $validator->errors()->add('clock_in', '出勤時間もしくは退勤時間が不適切な値です。');
                }
            }

            $breakStarts = $this->only(array_filter(array_keys($this->all()), fn($key) => str_starts_with($key, 'break_start_')));

            foreach ($breakStarts as $key => $start) {
                $index = str_replace('break_start_', '', $key);
                $end = $this->input('break_end_' . $index);

                $this->validateBreakPair($validator, $start, $end, $clockIn, $clockOut, $index, $baseDate);
            }
        });
    }

    private function validateBreakPair($validator, $start, $end, $clockIn, $clockOut, $index, $baseDate)
    {
        if ($start && $end) {
            $startTime = Carbon::parse("$baseDate $start");
            $endTime = Carbon::parse("$baseDate $end");

            if ($startTime->gt($endTime)) {
                $validator->errors()->add('break_start_' . $index, '休憩時間が勤務時間外です。');
            }

            if ($clockIn && $startTime->lt(Carbon::parse("$baseDate $clockIn"))) {
                $validator->errors()->add('break_start_' . $index, '休憩時間が勤務時間外です。');
            }

            if ($clockOut && $endTime->gt(Carbon::parse("$baseDate $clockOut"))) {
                $validator->errors()->add('break_end_' . $index, '休憩時間が勤務時間外です。');
            }
        }
    }

    private function breakFields()
    {
        // リクエストに含まれる休憩フィールドだけ動的検出
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
