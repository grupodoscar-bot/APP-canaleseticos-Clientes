<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';
require __DIR__ . '/../includes/mailer.php';
$me = require_auth();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT * FROM reports WHERE id=? LIMIT 1");
$st->execute([$id]);
$r = $st->fetch();
if (!$r) { http_response_code(404); exit('Denuncia no encontrada'); }

$errors = [];
$ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $now = gmdate('Y-m-d H:i:s');

    if ($act === 'acuse') {
        if (empty($r['acknowledged_at'])) {
            db()->prepare("UPDATE reports SET acknowledged_at=?, estado=CASE WHEN estado='recibida' THEN 'en_curso' ELSE estado END, updated_at=? WHERE id=?")
                ->execute([$now, $now, $id]);
            db()->prepare("UPDATE libro_registro SET fecha_acuse=? WHERE report_id=?")->execute([$now, $id]);
            audit('report_acknowledged', (int)$me['id'], $id);
            notify_acuse_reporter($r);
            $ok = t('case.ok.ack');
        }
    } elseif ($act === 'estado') {
        $ne = $_POST['estado'] ?? '';
        if (!in_array($ne, ['recibida','en_curso','resuelta','desestimada','bloqueada'], true)) {
            $errors[] = t('set.err.invalid');
        } else {
            $resolved = in_array($ne, ['resuelta','desestimada'], true) ? $now : null;
            db()->prepare("UPDATE reports SET estado=?, resolved_at=COALESCE(?, resolved_at), updated_at=? WHERE id=?")
                ->execute([$ne, $resolved, $now, $id]);
            if ($resolved) {
                db()->prepare("UPDATE libro_registro SET fecha_resolucion=?, estado_final=? WHERE report_id=?")
                    ->execute([$now, $ne, $id]);
            } else {
                db()->prepare("UPDATE libro_registro SET estado_final=? WHERE report_id=?")
                    ->execute([$ne, $id]);
            }
            audit('report_state_change', (int)$me['id'], $id, ['to' => $ne]);
            notify_status_change_reporter($r, $ne);
            $ok = t('case.ok.state');
        }
    } elseif ($act === 'investigacion') {
        $flag = !empty($_POST['flag']) ? 1 : 0;
        db()->prepare("UPDATE reports SET en_investigacion=?, updated_at=? WHERE id=?")->execute([$flag, $now, $id]);
        audit('report_investigation_flag', (int)$me['id'], $id, ['flag' => $flag]);
        $ok = t('case.ok.invest');
    } elseif ($act === 'mensaje') {
        if ($r['anonimizada']) { $errors[] = t('case.content.anon'); }
        else {
            $body = trim((string)($_POST['body'] ?? ''));
            if (mb_strlen($body) < 1 || mb_strlen($body) > 10000) $errors[] = t('set.err.invalid');
            else {
                db()->prepare("INSERT INTO communications (report_id, sender, user_id, body_enc, created_at) VALUES (?, 'admin', ?, ?, ?)")
                    ->execute([$id, (int)$me['id'], enc($body), $now]);
                db()->prepare("UPDATE reports SET updated_at=? WHERE id=?")->execute([$now, $id]);
                audit('message_from_admin', (int)$me['id'], $id);
                notify_new_message_reporter($r);
                $ok = t('case.ok.sent');
            }
        }
    }
    $st->execute([$id]); $r = $st->fetch();
}

db()->prepare("UPDATE communications SET read_at=? WHERE report_id=? AND sender='denunciante' AND read_at IS NULL")
    ->execute([gmdate('Y-m-d H:i:s'), $id]);

$msgs = db()->prepare("SELECT * FROM communications WHERE report_id=? ORDER BY created_at ASC");
$msgs->execute([$id]); $msgs = $msgs->fetchAll();

$ats = db()->prepare("SELECT * FROM report_attachments WHERE report_id=? ORDER BY created_at");
$ats->execute([$id]); $ats = $ats->fetchAll();

$age_days = (time() - strtotime($r['created_at'] . ' UTC')) / 86400;
$overdue = empty($r['acknowledged_at']) && $age_days > 7;

