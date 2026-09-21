<?php
/** Wspólna stopka stron publicznych. */
$service = (string) config('app.name');
$phone = (string) config('contact.phone');
$email = (string) config('contact.email');
$area = (string) config('contact.service_area');
$year = date('Y');
?>
</main>
<footer class="site-footer" id="kontakt">
    <div class="container footer-grid">
        <div class="footer-col">
            <div class="brand footer-brand">
                <span class="brand-mark" aria-hidden="true">DŁ</span>
                <span class="brand-text">
                    <span class="brand-name"><?= e((string) config('app.owner')) ?></span>
                    <span class="brand-tag"><?= e((string) config('app.tagline')) ?></span>
                </span>
            </div>
            <p class="footer-note">Przyjęcie sprzętu po wcześniejszym umówieniu terminu.</p>
        </div>

        <div class="footer-col">
            <h3>Kontakt</h3>
            <ul class="footer-list">
                <li><span class="muted">Telefon:</span> <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a></li>
                <li><span class="muted">E-mail:</span> <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
                <li><span class="muted">Obszar działania:</span> <?= e($area) ?></li>
            </ul>
        </div>

        <div class="footer-col">
            <h3>Informacje</h3>
            <ul class="footer-list">
                <li><a href="/booking.php">Umów dostarczenie sprzętu</a></li>
                <li><a href="/status.php">Sprawdź status zlecenia</a></li>
                <li><a href="/privacy.php">Polityka prywatności</a></li>
                <li><a href="/terms.php">Regulamin</a></li>
            </ul>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>&copy; <?= e($year) ?> <?= e($service) ?>. Wszelkie prawa zastrzeżone.</p>
    </div>
</footer>
<script src="/assets/js/main.js"></script>
<?php if (!empty($pageScripts)) foreach ($pageScripts as $s): ?>
<script src="<?= e($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
