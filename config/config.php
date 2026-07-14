<?php



require_once __DIR__ . '/env.php';

eventify_load_env_file();



/** Local timezone for schedules, “live now”, and date comparisons (Philippines). */

if (!defined('EVENTIFY_APP_TIMEZONE')) {

    define('EVENTIFY_APP_TIMEZONE', eventify_env('EVENTIFY_APP_TIMEZONE', 'Asia/Manila') ?? 'Asia/Manila');

}

if (function_exists('date_default_timezone_set')) {

    @date_default_timezone_set(EVENTIFY_APP_TIMEZONE);

}



if (!defined('BASE_URL')) {
    eventify_load_env_file();
    $baseUrl = null;
    if (array_key_exists('BASE_URL', $_ENV)) {
        $baseUrl = (string) $_ENV['BASE_URL'];
    } elseif (getenv('BASE_URL') !== false) {
        $baseUrl = (string) getenv('BASE_URL');
    }
    if ($baseUrl === null) {
        if (function_exists('eventify_resolve_base_url')) {
            $baseUrl = eventify_resolve_base_url();
        } else {
            $baseUrl = '';
            if (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '') {
                $docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
                $appRoot = rtrim(str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__)), '/');
                if ($docRoot !== '' && strpos($appRoot, $docRoot) === 0) {
                    $relative = substr($appRoot, strlen($docRoot));
                    $baseUrl = $relative === '' ? '' : $relative;
                }
            }
        }
    }
    define('BASE_URL', $baseUrl);
}



/** Require main-event RSVP before QR check-in (main and activity). */

if (!defined('EVENTIFY_CHECKIN_REQUIRE_RSVP')) {

    define('EVENTIFY_CHECKIN_REQUIRE_RSVP', true);

}



/** Require session RSVP before activity session check-in (when RSVP table exists). */

if (!defined('EVENTIFY_ACTIVITY_CHECKIN_REQUIRE_SESSION_RSVP')) {

    define('EVENTIFY_ACTIVITY_CHECKIN_REQUIRE_SESSION_RSVP', true);

}



/** When an event/activity has map coordinates, require live GPS within radius. Disabled for local testing. QR check-in stays enabled. */

if (!defined('EVENTIFY_CHECKIN_GEO_WHEN_PINNED')) {

    define('EVENTIFY_CHECKIN_GEO_WHEN_PINNED', false);

}



/** Meters from venue pin allowed for check-in. */

if (!defined('EVENTIFY_CHECKIN_GEO_RADIUS_M')) {

    define('EVENTIFY_CHECKIN_GEO_RADIUS_M', 300);

}



/** Minutes before activity start time when QR check-in opens. */

if (!defined('EVENTIFY_CHECKIN_EARLY_MINUTES')) {

    define('EVENTIFY_CHECKIN_EARLY_MINUTES', 15);

}



/** Ticket payment: simulate (demo), gcash_manual (reference + organizer confirm), both */

if (!defined('EVENTIFY_PAYMENT_MODE')) {

    define('EVENTIFY_PAYMENT_MODE', eventify_env('EVENTIFY_PAYMENT_MODE', 'both') ?? 'both');

}



if (!defined('EVENTIFY_SMS_PROVIDER')) {

    define('EVENTIFY_SMS_PROVIDER', eventify_env('EVENTIFY_SMS_PROVIDER', 'semaphore') ?? 'semaphore');

}



if (!defined('SEMAPHORE_API_KEY')) {

    define('SEMAPHORE_API_KEY', eventify_env('SEMAPHORE_API_KEY', '') ?? '');

}



if (!defined('SEMAPHORE_SENDER_NAME')) {

    define('SEMAPHORE_SENDER_NAME', eventify_env('SEMAPHORE_SENDER_NAME', '') ?? '');

}



$smtpLocalPath = __DIR__ . '/smtp.local.php';
if (is_readable($smtpLocalPath)) {
    require_once $smtpLocalPath;
}

/** PayMongo (GCash) credentials. Keep real keys in config/paymongo.local.php (gitignored). */
$paymongoLocalPath = __DIR__ . '/paymongo.local.php';
if (is_readable($paymongoLocalPath)) {
    require_once $paymongoLocalPath;
}

if (!defined('EVENTIFY_PAYMONGO_SECRET_KEY')) {
    define('EVENTIFY_PAYMONGO_SECRET_KEY', eventify_env('EVENTIFY_PAYMONGO_SECRET_KEY', '') ?? '');
}

