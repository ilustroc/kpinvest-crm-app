// resources/js/clientes/show/selection.js
export function initSelection() {
  const chkAll   = document.getElementById('chkAll');
  const chks     = Array.from(document.querySelectorAll('.chkOp'));
  const btnProp  = document.getElementById('btnPropuesta');
  const selCount = document.getElementById('selCount');

  const refresh = () => {
    if (!chks.length) return [];
    const selected = chks
      .filter((c) => c.checked && !c.disabled)
      .map((c) => c.value)
      .filter(Boolean);

    if (selCount) selCount.textContent = String(selected.length);
    if (btnProp)  btnProp.disabled = (selected.length === 0);
    return selected;
  };

  if (chkAll) {
    chkAll.addEventListener('change', () => {
      chks.forEach((c) => { if (!c.disabled) c.checked = chkAll.checked; });
      refresh();
    });
  }

  chks.forEach((c) =>
    c.addEventListener('change', () => {
      const enabled = chks.filter((x) => !x.disabled).length;
      const checked = chks.filter((x) => x.checked && !x.disabled).length;
      if (chkAll && enabled) chkAll.checked = (checked === enabled);
      refresh();
    })
  );

  refresh();
  return { refresh };
}