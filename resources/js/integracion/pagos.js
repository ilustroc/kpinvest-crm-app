(function () {
  // Encabezados EXACTOS
  const HEADERS = [
    "Fecha",
    "DNI",
    "Nombre",
    "Operacion",
    "Monto",
    "Agente",
    "Cosecha",
    "Cuenta_Recaudo",
    "Entidad Financiera",
  ];

  const $file = document.getElementById("csvFilePagos");
  const $btn = document.getElementById("btnImportPagos");
  const $box = document.getElementById("precheckBoxPagos");
  const $hdr = document.getElementById("hdrMsgPagos");
  const $typ = document.getElementById("typeMsgPagos");
  const $wrap = document.getElementById("issuesWrapPagos");
  const $body = document.getElementById("issuesBodyPagos");

  if (!$file || !$btn) return;

  function esc(s) {
    return String(s ?? "").replace(/</g, "&lt;");
  }

  function splitCSV(line, sep) {
    const out = [];
    let cur = "";
    let q = false;

    for (let i = 0; i < line.length; i++) {
      const c = line[i];
      if (c === '"') q = !q;
      else if (c === sep && !q) {
        out.push(cur);
        cur = "";
      } else cur += c;
    }
    out.push(cur);

    return out.map((s) =>
      s.replace(/^\uFEFF/, "").replace(/^"|"$/g, "").trim()
    );
  }

  function parseCSV(text) {
    text = (text || "").replace(/\r/g, "");
    const lines = text.split(/\n+/).filter(Boolean);
    if (!lines.length) return { rows: [], sep: "," };

    const a = lines[0].split(";");
    const b = lines[0].split(",");
    const sep = a.length > b.length ? ";" : ",";

    return { rows: lines.map((l) => splitCSV(l, sep)), sep };
  }

  const isNumber = (v) => {
    if (v === "" || v == null) return true;
    const x = String(v).replace(/\s/g, "").replace(/,/g, ".");
    return /^-?\d+(\.\d+)?$/.test(x);
  };

  const parseDate = (v) => {
    if (!v) return null;
    const s = String(v).trim();

    let m = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (m) return s;

    m = s.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (m) return `${m[3]}-${m[2]}-${m[1]}`;

    return null;
  };

  const isDate = (v) => v === "" || parseDate(v) != null;

  function show(el) {
    el?.classList.remove("hidden");
  }
  function hide(el) {
    el?.classList.add("hidden");
  }

  $file.addEventListener("change", (ev) => {
    const file = ev.target.files?.[0];
    if (!file) {
      $btn.disabled = true;
      hide($box);
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      const { rows } = parseCSV(e.target.result || "");
      if (!rows.length) {
        $btn.disabled = true;
        hide($box);
        return;
      }

      show($box);
      hide($wrap);
      $body.innerHTML = "";

      const header = rows[0].map((h) => String(h ?? "").trim());

      const missing = HEADERS.filter((h) => !header.includes(h));
      const extra = header.filter((h) => !HEADERS.includes(h));
      const headerOk = missing.length === 0;

      if (headerOk) {
        $hdr.innerHTML = `Encabezados: OK (<span class="font-bold text-emerald-700">${header.length}</span>)`;
      } else {
        $hdr.innerHTML =
          `Encabezados: faltan <span class="font-bold text-rose-700">${esc(missing.join(", ") || "-")}</span>` +
          (extra.length
            ? `, extra: <span class="font-bold text-amber-700">${esc(extra.join(", "))}</span>`
            : "");
      }

      let issues = [];
      let sampled = 0;

      for (let r = 1; r < Math.min(rows.length, 51); r++) {
        const row = rows[r];
        if (!row || !row.length) continue;
        sampled++;

        HEADERS.forEach((h, idx) => {
          const val = String(row[idx] ?? "").trim();
          let ok = true,
            detail = "";

          if (h === "Fecha") {
            ok = isDate(val);
            if (!ok) detail = "Fecha inválida. Use YYYY-MM-DD o DD/MM/YYYY";
          } else if (h === "Monto") {
            ok = isNumber(val);
            if (!ok) detail = "Número inválido";
          }

          if (!ok) issues.push({ r: r + 1, col: h, val, detail });
        });
      }

      $typ.textContent = `Tipos por muestra: ${sampled} fila(s) verificadas, ${issues.length} posible(s) problema(s)`;

      if (issues.length) {
        show($wrap);
        $body.innerHTML = issues
          .slice(0, 80)
          .map(
            (it) =>
              `<tr class="hover:bg-amber-50/40">
                <td class="px-3 py-2 whitespace-nowrap">${it.r}</td>
                <td class="px-3 py-2 whitespace-nowrap">${esc(it.col)}</td>
                <td class="px-3 py-2">${esc(it.val)}</td>
                <td class="px-3 py-2 text-slate-600">${esc(it.detail)}</td>
              </tr>`
          )
          .join("");
      }

      $btn.disabled = !headerOk;
    };

    reader.readAsText(file, "UTF-8");
  });
})();