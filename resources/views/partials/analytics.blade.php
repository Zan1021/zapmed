@if(config('analytics.measurement_id'))
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={{ config('analytics.measurement_id') }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ config('analytics.measurement_id') }}', {
        'anonymize_ip': true,
        'cookie_flags': 'SameSite=None;Secure'
    });

    // Only fire after cookie consent
    if (document.cookie.indexOf('zapmed_consent=accepted') === -1) {
        window['ga-disable-{{ config('analytics.measurement_id') }}'] = true;
    }
</script>
@endif
