{{-- Back arrow: returns to the last screen the member came from, else to $fallback (build guide: Back keeps history). --}}
<a href="{{ $fallback }}" class="zc-back" aria-label="Back"
   onclick="if (history.length > 1 && document.referrer.indexOf(location.origin) === 0) { history.back(); return false; }">&#8592;</a>