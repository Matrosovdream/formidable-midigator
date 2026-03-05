<?php
if ( ! defined('ABSPATH') ) { exit; }

final class MidigatorPreventionsListShortcode {

    public const SHORTCODE = 'midigator-preventions-list';

    /** GET params (UI only for now) */
    private const QP_PAGE        = 'mid_pre_page';
    private const QP_Q           = 'mid_pre_q'; // kept for compatibility, but hidden
    private const QP_SORT        = 'mid_pre_sort'; // arn|case|date
    private const QP_DIR         = 'mid_pre_dir';  // asc|desc

    // NEW filter params
    private const QP_CARD_FIRST6 = 'card_first_6';
    private const QP_CARD_LAST4  = 'card_last_4';

    private const ALERT_EXPIRATION_HOURS = 72;

    /** AJAX */
    private const NONCE_ACTION         = 'midigator_preventions_nonce';
    private const AJAX_ACTION_RESOLVE  = 'midigator_resolve_prevention';

    /** Assets */
    private const CSS_HANDLE = 'midigator-preventions-list-css';
    private const JS_HANDLE  = 'midigator-preventions-list-js';

    public function __construct() {
        add_shortcode(self::SHORTCODE, [ $this, 'render_shortcode' ]);

        // AJAX (logged-in). If you need public later: add wp_ajax_nopriv_
        add_action('wp_ajax_' . self::AJAX_ACTION_RESOLVE, [ $this, 'ajax_resolve_prevention' ]);
    }

