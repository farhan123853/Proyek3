
/**
 * VILLA JOGLO MASAYU
 * External Email Verification Service
 *
 * Platform : Google Apps Script
 * Mail      : MailApp
 * Endpoint  : doPost(e)
 */

// =====================================================
// 1. HTTP POST ENTRY POINT
// =====================================================

function doPost(e) {
  try {
    if (!e || !e.postData || !e.postData.contents) {
      return jsonResponse({
        success: false,
        message: 'Request body tidak ditemukan.'
      });
    }

    const payload = JSON.parse(e.postData.contents);

    const properties =
      PropertiesService.getScriptProperties();

    const expectedSecret =
      properties.getProperty('EMAIL_API_SECRET');

    const providedSecret =
      String(payload.secret || '');

    // Periksa shared secret.
    if (
      !expectedSecret ||
      !providedSecret ||
      !secureEquals(expectedSecret, providedSecret)
    ) {
      return jsonResponse({
        success: false,
        message: 'Unauthorized.'
      });
    }

    if (payload.action !== 'send_verification') {
      return jsonResponse({
        success: false,
        message: 'Action tidak dikenali.'
      });
    }

    return sendVerificationEmail(payload);

  } catch (error) {
    console.error('Endpoint error: ' + error.message);

    return jsonResponse({
      success: false,
      message: 'Terjadi kesalahan pada layanan email.'
    });
  }
}


// =====================================================
// 2. SEND VERIFICATION EMAIL
// =====================================================

function sendVerificationEmail(payload) {
  const properties =
    PropertiesService.getScriptProperties();

  const appBaseUrl =
    String(properties.getProperty('APP_BASE_URL') || '')
      .replace(/\/+$/, '');

  const recipient =
    String(payload.to || '').trim();

  const customerName =
    String(payload.name || 'Pelanggan')
      .trim()
      .slice(0, 100);

  const verificationUrl =
    String(payload.verification_url || '').trim();

  // Validasi email penerima.
  const emailPattern =
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  if (
    !emailPattern.test(recipient) ||
    recipient.length > 254
  ) {
    return jsonResponse({
      success: false,
      message: 'Alamat email tidak valid.'
    });
  }

  // Pastikan URL hanya berasal dari aplikasi kita.
  const expectedPrefix =
    appBaseUrl + '/verifikasi-email.php?token=';

  if (!verificationUrl.startsWith(expectedPrefix)) {
    return jsonResponse({
      success: false,
      message: 'URL verifikasi tidak diizinkan.'
    });
  }

  // Token harus berupa 64 karakter hexadecimal.
  const token =
    verificationUrl.substring(expectedPrefix.length);

  if (!/^[a-f0-9]{64}$/i.test(token)) {
    return jsonResponse({
      success: false,
      message: 'Format token tidak valid.'
    });
  }

  // Periksa kuota pengiriman email.
  const remainingQuota =
    MailApp.getRemainingDailyQuota();

  if (remainingQuota < 1) {
    return jsonResponse({
      success: false,
      message: 'Kuota pengiriman email habis.'
    });
  }

  // Escape nama untuk HTML email.
  const safeName = escapeHtml(customerName);
  const safeUrl = escapeHtml(verificationUrl);

  const subject =
    'Verifikasi Email - Villa Joglo Masayu';

  const plainBody =
    'Halo ' + customerName + '!\n\n' +
    'Terima kasih telah mendaftar di Villa Joglo Masayu.\n\n' +
    'Silakan buka tautan berikut untuk memverifikasi email:\n' +
    verificationUrl + '\n\n' +
    'Tautan ini hanya dapat digunakan satu kali dan berlaku ' +
    'selama 30 menit.\n\n' +
    'Jika kamu tidak merasa melakukan pendaftaran, abaikan email ini.\n\n' +
    'Villa Joglo Masayu';

  const htmlBody = `
    <div style="
      max-width:600px;
      margin:auto;
      padding:32px;
      font-family:Arial,sans-serif;
      color:#39271b;
      background:#fffaf0;
      border:1px solid #e8dcc5;
    ">
      <h1 style="color:#a87500;text-align:center;">
        Villa Joglo Masayu
      </h1>

      <p>Halo <strong>${safeName}</strong>!</p>

      <p>
        Terima kasih telah mendaftar di
        Villa Joglo Masayu.
      </p>

      <p>
        Untuk menyelesaikan proses registrasi,
        silakan verifikasi alamat email kamu
        melalui tombol berikut.
      </p>

      <p style="text-align:center;margin:32px 0;">
        <a href="${safeUrl}" style="
          display:inline-block;
          padding:14px 28px;
          background:#e5a400;
          color:#39271b;
          text-decoration:none;
          font-weight:bold;
          border-radius:5px;
        ">
          VERIFIKASI EMAIL SAYA
        </a>
      </p>

      <p style="font-size:13px;color:#806d50;">
        Tautan verifikasi hanya dapat digunakan satu kali
        dan berlaku selama 30 menit.
      </p>

      <p style="font-size:13px;color:#806d50;">
        Jika kamu tidak merasa melakukan pendaftaran,
        abaikan email ini.
      </p>
    </div>
  `;

  MailApp.sendEmail({
    to: recipient,
    subject: subject,
    body: plainBody,
    htmlBody: htmlBody,
    name: 'Villa Joglo Masayu'
  });

  console.log(
    'Verification email sent to: ' + recipient
  );

  return jsonResponse({
    success: true,
    message: 'Email verifikasi berhasil dikirim.'
  });
}


// =====================================================
// 3. SECURITY HELPERS
// =====================================================

function secureEquals(a, b) {
  if (a.length !== b.length) {
    return false;
  }

  let result = 0;

  for (let i = 0; i < a.length; i++) {
    result |= a.charCodeAt(i) ^ b.charCodeAt(i);
  }

  return result === 0;
}


function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, function(char) {
    const entities = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    };

    return entities[char];
  });
}


// =====================================================
// 4. JSON RESPONSE
// =====================================================

function jsonResponse(data) {
  return ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
}


// =====================================================
// 5. EXISTING MAIL TEST
// =====================================================

function testMailApp() {
  const recipient =
    Session.getEffectiveUser().getEmail();

  if (!recipient) {
    throw new Error(
      'Alamat email pengirim tidak berhasil dideteksi.'
    );
  }

  const remainingQuota =
    MailApp.getRemainingDailyQuota();

  if (remainingQuota < 1) {
    throw new Error(
      'Kuota pengiriman email harian sudah habis.'
    );
  }

  MailApp.sendEmail({
    to: recipient,
    subject: 'Tes Gmail Service - Villa Joglo Masayu',
    body:
      'Integrasi Google Apps Script MailApp berhasil dijalankan.'
  });

  Logger.log('Email berhasil dikirim ke: ' + recipient);
  Logger.log('Sisa kuota harian: ' + (remainingQuota - 1));
}
