{{-- Decorative only: ruled lines, concentric arcs and a dot grid in currentColor (follows the panel's accent). --}}
<svg {{ $attributes }} viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" fill="none" aria-hidden="true" focusable="false">
    <defs>
        <pattern id="sentinel-art-dots" width="28" height="28" patternUnits="userSpaceOnUse">
            <circle cx="2" cy="2" r="1.4" fill="currentColor" fill-opacity="0.16"/>
        </pattern>
        <linearGradient id="sentinel-art-fade" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="white" stop-opacity="0"/>
            <stop offset="0.45" stop-color="white" stop-opacity="1"/>
            <stop offset="1" stop-color="white" stop-opacity="0"/>
        </linearGradient>
        <mask id="sentinel-art-mask">
            <rect width="800" height="900" fill="url(#sentinel-art-fade)"/>
        </mask>
    </defs>
    <rect x="470" y="60" width="300" height="300" fill="url(#sentinel-art-dots)"/>
    <g mask="url(#sentinel-art-mask)" stroke="currentColor" stroke-opacity="0.08">
        @for ($line = 0; $line < 30; $line++)
            <line x1="0" x2="800" y1="{{ 30 + $line * 30 }}" y2="{{ 30 + $line * 30 }}"/>
        @endfor
        <line x1="96" x2="96" y1="0" y2="900" stroke-opacity="0.14"/>
    </g>
    <g stroke="currentColor" transform="translate(640 760)">
        <circle r="120" stroke-opacity="0.18"/>
        <circle r="200" stroke-opacity="0.13"/>
        <circle r="290" stroke-opacity="0.09"/>
        <circle r="390" stroke-opacity="0.06"/>
        <circle r="120" stroke-opacity="0.5" stroke-width="2" stroke-dasharray="80 674" stroke-linecap="round" transform="rotate(-150)"/>
    </g>
    <g fill="currentColor">
        <circle cx="520" cy="640" r="5" fill-opacity="0.55"/>
        <circle cx="756" cy="452" r="3.5" fill-opacity="0.4"/>
        <circle cx="352" cy="784" r="3" fill-opacity="0.35"/>
    </g>
</svg>