    public function render_shortcode($atts = []): string {

        $this->enqueue_assets();

        // Shortcode attr: show-resolved="true|false" (default false)
        $atts = shortcode_atts([
            'show-resolved' => 'false',
        ], (array) $atts, self::SHORTCODE);

        $showResolved = filter_var($atts['show-resolved'], FILTER_VALIDATE_BOOLEAN);

        // UI-only filter/sort state (no backend filtering yet)
        $page = isset($_GET[self::QP_PAGE]) ? max(1, (int) $_GET[self::QP_PAGE]) : 1;

        // OLD search (hidden now, but keep reading it to not break old links)
        $q    = isset($_GET[self::QP_Q]) ? sanitize_text_field((string) $_GET[self::QP_Q]) : '';

        $sort = isset($_GET[self::QP_SORT]) ? sanitize_key((string) $_GET[self::QP_SORT]) : '';
        $dir  = isset($_GET[self::QP_DIR]) ? sanitize_key((string) $_GET[self::QP_DIR]) : '';

        if (!in_array($sort, ['arn','case','date'], true)) $sort = '';
        if (!in_array($dir, ['asc','desc'], true)) $dir = '';

        // NEW: card filters from GET
        $card_first_6 = isset($_GET[self::QP_CARD_FIRST6]) ? preg_replace('/\D+/', '', (string) $_GET[self::QP_CARD_FIRST6]) : '';
        $card_last_4  = isset($_GET[self::QP_CARD_LAST4])  ? preg_replace('/\D+/', '', (string) $_GET[self::QP_CARD_LAST4])  : '';

        // Build filters: only pass if not empty (and optionally clamp length)
        $filters = [];

        // By default False
        $filters['is_resolved'] = $showResolved ? true : false;

        if ($card_first_6 !== '') {
            $filters['card_first_6'] = substr($card_first_6, 0, 6);
        }
        if ($card_last_4 !== '') {
            $filters['card_last_4'] = substr($card_last_4, 0, 4);
        }

        $per_page = 30;
        $opts = [
            'page' => $page,
            'per_page' => $per_page,
            'includeEntities' => [ 'resolve', 'resolve_history' ]
        ];

        $list = $this->safe_get_list($filters, $opts);

        if( isset( $_GET['log'] ) ) {
            echo "<pre>";
            print_r($list);
            echo "</pre>";
        } 

        $p = isset($list['pagination']) && is_array($list['pagination']) ? $list['pagination'] : [];
        $cur_page    = isset($p['page']) ? max(1, (int)$p['page']) : $page;
        $total_pages = isset($p['total_pages']) ? max(1, (int)$p['total_pages']) : 1;

        $base_url = $this->current_url_without([ self::QP_PAGE ]);

        ob_start();
        ?>
        <div class="mid-pre-wrap" id="mid-pre">

            <div class="mid-pre-head">
                <div class="mid-pre-title">
                    <?php echo $showResolved ? 'Prevention Flow - Resolved' : 'Prevention Flow'; ?>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" class="mid-pre-filters" id="midPreFiltersForm">
                <?php
                foreach ($_GET as $k => $v) {
                    if (in_array($k, [self::QP_PAGE, self::QP_Q, self::QP_CARD_FIRST6, self::QP_CARD_LAST4], true)) continue;
                    if (is_array($v)) continue;
                    echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string)$v) . '">';
                }
                ?>

                <!-- Search removed / hidden -->
                <input type="hidden" name="<?php echo esc_attr(self::QP_Q); ?>" value="<?php echo esc_attr($q); ?>">

                <div class="mid-pre-filter">
                    <label for="midPreCardFirst6">BIN</label>
                    <input
                        id="midPreCardFirst6"
                        name="<?php echo esc_attr(self::QP_CARD_FIRST6); ?>"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        value="<?php echo esc_attr($card_first_6); ?>"
                        placeholder="e.g. 551306"
                        style="width: 160px;"
                        maxlength="6"
                    />
                </div>

                <div class="mid-pre-filter">
                    <label for="midPreCardLast4">Last 4</label>
                    <input
                        id="midPreCardLast4"
                        name="<?php echo esc_attr(self::QP_CARD_LAST4); ?>"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        value="<?php echo esc_attr($card_last_4); ?>"
                        placeholder="e.g. 9157"
                        style="width: 140px;"
                        maxlength="4"
                    />
                </div>

                <div class="mid-pre-filter mid-pre-filter-actions">
                    <label>&nbsp;</label>
                    <div class="mid-pre-inline">
                        <button type="submit" class="mid-pre-btn mid-pre-btn-primary">Apply</button>
                        <a class="mid-pre-btn" href="<?php echo esc_url($this->current_url_without([self::QP_PAGE, self::QP_Q, self::QP_CARD_FIRST6, self::QP_CARD_LAST4])); ?>">Reset</a>
                    </div>
                </div>

                <input type="hidden" name="<?php echo esc_attr(self::QP_PAGE); ?>" value="1">
            </form>

            <div class="mid-pre-statusbar" id="midPreStatusbar">
                <?php
                if (!empty($list['error'])) {
                    echo '<span class="mid-pre-msg err">' . esc_html((string)$list['error']) . '</span>';
                }
                ?>
            </div>

            <table class="table table-striped table-listing mid-pre-table" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:34px;"><input type="checkbox" id="midPreCheckAll"></th>

                        <th style="width:160px;">Received on</th>
                        <th style="width:160px;">Transaction date</th>
                        <th style="width:170px;">Alert expiration</th>

                        <th style="width:120px;">Amount</th>

                        <th style="width:90px;">BIN</th>
                        <th style="width:80px;">Last 4</th>

                        <th style="width:220px;">ARN</th>
                        <th style="width:260px;">Descriptor</th>

                        <th style="width:220px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $this->render_rows_html($list); ?>
                </tbody>
            </table>

            <div class="mid-pre-footer">
                <div class="mid-pre-pager">
                    <?php
                    $prev_disabled = ($cur_page <= 1);
                    $next_disabled = ($cur_page >= $total_pages);

                    $prev_url = $this->add_query_arg_safe($base_url, self::QP_PAGE, (string) max(1, $cur_page - 1));
                    $next_url = $this->add_query_arg_safe($base_url, self::QP_PAGE, (string) min($total_pages, $cur_page + 1));
                    ?>
                    <a class="mid-pre-btn <?php echo $prev_disabled ? 'is-disabled' : ''; ?>"
                       href="<?php echo $prev_disabled ? '#' : esc_url($prev_url); ?>"
                       <?php echo $prev_disabled ? 'aria-disabled="true"' : ''; ?>
                    >Prev</a>

                    <span class="mid-pre-page"><?php echo esc_html("Page {$cur_page} / {$total_pages}"); ?></span>

                    <a class="mid-pre-btn <?php echo $next_disabled ? 'is-disabled' : ''; ?>"
                       href="<?php echo $next_disabled ? '#' : esc_url($next_url); ?>"
                       <?php echo $next_disabled ? 'aria-disabled="true"' : ''; ?>
                    >Next</a>
                </div>
            </div>

        </div>

        <!-- Resolve modal -->
        <div class="mid-pre-backdrop" id="midPreResolveModal" aria-hidden="true">
            <div class="mid-pre-modal">
                <button type="button" class="mid-pre-modal-close" id="midPreResolveClose" aria-label="Close">×</button>

                <div class="mid-pre-modal-title" id="midPreResolveTitle">Resolve</div>

                <div class="mid-pre-modal-body">
                    <div class="mid-pre-field">
                        <div class="mid-pre-field-label">Resolve reason</div>

                        <select id="midPreResolveReason" style="width:100%;">
                            <option value="">— Select —</option>
                            <?php
                            $reasons = (defined('MIDIGATOR_RESOLVE_PREVENTION_REASONS') && is_array(MIDIGATOR_RESOLVE_PREVENTION_REASONS))
                                ? MIDIGATOR_RESOLVE_PREVENTION_REASONS
                                : [];

                            foreach ($reasons as $value => $label): ?>
                                <option value="<?php echo esc_attr((string)$value); ?>">
                                    <?php echo esc_html((string)$label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Other note -->
                        <div class="mid-pre-field" id="midPreOtherWrap" style="margin-top:10px; display:none;">
                            <div class="mid-pre-field-label">Other note</div>
                            <textarea id="midPreResolveNote" rows="4" style="width:100%; resize:vertical;" placeholder="Write note..."></textarea>
                        </div>

                        <!-- Error/success area (replaces "Note here") -->
                        <div class="mid-pre-modal-statusbar" id="midPreResolveStatusbar"></div>
                    </div>
                </div>

                <div class="mid-pre-modal-actions">
                    <button class="mid-pre-btn" type="button" id="midPreResolveCancel">Cancel</button>
                    <button class="mid-pre-btn mid-pre-btn-success" type="button" id="midPreResolveConfirm" disabled>Resolve</button>
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function enqueue_assets(): void {

        wp_register_style(self::CSS_HANDLE, false, [], '1.0.1');
        wp_enqueue_style(self::CSS_HANDLE);

        wp_add_inline_style(self::CSS_HANDLE, '
            .mid-pre-wrap{padding:10px 0}
            .mid-pre-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
            .mid-pre-title{font-size:18px;font-weight:700}

            .mid-pre-filters{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;margin:10px 0}
            .mid-pre-filter{display:flex;flex-direction:column;gap:6px}
            .mid-pre-filter label{font-weight:600;font-size:12px;color:#24292f}
            .mid-pre-filter input[type="text"]{border:1px solid #d0d7de;border-radius:8px;padding:8px 10px;font-size:14px}
            .mid-pre-inline{display:flex;gap:10px;align-items:center}

            .mid-pre-statusbar{min-height:18px;margin:8px 0}
            .mid-pre-msg{display:inline-block;font-size:12px;padding:2px 6px;border-radius:4px;border:1px solid #d0d7de}
            .mid-pre-msg.err{color:#b00020;border-color:#b00020}

            /* headers left and rows same */
            .mid-pre-table th,.mid-pre-table td{vertical-align:top;text-align:left !important}

            .mid-pre-footer{margin-top:12px;display:flex;justify-content:flex-end}
            .mid-pre-pager{display:flex;gap:10px;align-items:center}

            .mid-pre-btn{display:inline-block;border:1px solid #d0d7de;border-radius:8px;padding:6px 10px;text-decoration:none;background:#fff;cursor:pointer}
            .mid-pre-btn.is-disabled{opacity:.5;pointer-events:none}
            .mid-pre-btn-primary{border-color:#0969da}
            .mid-pre-btn-success{border-color:#0a7a2b}

            .mid-pre-page{color:#57606a;font-size:12px}
            .mid-pre-muted{color:#57606a;font-size:12px}

            /* modal */
            .mid-pre-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:99999}
            .mid-pre-backdrop.is-open{display:flex}
            .mid-pre-modal{width:100%;max-width:520px;background:#fff;border-radius:12px;border:1px solid #d0d7de;box-shadow:0 12px 40px rgba(0,0,0,.25);padding:14px 16px;position:relative}
            .mid-pre-modal-close{position:absolute;right:10px;top:8px;border:0;background:transparent;font-size:22px;line-height:1;cursor:pointer;color:#57606a}
            .mid-pre-modal-title{font-size:16px;font-weight:700;margin-bottom:10px}
            .mid-pre-modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:12px}
            .mid-pre-field-label{font-weight:600;margin-bottom:6px}
            .mid-pre-modal-statusbar{margin-top:10px;min-height:18px;font-size:12px}
            .mid-pre-inline-msg{display:inline-block;padding:2px 6px;border-radius:4px;border:1px solid #d0d7de}
            .mid-pre-inline-msg.ok{color:#0a7a2b;border-color:#0a7a2b}
            .mid-pre-inline-msg.err{color:#b00020;border-color:#b00020}

            .faip-btn{font-size:14px;padding:8px 12px;border-radius:8px;border:1px solid #d0d7de;background:#fff;cursor:pointer}
            .faip-btn-success{border-color:#0a7a2b;color:#0a7a2b}

            .mid-pre-normalized{max-width:360px;white-space:normal;word-break:break-word}
        ');

        // JS (inline via registered empty handle)
        wp_register_script(self::JS_HANDLE, false, [], '1.0.1', true);
        wp_enqueue_script(self::JS_HANDLE);

        wp_localize_script(self::JS_HANDLE, 'MID_PRE_AJAX', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(self::NONCE_ACTION),
            'action_resolve' => self::AJAX_ACTION_RESOLVE,
        ]);

        wp_add_inline_script(self::JS_HANDLE, $this->inline_js());
    }

    private function inline_js(): string {
        return <<<JS
(function(){
  const \$ = (sel, root=document) => root.querySelector(sel);
  const \$\$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  const root = \$('#mid-pre');
  if (!root) return;

  // (Optional) enforce numeric only on inputs
  const onlyDigits = (el, maxLen) => {
    if (!el) return;
    el.addEventListener('input', () => {
      el.value = String(el.value || '').replace(/\\D+/g,'').slice(0, maxLen || 99);
    });
  };
  onlyDigits(\$('#midPreCardFirst6'), 6);
  onlyDigits(\$('#midPreCardLast4'), 4);

  // Check all
  const all = \$('#midPreCheckAll');
  if (all) {
    all.addEventListener('change', function(){
      const boxes = \$\$('#mid-pre tbody input[type="checkbox"][data-guid]');
      boxes.forEach(b => { b.checked = all.checked; });
    });
  }

  // Resolve modal elements
  const modal = \$('#midPreResolveModal');
  const btnClose = \$('#midPreResolveClose');
  const btnCancel = \$('#midPreResolveCancel');
  const btnConfirm = \$('#midPreResolveConfirm');
  const elReason = \$('#midPreResolveReason');
  const elOtherWrap = \$('#midPreOtherWrap');
  const elNote = \$('#midPreResolveNote');
  const statusbar = \$('#midPreResolveStatusbar');
  const title = \$('#midPreResolveTitle');

  let currentGuid = '';

  const setStatus = (type, text) => {
    if (!statusbar) return;
    if (!text) { statusbar.innerHTML = ''; return; }
    statusbar.innerHTML = '<span class="mid-pre-inline-msg ' + (type || '') + '">' + String(text) + '</span>';
  };

  const updateOtherVisibility = () => {
    const v = (elReason && elReason.value) ? elReason.value : '';
    const isOther = (v === 'other');
    if (elOtherWrap) elOtherWrap.style.display = isOther ? 'block' : 'none';
    if (!isOther && elNote) elNote.value = '';
  };

  const updateConfirmState = () => {
    if (!btnConfirm) return;
    const v = (elReason && elReason.value) ? elReason.value : '';
    if (!v) { btnConfirm.disabled = true; return; }
    if (v === 'other') {
      const note = (elNote && elNote.value) ? String(elNote.value).trim() : '';
      btnConfirm.disabled = (note.length < 1);
      return;
    }
    btnConfirm.disabled = false;
  };

  const openModal = (guid) => {
    currentGuid = guid || '';
    if (title) title.textContent = currentGuid ? ('Resolve ' + currentGuid) : 'Resolve';
    if (elReason) elReason.value = '';
    if (elNote) elNote.value = '';
    if (btnConfirm) btnConfirm.disabled = true;
    if (elOtherWrap) elOtherWrap.style.display = 'none';
    setStatus('', '');
    if (modal) { modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); }
  };

  const closeModal = () => {
    currentGuid = '';
    setStatus('', '');
    if (modal) { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); }
  };

  if (btnClose) btnClose.addEventListener('click', closeModal);
  if (btnCancel) btnCancel.addEventListener('click', closeModal);
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
  }

  if (elReason) {
    elReason.addEventListener('change', () => {
      setStatus('', '');
      updateOtherVisibility();
      updateConfirmState();
    });
  }
  if (elNote) {
    elNote.addEventListener('input', () => {
      updateConfirmState();
    });
  }

  // Row action: Resolve
  root.addEventListener('click', (e) => {
    const t = e.target;
    if (!t || !t.matches('[data-action="approve_row"]')) return;

    e.preventDefault();
    const guid = t.getAttribute('data-guid') || '';
    if (!guid) { alert('Missing prevention_guid'); return; }
    openModal(guid);
  });

  const markRowResolved = (guid) => {
    if (!guid) return;
    const btn = root.querySelector('[data-action="approve_row"][data-guid="' + CSS.escape(guid) + '"]');
    if (!btn) return;

    const cell = btn.closest('td');
    if (!cell) return;

    btn.style.display = 'none';

    let badge = cell.querySelector('.mid-pre-inline-msg.ok[data-resolved="1"]');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'mid-pre-inline-msg ok';
      badge.setAttribute('data-resolved','1');
      badge.textContent = 'Resolved';
      cell.appendChild(badge);
    }
  };

  // Confirm resolve -> AJAX
  if (btnConfirm) {
    btnConfirm.addEventListener('click', async () => {
      if (!currentGuid) { setStatus('err', 'Missing prevention_guid'); return; }

      const resolve_reason = (elReason && elReason.value) ? elReason.value : '';
      if (!resolve_reason) { setStatus('err', 'Select resolve reason'); return; }

      const note = (elNote && elNote.value) ? String(elNote.value).trim() : '';
      if (resolve_reason === 'other' && !note) { setStatus('err', 'Write note for "Other action"'); return; }

      btnConfirm.disabled = true;
      setStatus('', 'Saving…');

      try {
        const fd = new FormData();
        fd.append('action', (MID_PRE_AJAX && MID_PRE_AJAX.action_resolve) ? MID_PRE_AJAX.action_resolve : 'midigator_resolve_prevention');
        fd.append('_ajax_nonce', (MID_PRE_AJAX && MID_PRE_AJAX.nonce) ? MID_PRE_AJAX.nonce : '');
        fd.append('prevention_guid', currentGuid);
        fd.append('resolve_reason', resolve_reason);
        fd.append('note', note);

        const res = await fetch((MID_PRE_AJAX && MID_PRE_AJAX.ajax_url) ? MID_PRE_AJAX.ajax_url : window.ajaxurl, {
          method: 'POST',
          credentials: 'same-origin',
          body: fd
        });

        const json = await res.json().catch(() => ({}));

        if (json && json.success) {
          // success -> close modal and update row UI
          closeModal();
          markRowResolved(currentGuid);
        } else {
          const msg = (json && json.data && json.data.message) ? json.data.message : 'Resolve failed';
          setStatus('err', msg);
        }
      } catch (err) {
        setStatus('err', (err && err.message) ? err.message : 'Request failed');
      } finally {
        // keep disabled state accurate after modal close/open, but safe to re-enable here
        btnConfirm.disabled = false;
        updateConfirmState();
      }
    });
  }
})();
JS;
    }

    /**
     * Backend resolve handler
     * Receives: prevention_guid, resolve_reason, note
     */
    public function ajax_resolve_prevention(): void {
        check_ajax_referer(self::NONCE_ACTION, '_ajax_nonce');

        $preventionGuid = isset($_POST['prevention_guid']) ? sanitize_text_field((string) $_POST['prevention_guid']) : '';
        $reason         = isset($_POST['resolve_reason']) ? sanitize_text_field((string) $_POST['resolve_reason']) : '';
        $note           = isset($_POST['note']) ? sanitize_textarea_field((string) $_POST['note']) : '';

        if ($preventionGuid === '') {
            wp_send_json_error([ 'message' => 'Missing prevention_guid' ], 400);
        }
        if ($reason === '') {
            wp_send_json_error([ 'message' => 'Missing resolve_reason' ], 400);
        }
        if ($reason === 'other' && trim($note) === '') {
            wp_send_json_error([ 'message' => 'Note is required for "Other action"' ], 400);
        }

        try {
            $prevHelper = new FrmMidigatorPreventionHelper();

            // note can be empty for non-other reasons (ok)
            $res = $prevHelper->resolvePreventionAlert($preventionGuid, $reason, $note);

            // Normalize response
            $ok = false;
            if (is_array($res) && array_key_exists('ok', $res)) {
                $ok = (bool) $res['ok'];
            } elseif (is_array($res) && array_key_exists('success', $res)) {
                $ok = (bool) $res['success'];
            } elseif (is_object($res) && isset($res->ok)) {
                $ok = (bool) $res->ok;
            }

            if (!$ok) {
                // Best-effort message extraction
                $msg = 'Resolve failed';
                if (is_array($res)) {
                    if (!empty($res['error']) && is_string($res['error'])) $msg = $res['error'];
                    elseif (!empty($res['message']) && is_string($res['message'])) $msg = $res['message'];
                    elseif (!empty($res['data']['message']) && is_string($res['data']['message'])) $msg = $res['data']['message'];
                } elseif (is_object($res)) {
                    if (!empty($res->message) && is_string($res->message)) $msg = $res->message;
                }

                wp_send_json_error([ 'message' => $msg ], 400);
            }

            wp_send_json_success([
                'ok' => true,
                'prevention_guid' => $preventionGuid,
                'resolve_reason' => $reason,
            ]);

        } catch (\Throwable $e) {
            wp_send_json_error([ 'message' => $e->getMessage() ], 500);
        }
    }

    /**
     * Server-side list fetch
     */
    private function safe_get_list(array $filters, array $opts): array {
        try {

            $model = new FrmMidigatorPreventionModel();
            $rows = $model->getList( $filters, $opts );
            if (!is_array($rows)) $rows = [];

            return $rows;

        } catch (\Throwable $e) {
            return [
                'data' => [],
                'pagination' => [
                    'page' => $opts['page'] ?? 1,
                    'per_page' => $opts['per_page'] ?? 30,
                    'total' => 0,
                    'total_pages' => 1,
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function render_rows_html(array $list): string {

        $items = isset($list['data']) && is_array($list['data']) ? $list['data'] : [];

        if (empty($items)) {
            return '<tr><td colspan="14" class="mid-pre-muted">No results.</td></tr>';
        }

        $fmt_dt = static function($raw): string {
            $raw = (string) $raw;
            if ($raw === '') return '';
            $ts = strtotime($raw);
            if (!$ts) return '';
            return date('Y-m-d H:i', $ts);
        };

        $calc_exp = static function($preventionTsRaw): string {
            $preventionTsRaw = (string) $preventionTsRaw;
            if ($preventionTsRaw === '') return '';
            $ts = strtotime($preventionTsRaw);
            if (!$ts) return '';
            $ts += (int) MidigatorPreventionsListShortcode::ALERT_EXPIRATION_HOURS * 3600;
            return date('Y-m-d H:i', $ts);
        };

        ob_start();

        foreach ($items as $item) {

            if (is_object($item)) $item = (array) $item;
            if (!is_array($item)) continue;

            // GUID (required for resolve)
            $guid = isset($item['prevention_guid']) ? (string) $item['prevention_guid'] : '';
            if ($guid === '') {
                // Without guid we cannot resolve; still render but disable button
                $guid = '';
            }

            // Requested columns
            $caseId     = isset($item['prevention_case_number']) ? (string) $item['prevention_case_number'] : '';
            $receivedOn = $fmt_dt($item['created_at'] ?? ($item['prevention_timestamp'] ?? ''));
            $txDate     = $fmt_dt($item['transaction_timestamp'] ?? '');
            $prevTsRaw  = (string)($item['prevention_timestamp'] ?? '');
            $expiration = $calc_exp($prevTsRaw);

            $amount   = isset($item['amount']) ? (string) $item['amount'] : '';
            $currency = isset($item['currency']) ? (string) $item['currency'] : '';

            $bin   = isset($item['card_first_6']) ? (string) $item['card_first_6'] : '';
            $last4 = isset($item['card_last_4']) ? (string) $item['card_last_4'] : '';

            $arn        = isset($item['arn']) ? (string) $item['arn'] : '';
            $descriptor = isset($item['merchant_descriptor']) ? (string) $item['merchant_descriptor'] : '';

            $rowResolved = !empty($item['is_resolved']);

            // Fallback display
            if ($caseId === '') $caseId = '—';
            if ($receivedOn === '') $receivedOn = '—';
            if ($txDate === '') $txDate = '—';
            if ($expiration === '') $expiration = '—';
            if ($amount === '') $amount = '—';
            if ($currency === '') $currency = '—';
            if ($bin === '') $bin = '—';
            if ($last4 === '') $last4 = '—';
            if ($arn === '') $arn = '—';
            if ($descriptor === '') $descriptor = '—';

            $btnDisabled = ($guid === '');
            ?>
            <tr>
                <td><input type="checkbox" data-guid="<?php echo esc_attr($guid); ?>" <?php echo $btnDisabled ? 'disabled' : ''; ?>></td>

                <td><?php echo esc_html($receivedOn); ?></td>
                <td><?php echo esc_html($txDate); ?></td>
                <td><?php echo esc_html($expiration); ?></td>

                <td><?php echo esc_html($amount . ' ' . $currency); ?></td>

                <td><?php echo esc_html($bin); ?></td>
                <td><?php echo esc_html($last4); ?></td>

                <td><?php echo esc_html($arn); ?></td>
                <td class="mid-pre-normalized"><?php echo esc_html($descriptor); ?></td>

                <td>
                <td>
                    <?php if (!$rowResolved): ?>

                        <button
                            class="faip-btn faip-btn-success"
                            data-action="approve_row"
                            data-guid="<?php echo esc_attr($guid); ?>"
                            type="button"
                            <?php echo $btnDisabled ? 'disabled' : ''; ?>
                        >Resolve</button>

                    <?php else: ?>

                        
                       
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }

        return (string) ob_get_clean();
    }

    private function current_url_without(array $remove_keys): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $uri    = $_SERVER['REQUEST_URI'] ?? '';
        $url    = $scheme . '://' . $host . $uri;

        $parts = wp_parse_url($url);
        $path  = $parts['path'] ?? '';
        $query = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        foreach ($remove_keys as $k) {
            unset($query[$k]);
        }

        $base = $scheme . '://' . $host . $path;
        if (!empty($query)) {
            $base .= '?' . http_build_query($query);
        }

        return $base;
    }

    private function add_query_arg_safe(string $url, string $key, string $value): string {
        return add_query_arg([ $key => $value ], $url);
    }
}

add_action('init', function(){
    if (class_exists('MidigatorPreventionsListShortcode')) {
        new MidigatorPreventionsListShortcode();
    }
});