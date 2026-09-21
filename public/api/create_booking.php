<?php
/**
 * /api/create_booking.php — utworzenie rezerwacji (POST, JSON).
 *
 * Zabezpieczenia:
 *  - wymagany token CSRF,
 *  - honeypot (ukryte pole 'website' musi być puste),
 *  - rate limiting per IP,
 *  - pełna walidacja backendowa,
 *  - ochrona przed podwójną rezerwacją (transakcja + UNIQUE) w create_booking().
 *
 * Sukces:  { ok:true, number, token, date, time, status }
 * Błąd:    { ok:false, error, code, errors:{pole:komunikat} }
 */

declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['ok' => false, 'error' => 'Metoda niedozwolona.'], 405);
}

// 1) CSRF
csrf_require(true);

// 2) Rate limiting
$maxPerHour = (int) config('security.booking_max_per_hour', 6);
if (rate_limit_exceeded('create_booking', $maxPerHour, 3600)) {
    app_log('booking', 'Rate limit rezerwacji z IP ' . client_ip());
    json_response(['ok' => false, 'error' => 'Zbyt wiele prób. Spróbuj ponownie za chwilę.', 'code' => 'rate_limited'], 429);
}

$input = request_input();

// 3) Honeypot — boty często wypełniają ukryte pola.
if (!empty($input['website'])) {
    app_log('booking', 'Honeypot wypełniony z IP ' . client_ip());
    // Udajemy sukces, aby nie ułatwiać botom analizy.
    json_response(['ok' => false, 'error' => 'Nie udało się wysłać formularza.', 'code' => 'invalid'], 400);
}

// 4) (Opcjonalnie) CAPTCHA — jeśli włączona w konfiguracji.
if (config('security.captcha.enabled')) {
    $captchaOk = verify_captcha($input['captcha_token'] ?? '');
    if (!$captchaOk) {
        json_response(['ok' => false, 'error' => 'Weryfikacja CAPTCHA nie powiodła się.', 'code' => 'captcha'], 400);
    }
}

// 5) Walidacja pól
$errors = [];
$fullName = v_full_name($input['full_name'] ?? '', $errors);
$phone    = v_phone($input['phone'] ?? '', $errors);
$email    = v_email_optional($input['email'] ?? '', $errors);
$devType  = v_device_type($input['device_type'] ?? '', $errors);
$problem  = v_problem($input['problem_description'] ?? '', $errors);
$manufacturer = v_clean_string($input['device_manufacturer'] ?? '', 100);
$model        = v_clean_string($input['device_model'] ?? '', 100);
$dateObj = v_date($input['date'] ?? '', $errors);
$time    = v_time($input['time'] ?? '', $errors);

// 6) Zgoda na politykę prywatności — wymagana.
$privacy = $input['privacy_accepted'] ?? false;
$privacyOk = ($privacy === true || $privacy === '1' || $privacy === 1 || $privacy === 'on' || $privacy === 'true');
if (!$privacyOk) {
    $errors['privacy_accepted'] = 'Wymagana jest akceptacja Polityki prywatności.';
}

if ($errors) {
    json_response(['ok' => false, 'error' => 'Formularz zawiera błędy.', 'code' => 'validation', 'errors' => $errors], 422);
}

// 7) Utworzenie rezerwacji (bezpieczne względem wyścigu).
$result = create_booking([
    'full_name'           => $fullName,
    'phone'               => $phone,
    'email'               => $email,
    'device_type'         => $devType,
    'device_manufacturer' => $manufacturer !== '' ? $manufacturer : null,
    'device_model'        => $model !== '' ? $model : null,
    'problem_description' => $problem,
    'date'                => $dateObj->format('Y-m-d'),
    'time'                => $time,
]);

if (!$result['ok']) {
    $status = $result['code'] === 'slot_taken' ? 409 : 400;
    json_response($result, $status);
}

// 8) E-maile (nie blokują procesu w razie błędu).
try {
    if ($result['customer_email']) {
        $tpl = mail_booking_received($result);
        Mailer::send($result['customer_email'], $result['customer_name'], $tpl['subject'], $tpl['html']);
    }
    $adminEmail = (string) config('mail.admin_email');
    if ($adminEmail && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $html = mail_layout('Nowe zgłoszenie', '<p>Wpłynęło nowe zgłoszenie nr <strong>' . e($result['number']) . '</strong> na termin ' . e($result['date']) . ' ' . e($result['time']) . '.</p>');
        Mailer::send($adminEmail, 'Administrator', 'Nowe zgłoszenie ' . $result['number'], $html);
    }
} catch (Throwable $e) {
    app_log('mail', 'Powiadomienie o rezerwacji: ' . $e->getMessage());
}

// 9) Zapisz dane potwierdzenia w sesji (dla booking-success.php) i zwróć JSON.
$_SESSION['last_booking'] = [
    'number' => $result['number'],
    'token'  => $result['token'],
    'date'   => $result['date'],
    'time'   => $result['time'],
    'status' => $result['status'],
];

json_response([
    'ok'     => true,
    'number' => $result['number'],
    'token'  => $result['token'],
    'date'   => $result['date'],
    'time'   => $result['time'],
    'status' => $result['status'],
    'redirect' => u('/booking-success.php'),
]);


/**
 * Weryfikacja CAPTCHA (reCAPTCHA v2/v3 lub hCaptcha) — używane tylko, gdy włączona.
 */
function verify_captcha(string $token): bool
{
    if ($token === '') {
        return false;
    }
    $provider = (string) config('security.captcha.provider', 'recaptcha');
    $secret = (string) config('security.captcha.secret_key');
    $url = $provider === 'hcaptcha'
        ? 'https://hcaptcha.com/siteverify'
        : 'https://www.google.com/recaptcha/api/siteverify';

    $post = http_build_query([
        'secret'   => $secret,
        'response' => $token,
        'remoteip' => client_ip(),
    ]);
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $post,
        'timeout' => 8,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return false;
    }
    $data = json_decode($raw, true);
    return is_array($data) && !empty($data['success']);
}
