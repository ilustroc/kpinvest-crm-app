(function () {
  // Encabezados mínimos requeridos (puedes agregar más si quieres)
  const REQUIRED = ["NUMDOC", "OPERACION", "CUENTA", "ENTIDAD", "DPTO"];

  const $file = document.getElementById("csvFileData");
  const $btn  = document.getElementById("btnImportData");
  const $box  = document.getElementById("precheckBoxData");
  const $hdr  = document.getElementById("hdrMsgData");
  const $typ  = document.getElementById("typeMsgData");
  const $wrap = document.getElementById("issuesWrapData");
  const $body = document.getElementById("issuesBodyData");

  if (!$file || !$btn) return;

  const esc = (s) => String(s ?? "").replace(/</g, "&lt;");
  const show = (el) => el?.classList.remove("hidden");
  const hide = (el) => el?.classList.add("hidden");

  function splitCSV(line, sep) {
    const out = [];
    let cur = "", q = false;
    for (let i = 0; i < line.length; i++) {
      const c = line[i];
      if (c === '"') q = !q;
      else if (c === sep && !q) { out.push(cur); cur = ""; }
      else cur += c;
    }
    out.push(cur);
    return out.map(s => s.replace(/^\uFEFF/, "").replace(/^"|"$/g, "").trim());
  }

  function parseCSV(text) {
    text = (text || "").replace(/\r/g, "");
    const lines = text.split(/\n+/).filter(Boolean);
    if (!lines.length) return { rows: [] };
    const sep = (lines[0].split(";").length > lines[0].split(",").length) ? ";" : ",";
    return { rows: lines.map(l => splitCSV(l, sep)) };
  }

  const isDni = (v) => {
    const s = String(v ?? "").trim();
    if (!s) return false;
    if (!/^\d+$/.test(s)) return false;
    return s.length >= 7 && s.length <= 12;
  };

  $file.addEventListener("change", (ev) => {
    const file = ev.target.files?.[0];
    if (!file) { $btn.disabled = true; hide($box); return; }

    const reader = new FileReader();
    reader.onload = (e) => {
      const { rows } = parseCSV(e.target.result || "");
      if (!rows.length) { $btn.disabled = true; hide($box); return; }

      show($box); hide($wrap); $body.innerHTML = "";

      // headers (respetamos mayúsculas)
      const headerRaw = rows[0].map(h => String(h ?? "").trim());
      const headerSet = new Set(headerRaw);

      const missing = REQUIRED.filter(h => !headerSet.has(h));
      const headerOk = missing.length === 0;

      $hdr.innerHTML = headerOk
        ? `Encabezados mínimos: OK (<span class="font-bold text-emerald-700">${headerRaw.length}</span>)`
        : `Faltan: <span class="font-bold text-rose-700">${esc(missing.join(", ") || "-")}</span>`;

      // indices
      const idx = {};
      headerRaw.forEach((h, i) => { if (!(h in idx)) idx[h] = i; });

      let issues = [];
      let sampled = 0;

      for (let r = 1; r < Math.min(rows.length, 51); r++) {
        const row = rows[r];
        if (!row || !row.length) continue;
        sampled++;

        const numdoc = row[idx["NUMDOC"]] ?? "";
        const oper   = row[idx["OPERACION"]] ?? "";
        const cuenta = row[idx["CUENTA"]] ?? "";

        if (String(numdoc).trim() && !isDni(numdoc)) {
          issues.push({ r: r + 1, col: "NUMDOC", val: numdoc, detail: "NUMDOC inválido (solo números, 7–12 dígitos)" });
        }
        if (!String(oper).trim()) {
          issues.push({ r: r + 1, col: "OPERACION", val: oper, detail: "Campo requerido vacío" });
        }
        if (!String(cuenta).trim()) {
          issues.push({ r: r + 1, col: "CUENTA", val: cuenta, detail: "Campo requerido vacío" });
        }
      }

      $typ.textContent = `Muestra: ${sampled} fila(s) verificadas, ${issues.length} posible(s) problema(s)`;

      if (issues.length) {
        show($wrap);
        $body.innerHTML = issues.slice(0, 80).map(it => `
          <tr class="hover:bg-amber-50/40">
            <td class="px-3 py-2 whitespace-nowrap">${it.r}</td>
            <td class="px-3 py-2 whitespace-nowrap">${esc(it.col)}</td>
            <td class="px-3 py-2">${esc(it.val)}</td>
            <td class="px-3 py-2 text-slate-600">${esc(it.detail)}</td>
          </tr>
        `).join("");
      }

      $btn.disabled = !headerOk;
    };

    reader.readAsText(file, "UTF-8");
  });
})();