@props(['label', 'type' => 'text', 'hint' => null])
@php $name = $attributes->wire('model')->value(); $err = $name ? $errors->first($name) : null; @endphp
<div {{ $attributes->only('class')->merge(['class' => 'field' . ($err ? ' invalid' : '')]) }}>
  <input type="{{ $type }}" placeholder=" " {{ $attributes->except('class') }}>
  <label>{{ $label }}</label>
  @if($err)<div class="err">{{ $err }}</div>@elseif($hint)<div class="hint">{{ $hint }}</div>@endif
</div>
