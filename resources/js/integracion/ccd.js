(function () {
  const HEADERS = ["dni", "nombre", "cartera", "pdf"];

  const $file = document.getElementById("csvFileCCD");
  const $btn  = document.getElementById("btnImportCCD");
  const $box  = document.getElementById("precheckBoxCCD");
  const $hdr  = document.getElementById("hdrMsgCCD");
  const $typ  = document.getElementById("typeMsgCCD");
  const $wrap = document.getElementById("issuesWrapCCD");
  const $body = document.getElementById("issuesBodyCCD");

  if (!$file || !$btn) return;

  const esc = (s) => String(s ?? "").replace(/</g, "&lt;");

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

  const show = (el) => el?.classList.remove("hidden");
  const hide = (el) => el?.classList.add("hidden");

  const isDni = (v) => {
    const s = String(v ?? "").trim();
    if (!s) return false;
    if (!/^\d+$/.test(s)) return false;
    return s.length >= 7 && s.length <= 12; // ajusta si deseas
  };

  const isPdf = (v) => {
    const s = String(v ?? "").trim().toLowerCase();
    if (!s) return false;
    return s.endsWith(".pdf");
  };

  $file.addEventListener("change", (ev) => {
    const file = ev.target.files?.[0];
    if (!file) { $btn.disabled = true; hide($box); return; }

    const reader = new FileReader();
    reader.onload = (e) => {
      const { rows } = parseCSV(e.target.result || "");
      if (!rows.length) { $btn.disabled = true; hide($box); return; }

      show($box); hide($wrap); $body.innerHTML = "";

      const header = rows[0].map(h => String(h ?? "").trim().toLowerCase());
      const missing = HEADERS.filter(h => !header.includes(h));
      const extra   = header.filter(h => !HEADERS.includes(h));
      const headerOk = missing.length === 0;

      $hdr.innerHTML = headerOk
        ? `Encabezados: OK (<span class="font-bold text-emerald-700">${header.length}</span>)`
        : `Encabezados: faltan <span class="font-bold text-rose-700">${esc(missing.join(", ") || "-")}</span>`
          + (extra.length ? `, extra: <span class="font-bold text-amber-700">${esc(extra.join(", "))}</span>` : "");

      // índices por nombre (por si vienen desordenados)
      const idx = Object.fromEntries(header.map((h, i) => [h, i]));

      let issues = [];
      let sampled = 0;

      for (let r = 1; r < Math.min(rows.length, 51); r++) {
        const row = rows[r];
        if (!row || !row.length) continue;
        sampled++;

        const dni = row[idx["dni"]] ?? "";
        const pdf = row[idx["pdf"]] ?? "";

        if (String(dni).trim() && !isDni(dni)) {
          issues.push({ r: r + 1, col: "dni", val: dni, detail: "DNI inválido (solo números, 7–12 dígitos)" });
        }
        if (String(pdf).trim() && !isPdf(pdf)) {
          issues.push({ r: r + 1, col: "pdf", val: pdf, detail: "Debe terminar en .pdf" });
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