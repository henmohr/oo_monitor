<?php
/** @var array $_ */
$status = $_['status'] ?? [];
$meta = $_['meta'] ?? [];
$ok = (bool)($status['ok'] ?? false);
$message = (string)($status['message'] ?? '');
$output = (string)($status['output'] ?? '');
$timestamp = (string)($status['timestamp'] ?? '');
$outFilePath = (string)($meta['outFilePath'] ?? '');
$outFileStatus = $meta['outFileStatus'] ?? [];
$intervalMinutes = (int)($meta['intervalMinutes'] ?? 15);
$lastCheckAt = (string)($meta['lastCheckAt'] ?? '');
$lastCheckOk = (string)($meta['lastCheckOk'] ?? '');
$lastReconnectAt = (string)($meta['lastReconnectAt'] ?? '');
$lastReconnectOk = (string)($meta['lastReconnectOk'] ?? '');
$history = is_array($meta['history'] ?? null) ? $meta['history'] : [];
$lastFileTestAt = (string)($meta['lastFileTestAt'] ?? '');
$lastFileTestOk = (string)($meta['lastFileTestOk'] ?? '');
$lastFileTestMessage = (string)($meta['lastFileTestMessage'] ?? '');
$appEnabled = (bool)($meta['appEnabled'] ?? true);
$appdataBackupPath = (string)($meta['appdataBackupPath'] ?? '');
$outStatusMessage = (string)($outFileStatus['message'] ?? '');
$outStatusWarn = $outStatusMessage === 'Permission issue';
?>
<div class="oo-monitor">
  <h2>OnlyOffice Monitor</h2>
  <div class="oo-monitor__banner" id="oo-filetest-banner" style="<?php p($lastFileTestAt === '' ? 'display:none;' : ''); ?>">
    <strong>Teste de arquivo:</strong>
    <span id="oo-filetest-msg"><?php p($lastFileTestMessage); ?></span>
    <span id="oo-filetest-time"><?php p($lastFileTestAt); ?></span>
    <span id="oo-filetest-ok"><?php p($lastFileTestOk === '' ? '' : ($lastFileTestOk === '1' ? '(OK)' : '(FAIL)')); ?></span>
  </div>
  <div class="oo-monitor__status">
    <div><strong>Status:</strong> <span id="oo-status-text"><?php p($ok ? 'OK' : 'FAIL'); ?></span></div>
    <div><strong>Message:</strong> <span id="oo-status-message"><?php p($message); ?></span></div>
    <div><strong>Timestamp:</strong> <span id="oo-status-time"><?php p($timestamp); ?></span></div>
    <div><strong>App:</strong> <span id="oo-app-enabled" class="oo-monitor__badge <?php p($appEnabled ? 'ok' : 'fail'); ?>"><?php p($appEnabled ? 'Habilitado' : 'Desabilitado'); ?></span></div>
    <div><strong>Último check:</strong> <span id="oo-last-check"><?php p($lastCheckAt); ?></span> <span id="oo-last-check-ok"><?php p($lastCheckOk === '' ? '' : ($lastCheckOk === '1' ? '(OK)' : '(FAIL)')); ?></span></div>
    <div><strong>Última reconexão:</strong> <span id="oo-last-reconnect"><?php p($lastReconnectAt); ?></span> <span id="oo-last-reconnect-ok"><?php p($lastReconnectOk === '' ? '' : ($lastReconnectOk === '1' ? '(OK)' : '(FAIL)')); ?></span></div>
    <div><strong>out-oo.txt:</strong> <span id="oo-out-path"><?php p($outFilePath); ?></span></div>
    <div><strong>Permissões:</strong> <span id="oo-out-status"><?php p((string)($outFileStatus['message'] ?? '')); ?></span></div>
    <?php if ($appdataBackupPath !== ''): ?>
      <div><strong>Appdata:</strong> <span id="oo-appdata-path"><?php p($appdataBackupPath); ?></span></div>
    <?php endif; ?>
    <div class="oo-monitor__alert" id="oo-out-alert" style="<?php p($outStatusWarn ? '' : 'display:none;'); ?>">
      Atenção: verifique permissões/leitura/escrita do out-oo.txt.
    </div>
  </div>
  <div class="oo-monitor__actions">
    <button id="oo-check" class="button">Check/Reconnect</button>
    <button id="oo-backup" class="button">Fazer backup agora</button>
    <a class="button" id="oo-download-backup" href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('oo_monitor.status.downloadBackup')); ?>">Baixar backup (JSON)</a>
    <button id="oo-test-file" class="button">Testar acesso ao arquivo</button>
    <span id="oo-check-result"></span>
  </div>
  <div class="oo-monitor__settings">
    <h3>Configurações</h3>
    <div class="oo-monitor__field">
      <label for="oo-out-path-input"><strong>Caminho do out-oo.txt</strong></label>
      <input id="oo-out-path-input" type="text" value="<?php p($outFilePath); ?>" placeholder="/caminho/para/out-oo.txt">
    </div>
    <div class="oo-monitor__field">
      <label for="oo-interval-input"><strong>Intervalo do job (minutos)</strong></label>
      <input id="oo-interval-input" type="number" min="1" value="<?php p((string)$intervalMinutes); ?>">
    </div>
    <button id="oo-save-settings" class="button">Salvar configurações</button>
    <span id="oo-settings-result"></span>
  </div>
  <div class="oo-monitor__history">
    <h3>Histórico (últimos 10)</h3>
    <div id="oo-history">
      <?php if ($history === []): ?>
        <div>Sem histórico ainda.</div>
      <?php else: ?>
        <?php foreach (array_reverse($history) as $item): ?>
          <div class="oo-monitor__history-row">
            <span><?php p((string)($item['ts'] ?? '')); ?></span>
            <span><?php p((string)($item['action'] ?? '')); ?></span>
            <span><?php p((bool)($item['ok'] ?? false) ? 'OK' : 'FAIL'); ?></span>
            <span><?php p((string)($item['message'] ?? '')); ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <pre id="oo-output" class="oo-monitor__output"><?php p($output); ?></pre>
