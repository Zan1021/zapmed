(function() {
    'use strict';

    // Get config from script tag
    var script = document.currentScript || document.querySelector('script[data-partner]');
    if (!script) return;

    var config = {
        partner: script.getAttribute('data-partner') || '',
        treatments: (script.getAttribute('data-treatments') || '').split(',').filter(Boolean),
        type: script.getAttribute('data-type') || 'button', // button, card, floating
        color: script.getAttribute('data-color') || '#059669',
        text: script.getAttribute('data-text') || 'Book a Consultation',
        position: script.getAttribute('data-position') || 'bottom-right', // for floating
        baseUrl: 'https://zapmed.co.za',
    };

    // Use current domain in dev
    if (window.location.hostname === 'localhost') {
        config.baseUrl = 'http://localhost:8000';
    }

    var refUrl = config.baseUrl + '/register?ref=' + encodeURIComponent(config.partner);
    if (config.treatments.length === 1) {
        refUrl = config.baseUrl + '/' + config.treatments[0] + '?ref=' + encodeURIComponent(config.partner);
    }

    // Inject styles
    var style = document.createElement('style');
    style.textContent = [
        '.zapmed-widget-btn {',
        '  display: inline-flex; align-items: center; gap: 8px;',
        '  background: ' + config.color + '; color: #fff;',
        '  padding: 12px 24px; border-radius: 10px;',
        '  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;',
        '  font-size: 14px; font-weight: 600;',
        '  text-decoration: none; border: none; cursor: pointer;',
        '  transition: transform 0.15s, box-shadow 0.15s;',
        '  box-shadow: 0 2px 8px rgba(0,0,0,0.15);',
        '}',
        '.zapmed-widget-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }',
        '.zapmed-widget-btn svg { width: 18px; height: 18px; }',
        '.zapmed-floating { position: fixed; z-index: 9999; }',
        '.zapmed-floating.bottom-right { bottom: 24px; right: 24px; }',
        '.zapmed-floating.bottom-left { bottom: 24px; left: 24px; }',
        '.zapmed-card {',
        '  background: #fff; border: 1px solid #e5e7eb; border-radius: 16px;',
        '  padding: 24px; max-width: 360px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);',
        '  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;',
        '}',
        '.zapmed-card-title { font-size: 16px; font-weight: 700; color: #111827; margin: 0 0 4px; }',
        '.zapmed-card-desc { font-size: 13px; color: #6b7280; margin: 0 0 16px; }',
        '.zapmed-card-features { list-style: none; padding: 0; margin: 0 0 16px; }',
        '.zapmed-card-features li { font-size: 12px; color: #4b5563; padding: 4px 0; display: flex; align-items: center; gap: 6px; }',
        '.zapmed-card-features li::before { content: "\\2713"; color: ' + config.color + '; font-weight: bold; }',
        '.zapmed-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; color: #6b7280; margin-top: 12px; }',
        '.zapmed-badge img { width: 14px; height: 14px; }',
    ].join('\n');
    document.head.appendChild(style);

    // SVG icon
    var icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>';

    // Find the target container
    var container = document.getElementById('zapmed-widget');

    if (config.type === 'floating') {
        // Floating button (bottom-right by default)
        var float = document.createElement('div');
        float.className = 'zapmed-floating ' + config.position;
        float.innerHTML = '<a href="' + refUrl + '" target="_blank" rel="noopener" class="zapmed-widget-btn">' + icon + config.text + '</a>';
        document.body.appendChild(float);

    } else if (config.type === 'card' && container) {
        // Rich card with features
        container.innerHTML = [
            '<div class="zapmed-card">',
            '  <div class="zapmed-card-title">Online Doctor Consultation</div>',
            '  <div class="zapmed-card-desc">See a licensed SA doctor from home. Get your prescription delivered.</div>',
            '  <ul class="zapmed-card-features">',
            '    <li>Licensed HPCSA doctors</li>',
            '    <li>Video, audio, or text consultation</li>',
            '    <li>E-prescription &amp; delivery</li>',
            '    <li>100% confidential</li>',
            '  </ul>',
            '  <a href="' + refUrl + '" target="_blank" rel="noopener" class="zapmed-widget-btn">' + icon + config.text + '</a>',
            '  <div class="zapmed-badge">Powered by Zapmed</div>',
            '</div>',
        ].join('');

    } else if (container) {
        // Simple button
        container.innerHTML = '<a href="' + refUrl + '" target="_blank" rel="noopener" class="zapmed-widget-btn">' + icon + config.text + '</a>';

    } else if (config.type === 'button') {
        // No container found, inject floating as fallback
        var fallback = document.createElement('div');
        fallback.className = 'zapmed-floating bottom-right';
        fallback.innerHTML = '<a href="' + refUrl + '" target="_blank" rel="noopener" class="zapmed-widget-btn">' + icon + config.text + '</a>';
        document.body.appendChild(fallback);
    }
})();
