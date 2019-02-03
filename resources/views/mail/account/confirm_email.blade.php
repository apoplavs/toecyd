@extends('mail.account.wrapper')

@section('content')
	<b>Привіт {{ $data['name'] }}</b>,<br>
	Ви отримали цей лист, тому що ваша адреса електронної пошти була використана для реєстрації<br>
	в системі ТОЕсуд. Для завершення реєстрації підтвердіть Ваш email.
	<h4>
		<a href="{{ url('/confirm-email') }}?token={{ $data['remember_token'] }}">Підтвердити email</a>
	</h4>

@endsection