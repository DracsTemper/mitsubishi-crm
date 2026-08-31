@php
$paths=['grid'=>'
<rect x="3" y="3" width="7" height="7" />
<rect x="14" y="3" width="7" height="7" />
<rect x="3" y="14" width="7" height="7" />
<rect x="14" y="14" width="7" height="7" />','users'=>'
<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
<circle cx="9" cy="7" r="4" />
<path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />','chat'=>'
<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />','book'=>'
<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V2H6.5A2.5 2.5 0 0 0 4 4.5z" />
<path d="M4 4.5v15" />','calendar'=>'
<rect x="3" y="4" width="18" height="17" rx="2" />
<path d="M16 2v4M8 2v4M3 10h18" />','car'=>'
<path d="m3 11 2-5h14l2 5M5 16h14M6 19v2M18 19v2" />
<rect x="2" y="11" width="20" height="7" rx="2" />','chart'=>'
<path d="M3 3v18h18M7 16l4-5 4 3 5-8" />','link'=>'
<path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7l1-1" />','settings'=>'
<circle cx="12" cy="12" r="3" />
<path d="M19.4 15a2 2 0 0 0 .3 1.8l.1.1-2.9 2.9-.1-.1a2 2 0 0 0-1.8-.3 2 2 0 0 0-1 1.5v.1h-4v-.1a2 2 0 0 0-1-1.5 2 2 0 0 0-1.8.3l-.1.1-2.9-2.9.1-.1a2 2 0 0 0 .3-1.8 2 2 0 0 0-1.5-1H3v-4h.1a2 2 0 0 0 1.5-1 2 2 0 0 0-.3-1.8l-.1-.1 2.9-2.9.1.1a2 2 0 0 0 1.8.3 2 2 0 0 0 1-1.5V3h4v.1a2 2 0 0 0 1 1.5 2 2 0 0 0 1.8-.3l.1-.1 2.9 2.9-.1.1a2 2 0 0 0-.3 1.8 2 2 0 0 0 1.5 1h.1v4h-.1a2 2 0 0 0-1.5 1z" />','logout'=>'
<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />','search'=>'
<circle cx="11" cy="11" r="8" />
<path d="m21 21-4.35-4.35" />','bell'=>'
<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0" />','menu'=>'
<path d="M3 12h18M3 6h18M3 18h18" />'];
$path=$paths[$name]??$paths['grid'];
@endphp
<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $path !!}</svg>