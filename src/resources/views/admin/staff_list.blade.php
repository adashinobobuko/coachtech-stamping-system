@extends('admin.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/staff_list.css') }}?v={{ time() }}">
@endsection

@section('content')
<div class="container mx-auto px-4 py-6">
    <h2 class="text-xl font-bold mb-6">スタッフ一覧</h2>

    <table class="w-full border border-gray-300 text-center rounded overflow-hidden">
        <thead class="bg-gray-100">
            <tr>
                <th class="border px-4 py-2">名前</th>
                <th class="border px-4 py-2">メールアドレス</th>
                <th class="border px-4 py-2">月次勤怠</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr class="border">
                <td class="border px-4 py-2">{{ $user->name }}</td>
                <td class="border px-4 py-2">{{ $user->email }}</td>
                <td class="border px-4 py-2">
                    <a href="{{ route('admin.staff.attendance', ['id' => $user->id]) }}"
                       class="text-blue-600 hover:underline">詳細</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
