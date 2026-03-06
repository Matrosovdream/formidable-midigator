(function(){
    const $ = (sel, root=document) => root.querySelector(sel);
    const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));
  
    const root = $('#mid-pre');
    if (!root) return;
  
    const ajaxCfg = window.MID_PRE_AJAX || {};
  
    // enforce numeric only on inputs
    const onlyDigits = (el, maxLen) => {
      if (!el) return;
      el.addEventListener('input', () => {
        el.value = String(el.value || '').replace(/\D+/g, '').slice(0, maxLen || 99);
      });
    };
  
    const escapeHtml = (str) => {
      return String(str == null ? '' : str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    };
  
    const postFormData = async (actionName, payload) => {
      const fd = new FormData();
      fd.append('action', actionName);
      fd.append('_ajax_nonce', ajaxCfg.nonce || '');
  
      Object.keys(payload || {}).forEach((key) => {
        fd.append(key, payload[key] == null ? '' : String(payload[key]));
      });
  
      const res = await fetch(ajaxCfg.ajax_url || window.ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });
  
      return await res.json().catch(() => ({}));
    };
  
    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  
    onlyDigits($('#midPreCardFirst6'), 6);
    onlyDigits($('#midPreCardLast4'), 4);
  
    // --------------------------------------------------
    // Bulk controls
    // --------------------------------------------------
    const all = $('#midPreCheckAll');
    const bulkReason = $('#midPreBulkReason');
    const bulkResolveBtn = $('#midPreBulkResolve');
    const bulkRefundBtn = $('#midPreBulkRefund');
    const bulkStatusbar = $('#midPreBulkStatusbar');
  
    const getCheckboxes = () => {
      return $$('#mid-pre tbody input[type="checkbox"][data-guid]');
    };
  
    const getSelectedRows = () => {
      return getCheckboxes()
        .filter((cb) => cb.checked && !cb.disabled)
        .map((cb) => {
          const tr = cb.closest('tr');
          const guid = cb.getAttribute('data-guid') || (tr ? tr.getAttribute('data-guid') : '') || '';
          const itemIdRaw = cb.getAttribute('data-item-id') || (tr ? tr.getAttribute('data-item-id') : '') || '';
          const itemId = parseInt(itemIdRaw, 10) || 0;
  
          return {
            checkbox: cb,
            tr: tr,
            prevention_guid: guid,
            id: itemId
          };
        })
        .filter((row) => row.prevention_guid && row.id > 0);
    };
  
    const syncCheckAllState = () => {
      if (!all) return;
  
      const boxes = getCheckboxes().filter((b) => !b.disabled);
      const checked = boxes.filter((b) => b.checked);
  
      if (!boxes.length) {
        all.checked = false;
        all.indeterminate = false;
        return;
      }
  
      all.checked = checked.length === boxes.length;
      all.indeterminate = checked.length > 0 && checked.length < boxes.length;
    };
  
    const updateBulkButtonsState = () => {
      const selectedCount = getSelectedRows().length;
      const hasSelected = selectedCount > 0;
  
      if (bulkResolveBtn) bulkResolveBtn.disabled = !hasSelected;
      if (bulkRefundBtn) bulkRefundBtn.disabled = !hasSelected;
    };
  
    const refreshBulkUiState = () => {
      syncCheckAllState();
      updateBulkButtonsState();
    };
  
    const setBulkStatus = (type, text, progress) => {
      if (!bulkStatusbar) return;
  
      if (!text) {
        bulkStatusbar.innerHTML = '';
        return;
      }
  
      const pct = Math.max(0, Math.min(100, parseInt(progress || 0, 10)));
  
      bulkStatusbar.innerHTML = ''
        + '<div class="mid-pre-bulk-status-wrap">'
        +   '<span class="mid-pre-inline-msg ' + escapeHtml(type || '') + '">' + escapeHtml(text) + '</span>'
        +   '<div class="mid-pre-progress">'
        +     '<div class="mid-pre-progress-bar" style="width:' + pct + '%;"></div>'
        +   '</div>'
        + '</div>';
    };
  
    if (all) {
      all.addEventListener('change', function(){
        const boxes = getCheckboxes().filter((b) => !b.disabled);
        boxes.forEach((b) => {
          b.checked = all.checked;
        });
        refreshBulkUiState();
      });
    }
  
    root.addEventListener('change', (e) => {
      const t = e.target;
      if (!t) return;
  
      if (t.matches('tbody input[type="checkbox"][data-guid]')) {
        refreshBulkUiState();
      }
    });
  
    // --------------------------------------------------
    // Resolve modal elements
    // --------------------------------------------------
    const modal = $('#midPreResolveModal');
    const btnClose = $('#midPreResolveClose');
    const btnCancel = $('#midPreResolveCancel');
    const btnConfirm = $('#midPreResolveConfirm');
    const elReason = $('#midPreResolveReason');
    const elOtherWrap = $('#midPreOtherWrap');
    const elNote = $('#midPreResolveNote');
    const statusbar = $('#midPreResolveStatusbar');
    const title = $('#midPreResolveTitle');
  
    let currentGuid = '';
    let bulkBusy = false;
  
    const setStatus = (type, text) => {
      if (!statusbar) return;
      if (!text) {
        statusbar.innerHTML = '';
        return;
      }
      statusbar.innerHTML = '<span class="mid-pre-inline-msg ' + (type || '') + '">' + escapeHtml(text) + '</span>';
    };
  
    const updateOtherVisibility = () => {
      const v = (elReason && elReason.value) ? elReason.value : '';
      const isOther = (v === 'other');
  
      if (elOtherWrap) {
        elOtherWrap.style.display = isOther ? 'block' : 'none';
      }
  
      if (!isOther && elNote) {
        elNote.value = '';
      }
    };
  
    const updateConfirmState = () => {
      if (!btnConfirm) return;
  
      const v = (elReason && elReason.value) ? elReason.value : '';
  
      if (!v) {
        btnConfirm.disabled = true;
        return;
      }
  
      if (v === 'other') {
        const note = (elNote && elNote.value) ? String(elNote.value).trim() : '';
        btnConfirm.disabled = (note.length < 1);
        return;
      }
  
      btnConfirm.disabled = false;
    };
  
    const openModal = (guid) => {
      currentGuid = guid || '';
  
      if (title) {
        title.textContent = currentGuid ? ('Resolve ' + currentGuid) : 'Resolve';
      }
  
      if (elReason) elReason.value = '';
      if (elNote) elNote.value = '';
      if (btnConfirm) btnConfirm.disabled = true;
      if (elOtherWrap) elOtherWrap.style.display = 'none';
  
      setStatus('', '');
  
      if (modal) {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
      }
    };
  
    const closeModal = () => {
      currentGuid = '';
      setStatus('', '');
  
      if (modal) {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
      }
    };
  
    if (btnClose) btnClose.addEventListener('click', closeModal);
    if (btnCancel) btnCancel.addEventListener('click', closeModal);
  
    if (modal) {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          closeModal();
        }
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
      if (!guid) {
        alert('Missing prevention_guid');
        return;
      }
  
      openModal(guid);
    });
  
    const findRowByGuid = (guid) => {
      if (!guid) return null;
  
      let checkbox = null;
      try {
        checkbox = root.querySelector('tbody input[type="checkbox"][data-guid="' + CSS.escape(guid) + '"]');
      } catch (e) {
        checkbox = root.querySelector('tbody input[type="checkbox"][data-guid]');
      }
  
      if (!checkbox) return null;
  
      return {
        checkbox: checkbox,
        tr: checkbox.closest('tr'),
        prevention_guid: guid,
        id: parseInt(checkbox.getAttribute('data-item-id') || '0', 10) || 0
      };
    };
  
    const findActionCell = (row) => {
      if (!row) return null;
  
      const tr = row.tr || (row.checkbox ? row.checkbox.closest('tr') : null);
      if (!tr) return null;
  
      const btn = tr.querySelector('[data-action="approve_row"]');
      if (btn) return btn.closest('td');
  
      return tr.querySelector('td:last-child');
    };
  
    const ensureBadge = (cell, text, typeClass, resultKey) => {
      if (!cell) return null;
  
      let badge = cell.querySelector('.mid-pre-inline-msg[data-result="' + resultKey + '"]');
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'mid-pre-inline-msg ' + (typeClass || '');
        badge.setAttribute('data-result', resultKey);
        badge.textContent = text;
        cell.appendChild(badge);
      } else {
        badge.className = 'mid-pre-inline-msg ' + (typeClass || '');
        badge.textContent = text;
      }
  
      return badge;
    };
  
    const hideResolveButton = (cell) => {
      if (!cell) return;
      const btn = cell.querySelector('[data-action="approve_row"]');
      if (btn) btn.style.display = 'none';
    };
  
    const markRowResolved = (rowOrGuid) => {
      const row = typeof rowOrGuid === 'string' ? findRowByGuid(rowOrGuid) : rowOrGuid;
      if (!row) return;
  
      const cell = findActionCell(row);
      if (!cell) return;
  
      hideResolveButton(cell);
      ensureBadge(cell, 'Resolved', 'ok', 'resolved');
    };
  
    const markRowRefunded = (row) => {
      if (!row) return;
  
      const cell = findActionCell(row);
      if (!cell) return;
  
      hideResolveButton(cell);
      ensureBadge(cell, 'Refunded', 'ok', 'refunded');
    };
  
    // Confirm resolve -> AJAX
    if (btnConfirm) {
      btnConfirm.addEventListener('click', async () => {
        if (!currentGuid) {
          setStatus('err', 'Missing prevention_guid');
          return;
        }
  
        const resolve_reason = (elReason && elReason.value) ? elReason.value : '';
        if (!resolve_reason) {
          setStatus('err', 'Select resolve reason');
          return;
        }
  
        const note = (elNote && elNote.value) ? String(elNote.value).trim() : '';
        if (resolve_reason === 'other' && !note) {
          setStatus('err', 'Write note for "Other action"');
          return;
        }
  
        btnConfirm.disabled = true;
        setStatus('', 'Saving…');
  
        try {
          const json = await postFormData(
            (ajaxCfg.action_resolve || 'midigator_resolve_prevention'),
            {
              prevention_guid: currentGuid,
              resolve_reason: resolve_reason,
              note: note
            }
          );
  
          if (json && json.success) {
            const resolvedGuid = currentGuid;
            closeModal();
            markRowResolved(resolvedGuid);
            refreshBulkUiState();
          } else {
            const msg = (json && json.data && json.data.message) ? json.data.message : 'Resolve failed';
            setStatus('err', msg);
          }
        } catch (err) {
          setStatus('err', (err && err.message) ? err.message : 'Request failed');
        } finally {
          btnConfirm.disabled = false;
          updateConfirmState();
        }
      });
    }
  
    const setBulkBusy = (state) => {
      bulkBusy = !!state;
  
      if (bulkReason) bulkReason.disabled = bulkBusy;
      if (all) all.disabled = bulkBusy;
  
      getCheckboxes().forEach((cb) => {
        cb.disabled = bulkBusy;
      });
  
      if (bulkResolveBtn) {
        bulkResolveBtn.disabled = bulkBusy || getSelectedRows().length < 1;
      }
  
      if (bulkRefundBtn) {
        bulkRefundBtn.disabled = bulkBusy || getSelectedRows().length < 1;
      }
    };
  
    const processBulk = async (mode) => {
      if (bulkBusy) return;
  
      const rows = getSelectedRows();
      if (!rows.length) {
        setBulkStatus('err', 'Select at least one row', 0);
        return;
      }
  
      const resolveReason = bulkReason ? String(bulkReason.value || '') : '';
      if (mode === 'resolve' && !resolveReason) {
        setBulkStatus('err', 'Select resolve reason', 0);
        return;
      }
  
      setBulkBusy(true);
  
      let successCount = 0;
      let failCount = 0;
  
      try {
        for (let i = 0; i < rows.length; i++) {
          const row = rows[i];
          const progress = Math.round((i / rows.length) * 100);
  
          setBulkStatus(
            '',
            (mode === 'resolve' ? 'Resolving ' : 'Refunding ') + (i + 1) + ' of ' + rows.length + '…',
            progress
          );
  
          try {
            const actionName = mode === 'resolve'
              ? (ajaxCfg.action_bulk_resolve || 'midigator_bulk_resolve_prevention')
              : (ajaxCfg.action_bulk_refund || 'midigator_bulk_full_refund_prevention');
  
            const payload = {
              id: row.id,
              prevention_guid: row.prevention_guid
            };
  
            if (mode === 'resolve') {
              payload.resolve_reason = resolveReason;
            }
  
            const json = await postFormData(actionName, payload);
  
            if (json && json.success) {
              successCount++;
  
              if (mode === 'resolve') {
                markRowResolved(row);
              } else {
                markRowRefunded(row);
              }
  
              if (row.checkbox) {
                row.checkbox.checked = false;
              }
            } else {
              failCount++;
            }
          } catch (err) {
            failCount++;
          }
  
          refreshBulkUiState();
          await sleep(80);
        }
  
        if (failCount < 1) {
          setBulkStatus(
            'ok',
            (mode === 'resolve' ? 'Resolve' : 'Refund') + ' completed. ' + successCount + '/' + rows.length + ' processed.',
            100
          );
        } else {
          setBulkStatus(
            'err',
            (mode === 'resolve' ? 'Resolve' : 'Refund') + ' finished with errors. Success: ' + successCount + ', Failed: ' + failCount + '.',
            100
          );
        }
      } finally {
        setBulkBusy(false);
        refreshBulkUiState();
      }
    };
  
    if (bulkResolveBtn) {
      bulkResolveBtn.addEventListener('click', async () => {
        await processBulk('resolve');
      });
    }
  
    if (bulkRefundBtn) {
      bulkRefundBtn.addEventListener('click', async () => {
        await processBulk('refund');
      });
    }
  
    refreshBulkUiState();
  })();