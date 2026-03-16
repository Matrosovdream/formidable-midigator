(function(){
  const $ = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  const root = $('#mid-pre');
  if (!root) return;

  const ajaxCfg = window.MID_PRE_AJAX || {};

  // --------------------------------------------------
  // Helpers
  // --------------------------------------------------
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
      const val = payload[key];

      if (Array.isArray(val)) {
        val.forEach((item) => {
          fd.append(key + '[]', item == null ? '' : String(item));
        });
        return;
      }

      fd.append(key, val == null ? '' : String(val));
    });

    const res = await fetch(ajaxCfg.ajax_url || window.ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    });

    return await res.json().catch(() => ({}));
  };

  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  const reloadPage = () => {
    window.location.reload();
  };

  const formatRefundText = (payment) => {
    if (!payment || typeof payment !== 'object') {
      return 'To refund: —';
    }

    const refunded = payment.refunded_amount != null && payment.refunded_amount !== ''
      ? String(payment.refunded_amount)
      : '0';

    const full = payment.full_amount != null && payment.full_amount !== ''
      ? String(payment.full_amount)
      : '0';

    return 'To refund: ' + refunded + '/' + full;
  };

  onlyDigits($('#midPreCardFirst6'), 6);
  onlyDigits($('#midPreCardLast4'), 4);

  // --------------------------------------------------
  // Bulk controls
  // --------------------------------------------------
  const all = $('#midPreCheckAll');
  const bulkReason = $('#midPreBulkReason');
  const bulkResolveBtn = $('#midPreBulkResolve');
  const bulkRefundBtn = $('#midPreBulkRefund');
  const bulkDeleteBtn = $('#midPreBulkDelete');
  const deleteAllBtn = $('#midPreDeleteAll');
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

  const getAllRows = () => {
    return getCheckboxes()
      .filter((cb) => !cb.disabled)
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

  const getCheckedOrderCheckboxesForRow = (row) => {
    const tr = row?.tr || null;
    if (!tr) return [];

    return $$('.mid-pre-order-check:checked[data-order-id]', tr);
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
    const hasAnyRows = getAllRows().length > 0;

    if (bulkResolveBtn) bulkResolveBtn.disabled = !hasSelected;
    if (bulkRefundBtn) bulkRefundBtn.disabled = !hasSelected;
    if (bulkDeleteBtn) bulkDeleteBtn.disabled = !hasSelected;
    if (deleteAllBtn) deleteAllBtn.disabled = !hasAnyRows;
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
  // Confirm modal for top actions
  // --------------------------------------------------
  const confirmModal = $('#midPreConfirmModal');
  const confirmTitle = $('#midPreConfirmTitle');
  const confirmText = $('#midPreConfirmText');
  const confirmYes = $('#midPreConfirmYes');
  const confirmNo = $('#midPreConfirmNo');
  const confirmClose = $('#midPreConfirmClose');

  let confirmResolve = null;

  const openConfirm = ({ title, text, onYes }) => {
    confirmResolve = onYes || null;

    if (confirmTitle) confirmTitle.textContent = title || 'Are you sure?';
    if (confirmText) confirmText.textContent = text || 'Please confirm action.';

    if (confirmModal) {
      confirmModal.classList.add('is-open');
      confirmModal.setAttribute('aria-hidden', 'false');
    }
  };

  const closeConfirm = () => {
    confirmResolve = null;
    if (confirmModal) {
      confirmModal.classList.remove('is-open');
      confirmModal.setAttribute('aria-hidden', 'true');
    }
  };

  if (confirmClose) confirmClose.addEventListener('click', closeConfirm);
  if (confirmNo) confirmNo.addEventListener('click', closeConfirm);

  if (confirmYes) {
    confirmYes.addEventListener('click', async () => {
      const cb = confirmResolve;
      closeConfirm();
      if (typeof cb === 'function') {
        await cb();
      }
    });
  }

  if (confirmModal) {
    confirmModal.addEventListener('click', (e) => {
      if (e.target === confirmModal) closeConfirm();
    });
  }

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

  // --------------------------------------------------
  // Row helpers
  // --------------------------------------------------
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

    let box = cell.querySelector('.mid-pre-resolved-box[data-result="resolved"]');
    if (!box) {
      box = document.createElement('div');
      box.className = 'mid-pre-resolved-box';
      box.setAttribute('data-result', 'resolved');
      box.innerHTML = '<div class="mid-pre-resolved-line">Resolved</div>';
      cell.appendChild(box);
    }
  };

  const markRowRefunded = (row) => {
    if (!row) return;

    const cell = findActionCell(row);
    if (!cell) return;

    ensureBadge(cell, 'Refunded', 'ok', 'refunded');
  };

  // --------------------------------------------------
  // Order refund UI updates
  // --------------------------------------------------
  const updateOrderBlockFromResponse = (orderId, orderData) => {
    if (!orderId || !orderData) return;

    let orderRow = null;
    try {
      orderRow = root.querySelector('.mid-pre-order-row[data-order-id="' + CSS.escape(String(orderId)) + '"]');
    } catch (e) {
      orderRow = root.querySelector('.mid-pre-order-row[data-order-id]');
    }

    if (!orderRow) return;

    const payment = orderData.payment || {};

    const statusEl = $('.mid-pre-order-payment-status', orderRow);
    const refundEls = $$('.mid-pre-order-payment-refund', orderRow);
    const actionBtn = $('.mid-pre-order-refund-btn', orderRow);
    const statusBadge = $('.mid-pre-order-refund-result', orderRow);

    if (statusEl) {
      statusEl.textContent = 'Payment: ' + (payment.status != null && payment.status !== '' ? String(payment.status) : '—');
    }

    if (refundEls.length > 0) {
      const targetRefundEl = refundEls[refundEls.length - 1];
      if (targetRefundEl) {
        targetRefundEl.textContent = formatRefundText(payment);
      }
    }

    if (actionBtn) {
      const refundedAmount = parseFloat(payment.refunded_amount || 0);
      const fullAmount = parseFloat(payment.full_amount || 0);

      if (!isNaN(refundedAmount) && !isNaN(fullAmount) && refundedAmount >= fullAmount && fullAmount > 0) {
        actionBtn.disabled = true;
      }
    }

    if (statusBadge) {
      statusBadge.textContent = 'Refunded';
      statusBadge.style.display = 'inline-block';
    }
  };

  const processSingleOrderRefund = async (orderId, buttonEl) => {
    if (!orderId) return;

    const prevText = buttonEl ? buttonEl.textContent : '';
    if (buttonEl) {
      buttonEl.disabled = true;
      buttonEl.textContent = 'Refunding...';
    }

    try {
      const json = await postFormData(
        ajaxCfg.action_order_refund || 'midigator_related_order_refund',
        { entry_id: orderId }
      );

      if (json && json.success) {
        const orderData = json.data && json.data.order_data ? json.data.order_data : null;
        updateOrderBlockFromResponse(orderId, orderData);
      } else {
        const msg = json && json.data && json.data.message ? json.data.message : 'Refund failed';
        alert(msg);
      }
    } catch (err) {
      alert((err && err.message) ? err.message : 'Refund request failed');
    } finally {
      if (buttonEl) {
        buttonEl.textContent = prevText || 'Refund';

        const row = buttonEl.closest('.mid-pre-order-row');
        const refundEls = $$('.mid-pre-order-payment-refund', row);
        const targetRefundEl = refundEls.length ? refundEls[refundEls.length - 1] : null;

        const refundedFully = targetRefundEl && /To refund:\s*([0-9.]+)\s*\/\s*([0-9.]+)/i.test(targetRefundEl.textContent || '');
        if (!refundedFully) {
          buttonEl.disabled = false;
        } else {
          const m = (targetRefundEl.textContent || '').match(/To refund:\s*([0-9.]+)\s*\/\s*([0-9.]+)/i);
          if (m) {
            const refunded = parseFloat(m[1] || '0');
            const full = parseFloat(m[2] || '0');
            buttonEl.disabled = !(isNaN(refunded) || isNaN(full)) && refunded >= full && full > 0;
          } else {
            buttonEl.disabled = false;
          }
        }
      }
    }
  };

  // --------------------------------------------------
  // Row action: Resolve
  // --------------------------------------------------
  root.addEventListener('click', (e) => {
    const t = e.target;
    if (!t) return;

    if (t.matches('[data-action="approve_row"]')) {
      e.preventDefault();

      const guid = t.getAttribute('data-guid') || '';
      if (!guid) {
        alert('Missing prevention_guid');
        return;
      }

      openModal(guid);
      return;
    }

    if (t.matches('.mid-pre-order-refund-btn[data-order-id]')) {
      e.preventDefault();

      const orderId = parseInt(t.getAttribute('data-order-id') || '0', 10) || 0;
      if (!orderId) {
        alert('Missing order id');
        return;
      }

      openConfirm({
        title: 'Are you sure?',
        text: 'Refund this related order?',
        onYes: async () => {
          await processSingleOrderRefund(orderId, t);
        }
      });

      return;
    }
  });

  // --------------------------------------------------
  // Confirm resolve -> AJAX
  // --------------------------------------------------
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

  // --------------------------------------------------
  // Bulk processor
  // --------------------------------------------------
  const setBulkBusy = (state) => {
    bulkBusy = !!state;

    if (bulkReason) bulkReason.disabled = bulkBusy;
    if (all) all.disabled = bulkBusy;

    getCheckboxes().forEach((cb) => {
      cb.disabled = bulkBusy;
    });

    $$('.mid-pre-order-check').forEach((cb) => {
      cb.disabled = bulkBusy;
    });

    $$('.mid-pre-order-refund-btn').forEach((btn) => {
      btn.disabled = bulkBusy || btn.disabled;
    });

    if (bulkResolveBtn) {
      bulkResolveBtn.disabled = bulkBusy || getSelectedRows().length < 1;
    }

    if (bulkRefundBtn) {
      bulkRefundBtn.disabled = bulkBusy || getSelectedRows().length < 1;
    }

    if (bulkDeleteBtn) {
      bulkDeleteBtn.disabled = bulkBusy || getSelectedRows().length < 1;
    }

    if (deleteAllBtn) {
      deleteAllBtn.disabled = bulkBusy || getAllRows().length < 1;
    }
  };

  const processBulkResolve = async () => {
    if (bulkBusy) return;

    const rows = getSelectedRows();
    if (!rows.length) {
      setBulkStatus('err', 'Select at least one row', 0);
      return;
    }

    const resolveReason = bulkReason ? String(bulkReason.value || '') : '';
    if (!resolveReason) {
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

        setBulkStatus('', 'Resolving ' + (i + 1) + ' of ' + rows.length + '…', progress);

        try {
          const json = await postFormData(
            ajaxCfg.action_bulk_resolve || 'midigator_bulk_resolve_prevention',
            {
              id: row.id,
              prevention_guid: row.prevention_guid,
              resolve_reason: resolveReason
            }
          );

          if (json && json.success) {
            successCount++;
            markRowResolved(row);
            if (row.checkbox) row.checkbox.checked = false;
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
        setBulkStatus('ok', 'Resolve completed. ' + successCount + '/' + rows.length + ' processed.', 100);
      } else {
        setBulkStatus('err', 'Resolve finished with errors. Success: ' + successCount + ', Failed: ' + failCount + '.', 100);
      }
    } finally {
      setBulkBusy(false);
      refreshBulkUiState();
    }
  };

  const processBulkRefund = async () => {
    if (bulkBusy) return;

    const rows = getSelectedRows();
    if (!rows.length) {
      setBulkStatus('err', 'Select at least one row', 0);
      return;
    }

    const jobs = [];

    rows.forEach((row) => {
      const checkedOrders = getCheckedOrderCheckboxesForRow(row);

      if (checkedOrders.length) {
        checkedOrders.forEach((orderCb) => {
          const orderId = parseInt(orderCb.getAttribute('data-order-id') || '0', 10) || 0;
          if (orderId > 0) {
            jobs.push({
              type: 'order',
              row,
              orderId,
              checkbox: orderCb
            });
          }
        });
      } else {
        jobs.push({
          type: 'row',
          row
        });
      }
    });

    if (!jobs.length) {
      setBulkStatus('err', 'Nothing selected for refund', 0);
      return;
    }

    setBulkBusy(true);

    let successCount = 0;
    let failCount = 0;

    try {
      for (let i = 0; i < jobs.length; i++) {
        const job = jobs[i];
        const progress = Math.round((i / jobs.length) * 100);

        setBulkStatus('', 'Refunding ' + (i + 1) + ' of ' + jobs.length + '…', progress);

        try {
          if (job.type === 'order') {
            const json = await postFormData(
              ajaxCfg.action_order_refund || 'midigator_related_order_refund',
              { entry_id: job.orderId }
            );

            if (json && json.success) {
              successCount++;
              const orderData = json.data && json.data.order_data ? json.data.order_data : null;
              updateOrderBlockFromResponse(job.orderId, orderData);
              if (job.checkbox) job.checkbox.checked = false;
            } else {
              failCount++;
            }
          } else {
            const json = await postFormData(
              ajaxCfg.action_bulk_refund || 'midigator_bulk_full_refund_prevention',
              {
                id: job.row.id,
                prevention_guid: job.row.prevention_guid
              }
            );

            if (json && json.success) {
              successCount++;
              markRowRefunded(job.row);
              if (job.row.checkbox) job.row.checkbox.checked = false;
            } else {
              failCount++;
            }
          }
        } catch (err) {
          failCount++;
        }

        refreshBulkUiState();
        await sleep(80);
      }

      if (failCount < 1) {
        setBulkStatus('ok', 'Refund completed. ' + successCount + '/' + jobs.length + ' processed.', 100);
      } else {
        setBulkStatus('err', 'Refund finished with errors. Success: ' + successCount + ', Failed: ' + failCount + '.', 100);
      }
    } finally {
      setBulkBusy(false);
      refreshBulkUiState();
    }
  };

  const processBulkDelete = async () => {
    if (bulkBusy) return;
  
    const rows = getSelectedRows();
  
    if (!rows.length) {
      setBulkStatus('err', 'Select at least one row', 0);
      return;
    }
  
    const ids = rows.map((row) => row.id).filter((id) => id > 0);
    const guids = rows.map((row) => row.prevention_guid).filter((guid) => !!guid);
  
    if (!ids.length || !guids.length) {
      setBulkStatus('err', 'Nothing to delete', 0);
      return;
    }
  
    setBulkBusy(true);
    setBulkStatus('', 'Deleting ' + rows.length + ' row(s)…', 30);
  
    try {
      const json = await postFormData(
        ajaxCfg.action_bulk_delete || 'midigator_bulk_delete_prevention',
        {
          ids: ids,
          prevention_guids: guids
        }
      );
  
      if (json && json.success) {
        setBulkStatus('ok', 'Delete completed. Reloading...', 100);
        await sleep(250);
        reloadPage();
      } else {
        const msg = (json && json.data && json.data.message) ? json.data.message : 'Delete failed';
        setBulkStatus('err', msg, 100);
      }
    } catch (err) {
      setBulkStatus('err', (err && err.message) ? err.message : 'Delete request failed', 100);
    } finally {
      setBulkBusy(false);
      refreshBulkUiState();
    }
  };
  
  const processDeleteAll = async () => {
    if (bulkBusy) return;
  
    const rows = getAllRows();
  
    if (!rows.length) {
      setBulkStatus('err', 'No rows to delete', 0);
      return;
    }
  
    const ids = rows.map((row) => row.id).filter((id) => id > 0);
    const guids = rows.map((row) => row.prevention_guid).filter((guid) => !!guid);
  
    if (!ids.length || !guids.length) {
      setBulkStatus('err', 'Nothing to delete', 0);
      return;
    }
  
    setBulkBusy(true);
    setBulkStatus('', 'Deleting all rows on this page…', 30);
  
    try {
      const json = await postFormData(
        ajaxCfg.action_delete_all || 'midigator_delete_all_preventions',
        {
          ids: ids,
          prevention_guids: guids
        }
      );
  
      if (json && json.success) {
        setBulkStatus('ok', 'Delete all completed. Reloading...', 100);
        await sleep(250);
        reloadPage();
      } else {
        const msg = (json && json.data && json.data.message) ? json.data.message : 'Delete all failed';
        setBulkStatus('err', msg, 100);
      }
    } catch (err) {
      setBulkStatus('err', (err && err.message) ? err.message : 'Delete all request failed', 100);
    } finally {
      setBulkBusy(false);
      refreshBulkUiState();
    }
  };

  if (bulkResolveBtn) {
    bulkResolveBtn.addEventListener('click', () => {
      const rows = getSelectedRows();
      if (!rows.length) {
        setBulkStatus('err', 'Select at least one row', 0);
        return;
      }

      openConfirm({
        title: 'Are you sure?',
        text: 'Resolve selected rows?',
        onYes: async () => {
          await processBulkResolve();
        }
      });
    });
  }

  if (bulkRefundBtn) {
    bulkRefundBtn.addEventListener('click', () => {
      const rows = getSelectedRows();
      if (!rows.length) {
        setBulkStatus('err', 'Select at least one row', 0);
        return;
      }

      openConfirm({
        title: 'Are you sure?',
        text: 'Run refund for selected rows/orders?',
        onYes: async () => {
          await processBulkRefund();
        }
      });
    });
  }

  if (bulkDeleteBtn) {
    bulkDeleteBtn.addEventListener('click', () => {
      const rows = getSelectedRows();
      if (!rows.length) {
        setBulkStatus('err', 'Select at least one row', 0);
        return;
      }
  
      openConfirm({
        title: 'Are you sure?',
        text: 'Delete selected rows?',
        onYes: async () => {
          await processBulkDelete();
        }
      });
    });
  }

  if (deleteAllBtn) {
    deleteAllBtn.addEventListener('click', () => {
      const rows = getAllRows();
      if (!rows.length) {
        setBulkStatus('err', 'No rows to delete', 0);
        return;
      }
  
      openConfirm({
        title: 'Are you sure?',
        text: 'Delete all rows on this page?',
        onYes: async () => {
          await processDeleteAll();
        }
      });
    });
  }

  refreshBulkUiState();
})();