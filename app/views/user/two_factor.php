<?php
ob_start();
$title = $title ?? 'Two-Factor Authentication';
$status = $status ?? ['enabled' => false, 'pending_setup' => false];
$provisioningUri = $provisioningUri ?? null;
$qrUrl = $qrUrl ?? null;
$enabled = !empty($status['enabled']);
$pending = !empty($status['pending_setup']);
$secret = $status['secret'] ?? null;
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/branch-index.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/user-theme.css">

<div class="branch-hub user-theme container-fluid py-2">
    <header class="branch-hub-hero">
        <div>
            <h1><i class="fas fa-shield-halved me-2"></i>Two-factor authentication</h1>
            <p>Add a second step at sign-in using an authenticator app (Google Authenticator, Authy, etc.).</p>
            <span class="hero-badge"><?= $enabled ? '<i class="fas fa-circle-check"></i> Enabled' : '<i class="fas fa-circle-xmark"></i> Disabled' ?></span>
        </div>
        <div class="branch-hub-actions">
            <a href="<?= BASE_URL ?>dashboard" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i> Dashboard</a>
        </div>
    </header>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if ($enabled): ?>
                <div class="branch-form-panel mb-3">
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-lock me-1"></i> Two-factor authentication is active on your account.
                    </div>
                </div>

                <div class="branch-form-panel">
                    <div class="branch-form-section-head mb-3">
                        <span class="icon-wrap slate"><i class="fas fa-unlock"></i></span>
                        Disable 2FA
                    </div>
                    <p class="text-muted small">Enter your current password and a valid authenticator code to turn off 2FA.</p>
                    <form method="POST" action="<?= BASE_URL ?>user/two_factor_disable" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
                        <div class="col-md-6">
                            <label class="form-label">Current password</label>
                            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Authenticator code</label>
                            <input type="text" name="code" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Disable two-factor authentication?')">
                                <i class="fas fa-unlock me-1"></i> Disable 2FA
                            </button>
                        </div>
                    </form>
                </div>

            <?php elseif ($pending && $secret): ?>
                <div class="branch-form-panel mb-3">
                    <div class="branch-form-section-head mb-3">
                        <span class="icon-wrap indigo"><i class="fas fa-qrcode"></i></span>
                        Step 1 — Scan or enter secret
                    </div>
                    <div class="row g-4 align-items-center">
                        <?php if ($qrUrl): ?>
                        <div class="col-md-4 text-center">
                            <img src="<?= htmlspecialchars($qrUrl, ENT_QUOTES) ?>" alt="QR code" width="200" height="200" class="border rounded">
                        </div>
                        <?php endif; ?>
                        <div class="col-md-8">
                            <p class="small text-muted mb-2">Manual entry key:</p>
                            <code class="d-block p-2 bg-light rounded user-select-all"><?= htmlspecialchars(chunk_split($secret, 4, ' '), ENT_QUOTES) ?></code>
                            <?php if ($provisioningUri): ?>
                                <p class="small text-muted mt-3 mb-0">Or open this URI in a compatible app:</p>
                                <small class="text-break d-block"><?= htmlspecialchars($provisioningUri, ENT_QUOTES) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="branch-form-panel">
                    <div class="branch-form-section-head mb-3">
                        <span class="icon-wrap teal"><i class="fas fa-check"></i></span>
                        Step 2 — Confirm with a code
                    </div>
                    <form method="POST" action="<?= BASE_URL ?>user/two_factor_confirm" class="row g-3 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
                        <div class="col-md-6">
                            <label class="form-label">6-digit code from app</label>
                            <input type="text" name="code" class="form-control form-control-lg" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-shield-halved me-1"></i> Enable 2FA</button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <div class="branch-form-panel">
                    <p class="text-muted">Protect your account with time-based one-time passwords. You will be asked for a code after entering your password.</p>
                    <form method="POST" action="<?= BASE_URL ?>user/two_factor_setup">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-qrcode me-1"></i> Set up authenticator</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (!empty($telegram_column_ready)): ?>
                <div class="branch-form-panel mt-3">
                    <div class="branch-form-section-head mb-3">
                        <span class="icon-wrap slate"><i class="fab fa-telegram"></i></span>
                        Telegram notifications
                    </div>
                    <p class="text-muted small">
                        Save your personal Telegram chat id to receive instant alerts (e.g. new sales challans for your branch).
                        Message <strong>@userinfobot</strong> on Telegram to get your numeric id, then paste it below.
                        You must also start a chat with the company bot once so it can message you.
                    </p>
                    <form method="POST" action="<?= BASE_URL ?>user/update_telegram" class="row g-3 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
                        <div class="col-md-8">
                            <label class="form-label" for="self_telegram_user_id">Telegram User ID</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" name="telegram_user_id" id="self_telegram_user_id"
                                   class="form-control" placeholder="e.g. 123456789"
                                   value="<?= htmlspecialchars((string)($telegram_user_id ?? ''), ENT_QUOTES) ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="fab fa-telegram me-1"></i> Save Telegram ID
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once '../app/views/layouts/main.php';
