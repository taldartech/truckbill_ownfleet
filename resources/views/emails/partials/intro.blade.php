@php
    $c = config('mail-brand.colors');
    $highlight = $highlight ?? null;
    $hasHighlight = filled($highlight) && str_contains($title, $highlight);
    $image = $image ?? null;
    $hasBody = filled($text ?? null) || filled($buttonUrl ?? null);
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td class="tb-stack" valign="middle" style="padding:0 0 {{ $hasBody ? '28px' : '20px' }} 0;">
            @if (filled($greeting ?? null))
                <p style="margin:0 0 18px 0;font-size:17px;line-height:1.5;color:{{ $c['heading'] }};">{{ $greeting }}</p>
            @endif

            <h1 class="tb-title" style="margin:0 0 {{ $hasBody ? '14px' : '0' }} 0;font-size:30px;line-height:1.25;font-weight:800;color:{{ $c['heading'] }};">
                @if ($hasHighlight){{ \Illuminate\Support\Str::before($title, $highlight) }}<span style="color:{{ $highlightColor ?? $c['primary'] }};">{{ $highlight }}</span>{{ \Illuminate\Support\Str::after($title, $highlight) }}@else{{ $title }}@endif
            </h1>

            @if (filled($text ?? null))
                <p style="margin:0 0 {{ filled($buttonUrl ?? null) ? '26px' : '0' }} 0;font-size:16px;line-height:1.6;color:{{ $c['text'] }};">{{ $text }}</p>
            @endif

            @if (filled($buttonUrl ?? null))
                @include('emails.partials.button', ['url' => $buttonUrl, 'label' => $buttonLabel])
            @endif
        </td>
        @if ($image)
            <td class="tb-stack tb-hero-img" width="236" align="right" valign="middle" style="width:236px;padding:0 0 28px 16px;">
                <img src="{{ config('mail-brand.asset_url') }}/{{ $image }}" width="236" alt="{{ $imageAlt ?? '' }}" style="display:inline-block;width:236px;max-width:100%;height:auto;border:0;">
            </td>
        @endif
    </tr>
</table>
