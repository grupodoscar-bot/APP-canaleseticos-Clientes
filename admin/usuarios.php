<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';
$me = require_auth();
require_admin($me);

$errors = []; $ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        $role  = ($_POST['role'] ?? 'officer') === 'admin' ? 'admin' : 'officer';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('users.err.email');
        if (strlen($pass) < 10) $errors[] = t('users.err.pwd');
        if (!$errors) {
            try {
                $st = db()->prepare("INSERT INTO users (email, pass_hash, role, created_at, activo) VALUES (?,?,?,?,1)");
                $st->execute([$email, password_hash($pass, PASSWORD_ARGON2ID), $role, gmdate('Y-m-d H:i:s')]);
                audit('user_created', (int)$me['id'], null, ['email' => $email, 'role' => $role]);
                $ok = t('users.ok.created');
            } catch (Throwable $e) { $errors[] = t('users.err.dup'); }
        }
    } elseif ($act === 'toggle') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid && $uid !== (int)$me['id']) {
            db()->prepare("UPDATE users SET activo = 1 - activo WHERE id=?")->execute([$uid]);
            audit('user_toggled', (int)$me['id'], null, ['uid' => $uid]);
            $ok = t('users.ok.toggled');
        }
    } elseif ($act === 'reset_totp') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid) {
            db()->prepare("UPDATE users SET totp_secret=NULL, totp_enabled=0 WHERE id=?")->execute([$uid]);
            audit('user_totp_reset', (int)$me['id'], null, ['uid' => $uid]);
            $ok = t('users.ok.2fa_reset');
        }
    }
}

$users = db()->query("SELECT id,email,role,activo,totp_enabled,created_at,last_login FROM users ORDER BY id ASC")->fetchAll();
render_header(t('users.title'), true);
?>
<main class="wide">
    <?= breadcrumb([t('nav.panel') => '/admin/dashboard.php', t('users.title')]) ?>

    <div class="page-head">
        <h1><?= h(t('users.title')) ?></h1>
        <p><?= h(t('users.sub')) ?></p>
    </div>

    <?php if ($ok): ?><div class="alert ok"><div><?= h($ok) ?></div></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <div class="table-wrap">
        <table>
            <thead><tr><th><?= h(t('users.col.email')) ?></th><th><?= h(t('users.col.role')) ?></th><th><?= h(t('users.col.status')) ?></th><th><?= h(t('users.col.2fa')) ?></th><th><?= h(t('users.col.created')) ?></th><th><?= h(t('users.col.last')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <strong><?= h($u['email']) ?></strong>
                        <?php if ((int)$u['id'] === (int)$me['id']): ?> <span class="pill"><?= h(t('users.you')) ?></span><?php endif; ?>
                    </td>
                    <td><span class="badge <?= $u['role']==='admin' ? 'recibida' : '' ?>"><?= h(t('users.role.'.$u['role'])) ?></span></td>
                    <td><?= $u['activo'] ? '<span class="badge resuelta">'.h(t('users.status.active')).'</span>' : '<span class="badge desestimada">'.h(t('users.status.inactive')).'</span>' ?></td>
                    <td><?= $u['totp_enabled'] ? '<span class="badge resuelta">✓</span>' : '<span class="muted small">—</span>' ?></td>
                    <td class="small muted"><?= h(substr((string)$u['created_at'], 0, 10)) ?></td>
                    <td class="small muted"><?= h($u['last_login'] ?? '—') ?></td>
                    <td>
                        <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
                            <form method="post" data-confirm="<?= h(t('users.confirm.toggle')) ?>" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                                <button class="btn sm secondary" type="submit"><?= h(t($u['activo'] ? 'users.btn.deactivate' : 'users.btn.activate')) ?></button>
                            </form>
                            <form method="post" data-confirm="<?= h(t('users.confirm.reset')) ?>" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset_totp">
                                <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                                <button class="btn sm ghost" type="submit"><?= h(t('users.btn.reset2fa')) ?></button>
                            </form>
                        </div>
                        <?php else: ?><span class="muted small">—</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-top:26px">
        <h2 style="margin-top:0"><?= h(t('users.create.title')) ?></h2>
        <p class="muted"><?= h(t('users.create.hint')) ?></p>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="grid-2">
                <div class="form-group">
                    <label><?= h(t('users.create.email')) ?></label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label><?= h(t('users.create.role')) ?></label>
                    <select name="role">
                        <option value="officer"><?= h(t('users.create.role.officer')) ?></option>
                        <option value="admin"><?= h(t('users.create.role.admin')) ?></option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label><?= h(t('users.create.pwd')) ?> <small><?= h(t('users.create.pwd.hint')) ?></small></label>
                <input type="password" name="password" required minlength="10">
            </div>
            <div class="btn-row"><button class="btn" type="submit"><?= h(t('users.create.btn')) ?></button></div>
        </form>
    </div>
</main>
<?php render_footer(true); ?>