</div>

<script>
  (function () {
    var btn = document.getElementById('oo-check');
    var statusText = document.getElementById('oo-status-text');
    var statusMessage = document.getElementById('oo-status-message');
    var statusTime = document.getElementById('oo-status-time');
    var lastCheck = document.getElementById('oo-last-check');
    var lastCheckOk = document.getElementById('oo-last-check-ok');
    var lastReconnect = document.getElementById('oo-last-reconnect');
    var lastReconnectOk = document.getElementById('oo-last-reconnect-ok');
    var appEnabled = document.getElementById('oo-app-enabled');
    var outPath = document.getElementById('oo-out-path');
    var outStatus = document.getElementById('oo-out-status');
    var outAlert = document.getElementById('oo-out-alert');
    var output = document.getElementById('oo-output');
    var result = document.getElementById('oo-check-result');
    var backupBtn = document.getElementById('oo-backup');
    var testBtn = document.getElementById('oo-test-file');
    var outPathInput = document.getElementById('oo-out-path-input');
    var intervalInput = document.getElementById('oo-interval-input');
    var saveSettings = document.getElementById('oo-save-settings');
    var settingsResult = document.getElementById('oo-settings-result');
    var filetestBanner = document.getElementById('oo-filetest-banner');
    var filetestMsg = document.getElementById('oo-filetest-msg');
    var filetestTime = document.getElementById('oo-filetest-time');
    var filetestOk = document.getElementById('oo-filetest-ok');
    var historyContainer = document.getElementById('oo-history');

    function renderHistory(items) {
      if (!historyContainer) return;
      if (!items || !items.length) {
        historyContainer.innerHTML = '<div>Sem histórico ainda.</div>';
        return;
      }
      var html = items.slice().reverse().map(function (item) {
        var ok = item.ok ? 'OK' : 'FAIL';
        return '<div class="oo-monitor__history-row">' +
          '<span>' + (item.ts || '') + '</span>' +
          '<span>' + (item.action || '') + '</span>' +
          '<span>' + ok + '</span>' +
          '<span>' + (item.message || '') + '</span>' +
        '</div>';
      }).join('');
      historyContainer.innerHTML = html;
    }

    btn.addEventListener('click', function () {
      btn.disabled = true;
      result.textContent = 'Running...';

      fetch(OC.generateUrl('/apps/oo_monitor/check'), {
        method: 'POST',
        headers: {
          'requesttoken': OC.requestToken
        }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          statusText.textContent = data.ok ? 'OK' : 'FAIL';
          statusMessage.textContent = data.message || '';
          statusTime.textContent = data.timestamp || '';
          output.textContent = data.output || '';
          result.textContent = data.action ? ('Action: ' + data.action) : 'Done';
          if (data.timestamp) {
            lastCheck.textContent = data.timestamp;
            lastCheckOk.textContent = data.ok ? '(OK)' : '(FAIL)';
          }
          if (data.action === 'reconnect') {
            lastReconnect.textContent = data.timestamp || '';
            lastReconnectOk.textContent = data.ok ? '(OK)' : '(FAIL)';
          }
          if (data.meta && data.meta.history) {
            renderHistory(data.meta.history);
          }
          if (data.meta && typeof data.meta.appEnabled !== 'undefined') {
            appEnabled.textContent = data.meta.appEnabled ? 'Habilitado' : 'Desabilitado';
            appEnabled.className = 'oo-monitor__badge ' + (data.meta.appEnabled ? 'ok' : 'fail');
          }
        })
        .catch(function (e) {
          result.textContent = 'Error: ' + e;
        })
        .finally(function () {
          btn.disabled = false;
        });
    });

    backupBtn.addEventListener('click', function () {
      backupBtn.disabled = true;
      result.textContent = 'Saving backup...';

      fetch(OC.generateUrl('/apps/oo_monitor/backup'), {
        method: 'POST',
        headers: {
          'requesttoken': OC.requestToken
        }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          result.textContent = data.message || 'Backup saved';
        })
        .catch(function (e) {
          result.textContent = 'Error: ' + e;
        })
        .finally(function () {
          backupBtn.disabled = false;
        });
    });

    testBtn.addEventListener('click', function () {
      testBtn.disabled = true;
      result.textContent = 'Testing...';

      fetch(OC.generateUrl('/apps/oo_monitor/test-file'), {
        method: 'POST',
        headers: {
          'requesttoken': OC.requestToken
        }
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          result.textContent = data.message || 'Test complete';
          if (data && data.message) {
            filetestBanner.style.display = '';
            filetestMsg.textContent = data.message || '';
            filetestTime.textContent = data.timestamp || new Date().toISOString();
            filetestOk.textContent = data.ok ? '(OK)' : '(FAIL)';
          }
        })
        .catch(function (e) {
          result.textContent = 'Error: ' + e;
        })
        .finally(function () {
          testBtn.disabled = false;
        });
    });

    saveSettings.addEventListener('click', function () {
      saveSettings.disabled = true;
      settingsResult.textContent = 'Saving...';

      var form = new URLSearchParams();
      form.append('outFilePath', outPathInput.value || '');
      form.append('intervalMinutes', parseInt(intervalInput.value || '15', 10));

      fetch(OC.generateUrl('/apps/oo_monitor/settings'), {
        method: 'POST',
        headers: {
          'requesttoken': OC.requestToken,
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body: form.toString()
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.meta) {
            settingsResult.textContent = 'Saved';
            return;
          }
          outPath.textContent = data.meta.outFilePath || '';
          outStatus.textContent = (data.meta.outFileStatus && data.meta.outFileStatus.message) || '';
          var msg = (data.meta.outFileStatus && data.meta.outFileStatus.message) || '';
          var warn = msg === 'Permission issue';
          outAlert.style.display = warn ? '' : 'none';
          intervalInput.value = data.meta.intervalMinutes || 15;
          lastCheck.textContent = data.meta.lastCheckAt || '';
          lastCheckOk.textContent = data.meta.lastCheckOk === '1' ? '(OK)' : (data.meta.lastCheckOk === '0' ? '(FAIL)' : '');
          lastReconnect.textContent = data.meta.lastReconnectAt || '';
          lastReconnectOk.textContent = data.meta.lastReconnectOk === '1' ? '(OK)' : (data.meta.lastReconnectOk === '0' ? '(FAIL)' : '');
          appEnabled.textContent = data.meta.appEnabled ? 'Habilitado' : 'Desabilitado';
          appEnabled.className = 'oo-monitor__badge ' + (data.meta.appEnabled ? 'ok' : 'fail');
          if (data.meta.lastFileTestAt) {
            filetestBanner.style.display = '';
            filetestMsg.textContent = data.meta.lastFileTestMessage || '';
            filetestTime.textContent = data.meta.lastFileTestAt || '';
            filetestOk.textContent = data.meta.lastFileTestOk === '1' ? '(OK)' : (data.meta.lastFileTestOk === '0' ? '(FAIL)' : '');
          }
          if (data.meta.history) {
            renderHistory(data.meta.history);
          }
          settingsResult.textContent = data.message || 'Settings saved';
        })
        .catch(function (e) {
          settingsResult.textContent = 'Error: ' + e;
        })
        .finally(function () {
          saveSettings.disabled = false;
        });
    });
  })();
