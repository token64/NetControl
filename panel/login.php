<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (auth_logged_in()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! csrf_verify()) {
        $error = 'Sesión de seguridad inválida. Recarga la página.';
    } else {
        $user = trim((string) ($_POST['user'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');
        if (auth_login($user, $pass)) {
            $_SESSION['panel_user'] = ADMIN_USER;
            flash_set('success', 'Bienvenido al panel.');
            redirect('index.php');
        }
        $error = 'Usuario o contraseña incorrectos.';
    }
}

$pageTitle = 'Iniciar sesión — NetControl';
require __DIR__ . '/partials/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="nc-card p-4 p-md-5">
            <h1 class="h4 mb-4">Acceso al panel</h1>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger nc-flash"><?= esc($error) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Usuario</label>
                    <input type="text" name="user" class="form-control" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-nc-primary w-100">Entrar</button>
            </form>
            <p class="small text-secondary mt-3 mb-0">Cambia la clave por defecto en <code>config.php</code>.</p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
