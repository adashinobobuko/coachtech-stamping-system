<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'clock_in' => ['required', 'date_format:H:i'],
            'clock_out' => ['required', 'date_format:H:i', 'after_or_equal:clock_in'],
            'break_start_1' => ['nullable', 'date_format:H:i'],
            'break_end_1' => ['nullable', 'date_format:H:i', 'after_or_equal:break_start_1'],
            'break_start_2' => ['nullable', 'date_format:H:i'],
            'break_end_2' => ['nullable', 'date_format:H:i', 'after_or_equal:break_start_2'],
            'note' => ['nullable', 'string'],
            'is_correction' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'clock_in.required' => '出勤時刻を入力してください。',
            'clock_in.date_format' => '出勤時刻の形式が正しくありません。',
            'clock_out.required' => '退勤時刻を入力してください。',
            'clock_out.date_format' => '退勤時刻の形式が正しくありません。',
            'clock_out.after_or_equal' => '退勤時刻は出勤時刻と同じか、それより後にしてください。',
            'break_start_1.date_format' => '休憩1の開始時刻の形式が正しくありません。',
            'break_end_1.date_format' => '休憩1の終了時刻の形式が正しくありません。',
            'break_end_1.after_or_equal' => '休憩1の終了は開始時刻より後にしてください。',
            'break_start_2.date_format' => '休憩2の開始時刻の形式が正しくありません。',
            'break_end_2.date_format' => '休憩2の終了時刻の形式が正しくありません。',
            'break_end_2.after_or_equal' => '休憩2の終了は開始時刻より後にしてください。',
            'is_correction.required' => '修正申請かどうかの情報が不足しています。',
            'is_correction.boolean' => '修正申請の形式が不正です。',
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
            $isCorrection = $this->input('is_correction');
            $note = $this->input('note');

            if (strtotime($clockIn) > strtotime($clockOut)) {
                $validator->errors()->add('clock_in', '出勤時刻は退勤時刻より早くなければなりません。');
            }

            if ($isCorrection && empty($note)) {
                $validator->errors()->add('note', '修正申請の場合は備考を記入してください。');
            }

            if (($breakStart1 && strtotime($breakStart1) < strtotime($clockIn)) ||
                ($breakEnd1 && strtotime($breakEnd1) > strtotime($clockOut))) {
                $validator->errors()->add('break_start_1', '休憩1は勤務時間内に設定してください。');
            }

            if (($breakStart2 && strtotime($breakStart2) < strtotime($clockIn)) ||
                ($breakEnd2 && strtotime($breakEnd2) > strtotime($clockOut))) {
                $validator->errors()->add('break_start_2', '休憩2は勤務時間内に設定してください。');
            }
        });
    }

    public function applications()
    {
        return $this->hasMany(AttendanceApplication::class, 'attendance_id');
    }

}
