<?php

// Firebase Cloud Messaging — rotate keys if they were previously committed to git.
// Server key: Project Settings → Cloud Messaging → Legacy server key (PHP push from SalesController)
define('FCM_SERVER_KEY', '5CYQru6bx0YPq8plkv8oPgQDwt-36N-nvVFlhbuBf9A');

// Web push key pair: Project Settings → Cloud Messaging → Web configuration → Key pair (browser getToken)
define('FCM_VAPID_KEY', 'BLnKqotoEKiav1-d1MohV6sCBJcwbpiLipGm8BjKe3OYonR0D8yACTJC91V1-Lza5koPl8e9GO42FpUZzpE05uY');