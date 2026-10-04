@props(['label', 'hint' => null])
@php $name = $attributes->wire('model')->value(); $err = $name ? $errors->first($name) : null; @endphp
<div {{ $attributes->only('class')->merge(['class' => 'field' . ($err ? ' invalid' : '')]) }}>
  <select {{ $attributes->except('class') }}>{{ $slot }}</select>
  <label>{{ $label }}</label>
  @if($err)<div class="err">{{ $err }}</div>@elseif($hint)<div class="hint">{{ $hint }}</div>@endif
</div>
