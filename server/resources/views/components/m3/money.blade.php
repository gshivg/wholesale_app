@props(['v', 'paise' => false])
{{ \App\Support\Money::fmt($v, ! $paise) }}
