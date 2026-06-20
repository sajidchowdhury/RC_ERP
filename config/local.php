<?php

// Firebase Cloud Messaging — rotate keys if they were previously committed to git.
// Server key: Project Settings → Cloud Messaging → Legacy server key (PHP push from SalesController)
define('FCM_SERVER_KEY', '5CYQru6bx0YPq8plkv8oPgQDwt-36N-nvVFlhbuBf9A');

// Web push key pair: Project Settings → Cloud Messaging → Web configuration → Key pair (browser getToken)
define('FCM_VAPID_KEY', 'BLnKqotoEKiav1-d1MohV6sCBJcwbpiLipGm8BjKe3OYonR0D8yACTJC91V1-Lza5koPl8e9GO42FpUZzpE05uY');

define('INVESTIGATION_QR_SECRET', '1234567890');
define('INVESTIGATION_COMPANY_EMAIL', 'sajidchowdhury35@gmail.com');
define('INVESTIGATION_EFFECTIVE_ROLE', 'superadmin'); // role used during restriction
define('INVESTIGATION_OTP_MINUTES', 15);
define('INVESTIGATION_SHOW_OTP_ON_FAIL', true); // show code on scan page when mail() fails (local dev)
define('INVESTIGATION_FISCAL_START_MONTH', 7); // July
define('GL_RECONCILIATION_TOLERANCE', 0.02);
define('RECON_ALERT_EMAIL', 'sajidchowdhury35@gmail.com');
define('TELEGRAM_BOT_TOKEN', '8896166121:AAEGfcR8npPp_F8S6iI6USzYtM4jb_Zms10');
define('TELEGRAM_ALERTS_ENABLED', true);