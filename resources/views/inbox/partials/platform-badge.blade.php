@php
    $config = [
        'facebook' => [
            'color' => 'primary',
            'icon' => 'facebook',
            'label' => 'Facebook Messenger',
        ],
        'instagram' => [
            'color' => 'danger',
            'icon' => 'instagram',
            'label' => 'Instagram DM',
        ],
        'whatsapp' => [
            'color' => 'success',
            'icon' => 'messageCircle',
            'label' => 'WhatsApp',
        ],
    ];

    $p = $config[$platform] ?? $config['facebook'];
@endphp

<span class="badge bg-{{ $p['color'] }}-soft text-{{ $p['color'] }} d-inline-flex align-items-center gap-1">
    <i data-feather="{{ $p['icon'] }}" class="wd-14 ht-14"></i>
    {{ $p['label'] }}
</span>
