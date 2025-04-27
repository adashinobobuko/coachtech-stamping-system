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
            'clock_in' => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:255'],
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

            if ($clockIn && $clockOut && Carbon::parse($clockIn)->gt(Carbon::parse($clockOut))) {
                $validator->errors()->add('clock_in', '出勤時間は退勤時間より前でなければなりません。');
            }

            // 動的に休憩ペアチェック
            $breakStarts = $this->only(array_filter(array_keys($this->all()), fn($key) => str_starts_with($key, 'break_start_')));
            $breakEnds = $this->only(array_filter(array_keys($this->all()), fn($key) => str_starts_with($key, 'break_end_')));

            foreach ($breakStarts as $key => $start) {
                $index = str_replace('break_start_', '', $key);
                $end = $this->input('break_end_' . $index);

                $this->validateBreakPair($validator, $start, $end, $clockIn, $clockOut, $key);
            }
        });
    }

    private function validateBreakPair($validator, $start, $end, $clockIn, $clockOut, $fieldName)
    {
        if ($start && $end) {
            $startTime = Carbon::parse($start);
            $endTime = Carbon::parse($end);

            if ($startTime->gt($endTime)) {
                $validator->errors()->add($fieldName, '休憩開始は休憩終了より前でなければなりません。');
            }

            if ($clockIn && $startTime->lt(Carbon::parse($clockIn))) {
                $validator->errors()->add($fieldName, '休憩開始が出勤時間より前です。');
            }

            if ($clockOut && $endTime->gt(Carbon::parse($clockOut))) {
                $validator->errors()->add($fieldName, '休憩終了が退勤時間を超えています。');
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
            'clock_in.date_format' => '出勤時刻の形式が正しくありません（例：08:30）。',
            'clock_out.date_format' => '退勤時刻の形式が正しくありません（例：17:30）。',
            'note.string' => '備考は文字列で入力してください。',
            'note.max' => '備考は255文字以内で入力してください。',
            '*.date_format' => '時刻の形式が正しくありません（例：10:00）。',
        ];
    }
}
