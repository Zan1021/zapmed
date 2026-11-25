# zapdev server notes (staging box 143.244.215.131, Forge server 1240557)

Written 2026-11-25 by Naz. Staging only.

## Why pages got slow after a few deploys (fixed)
- One PHP code cache (opcache, 128 MB) is shared by all 4 sites on the box (zapdev, husdev, mahalaadmin, spar). Every deploy lands in a NEW release folder, so the cache keeps the old release's files until PHP is reloaded. It filled up (0 MB free, 48% hit rate) and every request recompiled code: /health took 0.32 s on the server, 0.10 s after a reload.
- Fix in the app: `server:reload-php-on-release` (config `operations.server`, env `RELOAD_PHP_ON_RELEASE=true`, ON on zapdev) reloads PHP once per new release. It is run every minute by a forge crontab line: `* * * * * cd /home/forge/zapdev.co.za/current && /usr/bin/php8.2 artisan server:reload-php-on-release`. The forge user may reload php8.2-fpm without a password (sudoers). Marker file: `storage/framework/php-reloaded-for-release`.
- Better still (needs root, ask in Forge): raise `opcache.memory_consumption` to 256 and `opcache.max_accelerated_files` to 20000 in the PHP 8.2 FPM settings.
- The other sites on the box are not covered by this (they would need the same crontab line per site).
- Network: this PC to the server is about 0.28 s each way (the box is in Europe), so every new connection costs about 0.85 s before the page is even asked for. Hosting in Cape Town (AWS af-south-1 is planned) fixes that.

## FOUND: nothing runs the Laravel scheduler on zapdev
`crontab -l` for forge is empty (apart from the line above) and no supervisor daemons exist. So none of the scheduled jobs in `routes/console.php` have ever run on staging: coach / appointment reminders, `subscriptions:run-due` (09:30), `integrations:retry`, `checkouts:follow-up`, `coach:end-lapsed`, `authorisations:expire`, `followups:prompt`, nightly snapshots and scans. `schedule:list` only shows what is registered, not that it runs. To switch on: add `* * * * * cd /home/forge/zapdev.co.za/current && /usr/bin/php8.2 artisan schedule:run >> /dev/null 2>&1` (or Forge > Scheduler). Do it only when Captain Zan agrees: some jobs email or message test patients (all the new sending switches are OFF, the older appointment / prescription reminders are not).

## Missing doctor photo (fixed)
`User::avatar_url` returns null when the uploaded file is missing, so pages show the initials instead of a broken image.