render_header(t('case.title') . ' ' . $r['codigo_seguimiento'], true);
?>
<main class="mid" style="max-width:90%">
    <?= breadcrumb([t('nav.panel') => '/admin/dashboard.php', t('case.title') . ' ' . $r['codigo_seguimiento']]) ?>

    <?php if ($ok): ?><div class="alert ok"><div><?= h($ok) ?></div></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert err"><div><?= h($e) ?></div></div><?php endforeach; ?>

    <div class="grid-sidebar" style="grid-template-columns:minmax(0,1fr) 300px">
        <div>
            <div class="card">
                <div class="space-between">
                    <div>
                        <div class="small muted" style="margin-bottom:4px"><?= h(t('case.section.tracking')) ?></div>
                        <div style="font-family:var(--font-mono);letter-spacing:2px;font-size:1.3rem;font-weight:600"><?= h($r['codigo_seguimiento']) ?></div>
                    </div>
                    <span class="badge dot <?= h($r['estado']) ?>"><?= h(t('st.'.$r['estado'])) ?></span>
                </div>

                <hr>

                <div class="kv">
                    <div><?= h(t('case.kv.category')) ?></div><div><?= h(t('cat.'.$r['categoria'])) ?></div>
                    <div><?= h(t('case.kv.relation')) ?></div><div><?= $r['relacion'] ? h(t('rel.'.$r['relacion'])) : '—' ?></div>
                    <?php if (!empty($r['identificada']) && !empty($r['reporter_id'])):
                        $repSt = db()->prepare("SELECT * FROM reporters WHERE id=? LIMIT 1");
                        $repSt->execute([(int)$r['reporter_id']]);
                        $repRow = $repSt->fetch();
                        $repNombre = ($repRow && $repRow['nombre_enc']) ? (string)dec((string)$repRow['nombre_enc']) : '';
                    ?>
                    <div>Denunciante</div><div><span class="badge recibida">Identificada</span> <?= $repRow ? '<strong>' . h($repNombre !== '' ? $repNombre : (string)$repRow['email']) . '</strong> · <code>' . h((string)$repRow['email']) . '</code>' : '<span class="muted">cuenta eliminada</span>' ?></div>
                    <?php else: ?>
                    <div>Denunciante</div><div><span class="badge overdue">Anónima</span></div>
                    <?php endif; ?>
                    <div><?= h(t('case.kv.received')) ?></div><div><?= h($r['created_at']) ?> UTC</div>
                    <div><?= h(t('case.kv.ack')) ?></div><div>
                        <?= $r['acknowledged_at']
                            ? h($r['acknowledged_at']) . ' UTC'
                            : ($overdue ? '<span class="badge overdue">' . h(t('dash.pending')) . ' ' . (int)$age_days . 'd</span>' : '<span class="muted">' . h(t('dash.pending')) . '</span>') ?>
                    </div>
                    <div><?= h(t('case.kv.updated')) ?></div><div><?= h($r['updated_at']) ?> UTC</div>
                    <div><?= h(t('case.kv.invest')) ?></div><div><?= $r['en_investigacion'] ? '<span class="badge recibida">'.h(t('case.invest.yes')).'</span>' : '<span class="muted">—</span>' ?></div>
                    <div><?= h(t('case.kv.anon')) ?></div><div><?= $r['anonimizada'] ? '✓' : '—' ?></div>
                </div>
            </div>

            <div class="card">
                <h2 style="margin-top:0"><?= h(t('case.content.title')) ?></h2>
                <?php if ($r['anonimizada']): ?>
                    <div class="empty"><div class="empty-icon">🔒</div><p><?= h(t('case.content.anon')) ?></p></div>
                <?php else: ?>
                    <h3><?= h(t('case.content.h.title')) ?></h3>
                    <p><?= nl2br(h((string)dec($r['titulo_enc']))) ?></p>
                    <h3><?= h(t('case.content.h.desc')) ?></h3>
                    <div style="white-space:pre-wrap;background:var(--n-25);border:1px solid var(--border);padding:16px 18px;border-radius:var(--radius);line-height:1.7;font-size:.94rem"><?= h((string)dec($r['descripcion_enc'])) ?></div>
                <?php endif; ?>

                <?php if ($ats): ?>
                <h3><?= h(t('case.attach.title')) ?> (<?= count($ats) ?>)</h3>
                <ul class="clean">
                    <?php foreach ($ats as $a): ?>
                        <li>
                            <span>📎</span>
                            <span style="flex:1">
                                <a href="/admin/download.php?id=<?= (int)$a['id'] ?>"><?= h((string)dec($a['filename_original_enc'])) ?></a>
                                <div class="small muted"><?= h($a['mime']) ?> · <?= number_format($a['size_bytes']/1024, 1) ?> KB</div>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 style="margin-top:0"><?= h(t('case.chat.title')) ?></h2>
                <?php if (!$msgs): ?>
                    <div class="empty"><div class="empty-icon">💬</div><p><?= h(t('case.chat.empty')) ?></p></div>
                <?php else: ?>
                <div class="chat">
                    <?php foreach ($msgs as $m): ?>
                    <div class="msg <?= h($m['sender']) ?>">
                        <?= nl2br(h((string)dec($m['body_enc']))) ?>
                        <div class="meta"><?= h(t($m['sender']==='admin' ? 'case.chat.you' : 'case.chat.reporter')) ?> · <?= h($m['created_at']) ?> UTC</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!$r['anonimizada']): ?>
                <form method="post" style="margin-top:18px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mensaje">
                    <div class="form-group">
                        <label for="body"><?= h(t('case.chat.reply')) ?></label>
                        <textarea id="body" name="body" required minlength="1" maxlength="10000" placeholder="<?= h(t('case.chat.placeholder')) ?>"></textarea>
                    </div>
                    <div class="btn-row"><button class="btn dark" type="submit"><?= h(t('case.chat.send')) ?></button></div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <aside>
            <div class="card" style="position:sticky;top:calc(var(--header-h) + 18px)">
                <h3 style="margin-top:0"><?= h(t('case.actions')) ?></h3>

                <?php if (empty($r['acknowledged_at'])): ?>
                <form method="post" style="margin-bottom:14px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="acuse">
                    <p class="help" style="margin:0 0 8px"><?= h(t('case.ack.hint')) ?></p>
                    <button class="btn dark block" type="submit"><?= h(t('case.ack.btn')) ?></button>
                </form>
                <hr>
                <?php endif; ?>

                <form method="post" style="margin-bottom:14px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="estado">
                    <div class="form-group">
                        <label><?= h(t('case.state.change')) ?></label>
                        <select name="estado">
                            <?php foreach (['recibida','en_curso','resuelta','desestimada','bloqueada'] as $k): ?>
                                <option value="<?= h($k) ?>" <?= $r['estado']===$k?'selected':'' ?>><?= h(t('st.'.$k)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn dark block" type="submit"><?= h(t('case.state.save')) ?></button>
                </form>

                <hr>

                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="investigacion">
                    <label class="check-inline">
                        <input type="checkbox" name="flag" value="1" <?= $r['en_investigacion']?'checked':'' ?>>
                        <span class="small"><?= t('case.invest.check') ?></span>
                    </label>
                    <button class="btn sm dark block" type="submit"><?= h(t('case.invest.save')) ?></button>
                </form>
            </div>
        </aside>
    </div>
</main>
<?php render_footer(true); ?>