if (!defined('EVENTIFY_PAYMONGO_PUBLIC_KEY')) {
    define('EVENTIFY_PAYMONGO_PUBLIC_KEY', eventify_env('EVENTIFY_PAYMONGO_PUBLIC_KEY', '') ?? '');
}

/** Minimum order total (pesos) for PayMongo GCash online. 0 = no EVENTIFY-side minimum (PayMongo may still reject very small GCash amounts). */
if (!defined('EVENTIFY_PAYMONGO_GCASH_MIN_PESO')) {
    define('EVENTIFY_PAYMONGO_GCASH_MIN_PESO', 0);
}

if (!defined('EVENTIFY_SMTP_HOST')) {
    define('EVENTIFY_SMTP_HOST', eventify_env('EVENTIFY_SMTP_HOST', 'smtp.gmail.com') ?? 'smtp.gmail.com');
}

if (!defined('EVENTIFY_SMTP_PORT')) {
    define('EVENTIFY_SMTP_PORT', (int) (eventify_env('EVENTIFY_SMTP_PORT', '587') ?? '587'));
}

if (!defined('EVENTIFY_SMTP_USERNAME')) {
    define('EVENTIFY_SMTP_USERNAME', eventify_env('EVENTIFY_SMTP_USERNAME', '') ?? '');
}

if (!defined('EVENTIFY_SMTP_PASSWORD')) {
    define('EVENTIFY_SMTP_PASSWORD', eventify_env('EVENTIFY_SMTP_PASSWORD', '') ?? '');
}

if (!defined('EVENTIFY_SMTP_FROM_EMAIL')) {
    define('EVENTIFY_SMTP_FROM_EMAIL', eventify_env('EVENTIFY_SMTP_FROM_EMAIL', '') ?? '');
}

if (!defined('EVENTIFY_SMTP_FROM_NAME')) {
    define('EVENTIFY_SMTP_FROM_NAME', eventify_env('EVENTIFY_SMTP_FROM_NAME', 'EVENTIFY') ?? 'EVENTIFY');
}

if (!defined('EVENTIFY_SMTP_ALLOW_INSECURE_TLS')) {
    $insecureTls = strtolower(eventify_env('EVENTIFY_SMTP_ALLOW_INSECURE_TLS', 'false') ?? 'false');
    define('EVENTIFY_SMTP_ALLOW_INSECURE_TLS', in_array($insecureTls, ['1', 'true', 'yes'], true));
}

if (!defined('EVENTIFY_SMTP_NO_AUTH')) {
    $noAuth = strtolower(eventify_env('EVENTIFY_SMTP_NO_AUTH', 'false') ?? 'false');
    define('EVENTIFY_SMTP_NO_AUTH', in_array($noAuth, ['1', 'true', 'yes'], true));
}

if (!defined('EVENTIFY_SMTP_DISABLE_TLS')) {
    $disableTls = strtolower(eventify_env('EVENTIFY_SMTP_DISABLE_TLS', 'false') ?? 'false');
    define('EVENTIFY_SMTP_DISABLE_TLS', in_array($disableTls, ['1', 'true', 'yes'], true));
}

if (!defined('EVENTIFY_SMTP_EHLO_HOST')) {
    define('EVENTIFY_SMTP_EHLO_HOST', eventify_env('EVENTIFY_SMTP_EHLO_HOST', '') ?? '');
}

if (!defined('EVENTIFY_SMTP_FALLBACK_MAIL')) {
    $fallbackMail = strtolower(eventify_env('EVENTIFY_SMTP_FALLBACK_MAIL', 'true') ?? 'true');
    define('EVENTIFY_SMTP_FALLBACK_MAIL', in_array($fallbackMail, ['1', 'true', 'yes'], true));
}

if (!defined('EVENTIFY_SMTP_FORCE_MAIL')) {
    $forceMail = strtolower(eventify_env('EVENTIFY_SMTP_FORCE_MAIL', 'false') ?? 'false');
    define('EVENTIFY_SMTP_FORCE_MAIL', in_array($forceMail, ['1', 'true', 'yes'], true));
}

if (!defined('EVENTIFY_CRON_KEY')) {
    define('EVENTIFY_CRON_KEY', eventify_env('EVENTIFY_CRON_KEY', '') ?? '');
}



$error = $error ?? '';

$success = $success ?? '';

?>

