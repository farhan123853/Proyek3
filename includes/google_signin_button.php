<?php
/**
 * Google Sign-In Button Component
 * Villa Joglo Masayu
 */

$googleClientId = trim($config['GOOGLE_CLIENT_ID'] ?? '');

if ($googleClientId === '') {
    return;
}

// Nonce unik untuk Google ID Token.
$googleNonce = google_auth_create_nonce();

// CSRF token untuk POST ke backend PHP.
$csrfToken = csrf_token();

$googleEndpoint = BASE_URL . 'google-auth.php';

$googleConfig = [
    'clientId' => $googleClientId,
    'nonce' => $googleNonce,
    'csrfToken' => $csrfToken,
    'endpoint' => $googleEndpoint,
];
?>

<div style="margin-top: 1.5rem;">

    <div style="
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 1.25rem;
        color: var(--wood);
        font-size: 13px;
    ">
        <span style="flex: 1; height: 1px; background: var(--border-color, #ddd);"></span>
        <span>ATAU</span>
        <span style="flex: 1; height: 1px; background: var(--border-color, #ddd);"></span>
    </div>

    <div
        id="google-signin-button"
        style="
            width: 100%;
            display: flex;
            justify-content: center;
        "
    ></div>

    <div
        id="google-signin-error"
        class="alert alert-danger"
        role="alert"
        aria-live="polite"
        hidden
        style="margin-top: 1rem;"
    ></div>

</div>

<script>
(function () {
    'use strict';

    const config = <?= json_encode(
        $googleConfig,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_AMP |
        JSON_HEX_QUOT
    ) ?>;

    const buttonContainer = document.getElementById(
        'google-signin-button'
    );

    const errorContainer = document.getElementById(
        'google-signin-error'
    );

    function showError(message) {
        errorContainer.textContent = message;
        errorContainer.hidden = false;
    }

    function clearError() {
        errorContainer.textContent = '';
        errorContainer.hidden = true;
    }

    async function handleGoogleCredential(response) {
        clearError();

        if (!response || !response.credential) {
            showError('Google tidak memberikan credential yang valid.');
            return;
        }

        const formData = new URLSearchParams();

        formData.set('credential', response.credential);
        formData.set('csrf_token', config.csrfToken);

        try {
            const responseHttp = await fetch(config.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept': 'application/json'
                },
                body: formData.toString()
            });

            const result = await responseHttp.json();

            if (
                !responseHttp.ok ||
                !result.success ||
                typeof result.redirect !== 'string'
            ) {
                showError(
                    result.message ||
                    'Google Sign-In gagal. Silakan coba kembali.'
                );
                return;
            }

            window.location.assign(result.redirect);

        } catch (error) {
            showError(
                'Tidak dapat menghubungi server. Periksa koneksi dan coba kembali.'
            );
        }
    }

    window.vjmInitializeGoogleSignIn = function () {
        if (
            !window.google ||
            !google.accounts ||
            !google.accounts.id
        ) {
            showError('Layanan Google Sign-In belum berhasil dimuat.');
            return;
        }

        google.accounts.id.initialize({
            client_id: config.clientId,
            callback: handleGoogleCredential,
            nonce: config.nonce,
            ux_mode: 'popup',
            auto_select: false
        });

        const buttonWidth = Math.min(
            buttonContainer.clientWidth || 320,
            400
        );

        google.accounts.id.renderButton(
            buttonContainer,
            {
                type: 'standard',
                theme: 'outline',
                size: 'large',
                text: 'continue_with',
                shape: 'rectangular',
                logo_alignment: 'left',
                width: buttonWidth
            }
        );
    };

})();
</script>

<script
    src="https://accounts.google.com/gsi/client"
    async
    defer
    onload="window.vjmInitializeGoogleSignIn()"
></script>