</script>

<style>
  .oo-monitor { padding: 12px 0; }
  .oo-monitor__status { margin: 8px 0; }
  .oo-monitor__actions { margin: 12px 0; display: flex; gap: 8px; align-items: center; }
  .oo-monitor__settings { margin: 12px 0; padding: 12px; background: var(--color-background-hover); border-radius: 8px; }
  .oo-monitor__field { margin-bottom: 8px; display: flex; flex-direction: column; gap: 4px; max-width: 520px; }
  .oo-monitor__field input { padding: 6px 8px; }
  .oo-monitor__alert { margin-top: 8px; padding: 8px; border-left: 4px solid #c46; background: #fff4f6; }
  .oo-monitor__banner { margin: 8px 0; padding: 8px; border-left: 4px solid #4c6; background: #f4fff6; }
  .oo-monitor__badge { display: inline-block; padding: 2px 6px; border-radius: 999px; font-weight: 600; font-size: 12px; }
  .oo-monitor__badge.ok { background: #e7f7ed; color: #256a3a; }
  .oo-monitor__badge.fail { background: #fdebed; color: #9b1c1c; }
  .oo-monitor__history { margin: 12px 0; }
  .oo-monitor__history-row { display: grid; grid-template-columns: 180px 120px 60px 1fr; gap: 8px; padding: 4px 0; }
  .oo-monitor__history-row:nth-child(odd) { background: var(--color-background-hover); }
  .oo-monitor__output { background: var(--color-background-dark); padding: 8px; border-radius: 6px; max-height: 240px; overflow: auto; }
</style>
