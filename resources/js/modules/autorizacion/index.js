import { $, $$, escapeHtml, ready } from "../../core/dom";
import { setupModals } from "../../core/modal";
import { formatMoney } from "../../core/money";

function money(value) {
    return formatMoney(value, "PEN");
}

function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = value || "-";
    }
}

function parseJsonAttribute(element, name, fallback = []) {
    try {
        return JSON.parse(
            element.getAttribute(name) || JSON.stringify(fallback),
        );
    } catch (_) {
        return fallback;
    }
}

function parseBase64JsonAttribute(element, name, fallback = []) {
    const encoded = element.getAttribute(name);

    if (!encoded) {
        return fallback;
    }

    try {
        const binary = atob(encoded);
        const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
        const decoded = new TextDecoder().decode(bytes);

        return JSON.parse(decoded);
    } catch (_) {
        return fallback;
    }
}

function toDmy(value) {
    if (!value) return "-";

    const text = String(value).trim();
    const datePart = text.split(" ")[0];
    const separator = datePart.includes("-") ? "-" : "/";
    const [year, month, day] = datePart.split(separator);

    if (year && month && day) {
        return `${day.padStart(2, "0")}/${month.padStart(2, "0")}/${year}`;
    }

    return text;
}

function setupDecisionModals(root) {
    $$(".js-open-nota", root).forEach((button) => {
        button.addEventListener("click", () => {
            const form = $("#formNotaEstado");
            const title = $("#modalNotaEstadoTitulo");
            const textarea = $("#notaEstadoTxt");

            form?.setAttribute("action", button.dataset.action || "#");

            if (title) {
                title.textContent = button.dataset.title || "Aprobar solicitud";
            }

            if (textarea) {
                textarea.value = "";
                setTimeout(() => textarea.focus(), 120);
            }
        });
    });

    $$(".js-open-rechazo", root).forEach((button) => {
        button.addEventListener("click", () => {
            const form = $("#formRechazo");
            const title = $("#modalRechazoTitulo");
            const textarea = $("#motivoTxt");

            form?.setAttribute("action", button.dataset.action || "#");

            if (title) {
                title.textContent =
                    button.dataset.title || "Rechazar solicitud";
            }

            if (textarea) {
                textarea.value = "";
                setTimeout(() => textarea.focus(), 120);
            }
        });
    });
}

function accountsFromOperationText(operationText) {
    return String(operationText || "")
        .split(",")
        .map((operation) => operation.trim())
        .filter(Boolean)
        .map((operation) => ({
            operacion: operation,
            entidad: "",
            producto: "",
            cosecha: "",
            saldo_capital: 0,
            deuda_total: 0,
        }));
}

function uniqueOperations(values) {
    return [
        ...new Set(
            (values || [])
                .map((value) => String(value || "").trim())
                .filter(Boolean),
        ),
    ];
}

function uniqueValues(values) {
    return [
        ...new Set(
            (values || [])
                .map((value) => String(value || "").trim())
                .filter(Boolean),
        ),
    ];
}

function operationsFromPayments(payments) {
    return uniqueOperations(
        (payments || []).map((payment) => payment?.operacion ?? payment?.oper),
    );
}

function renderAccounts(accounts, operationText = "") {
    const container = $("#acc_cuentas");

    if (!container) return;

    const visibleAccounts =
        Array.isArray(accounts) && accounts.length
            ? accounts
            : accountsFromOperationText(operationText);

    container.innerHTML = visibleAccounts
        .map((account, index) => {
            const year = account?.fecha_castigo
                ? String(account.fecha_castigo).slice(0, 4)
                : "-";

            return `
            <details class="overflow-hidden rounded-lg border border-kp-border bg-white" ${index === 0 ? "open" : ""}>
                <summary class="list-none cursor-pointer px-3.5 py-3 transition-colors hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
                    <span class="block text-[13px] font-semibold text-kp-ink">Operación ${escapeHtml(account?.operacion || "-")}</span>
                    <span class="mt-0.5 block text-[11px] text-kp-muted">${escapeHtml(account?.entidad || "-")} · ${escapeHtml(account?.cosecha || "-")}</span>
                </summary>
                <dl class="grid gap-2 border-t border-kp-border p-3 sm:grid-cols-2">
                    <div class="rounded-md bg-slate-50 px-2.5 py-2"><dt class="text-[10px] font-semibold uppercase tracking-[0.04em] text-kp-muted">Año castigo</dt><dd class="mt-0.5 text-[12px] font-medium text-kp-ink">${escapeHtml(year)}</dd></div>
                    <div class="rounded-md bg-slate-50 px-2.5 py-2"><dt class="text-[10px] font-semibold uppercase tracking-[0.04em] text-kp-muted">Entidad</dt><dd class="mt-0.5 text-[12px] font-medium text-kp-ink">${escapeHtml(account?.entidad || "-")}</dd></div>
                    <div class="rounded-md bg-slate-50 px-2.5 py-2"><dt class="text-[10px] font-semibold uppercase tracking-[0.04em] text-kp-muted">Producto</dt><dd class="mt-0.5 text-[12px] font-medium text-kp-ink">${escapeHtml(account?.producto || "-")}</dd></div>
                    <div class="rounded-md bg-slate-50 px-2.5 py-2"><dt class="text-[10px] font-semibold uppercase tracking-[0.04em] text-kp-muted">Cosecha</dt><dd class="mt-0.5 text-[12px] font-medium text-kp-ink">${escapeHtml(account?.cosecha || "-")}</dd></div>
                    <div class="rounded-md bg-slate-50 px-2.5 py-2"><dt class="text-[10px] font-semibold uppercase tracking-[0.04em] text-kp-muted">Capital</dt><dd class="mt-0.5 text-[12px] font-medium tabular-nums text-kp-ink">${money(account?.saldo_capital)}</dd></div>
                    <div class="rounded-md bg-slate-50 px-2.5 py-2"><dt class="text-[10px] font-semibold uppercase tracking-[0.04em] text-kp-muted">Deuda total</dt><dd class="mt-0.5 text-[12px] font-medium tabular-nums text-kp-ink">${money(account?.deuda_total)}</dd></div>
                </dl>
            </details>
        `;
        })
        .join("");
}

function renderSchedule(button, type) {
    const wrapper = $("#crono_wrap");
    const body = $("#crono_body");
    const total = $("#crono_total");
    const title = $("#crono_titulo");

    if (!wrapper || !body || !total || !title) return;

    const schedule = parseBase64JsonAttribute(
        button,
        "data-crono-b64",
        parseJsonAttribute(button, "data-crono", []),
    );

    if (type === "cancelacion") {
        wrapper.classList.add("hidden");
        return;
    }

    if (!schedule.length) {
        wrapper.classList.add("hidden");
        body.innerHTML = "";
        total.textContent = money(0);
        return;
    }

    const hasBalloon =
        button.dataset.hasbalon === "1" ||
        schedule.some(
            (row) =>
                row?.es_balon === true ||
                row?.es_balon === 1 ||
                row?.es_balon === "1",
        );

    title.textContent = hasBalloon
        ? "Cronograma de cuotas (con balón)"
        : "Cronograma de cuotas";

    let sum = 0;

    body.innerHTML = schedule
        .map((row) => {
            const amount = Number(row?.monto || 0);
            sum += amount;

            return `
            <tr class="transition-colors hover:bg-slate-50/70">
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-2.5 text-center text-[12px] tabular-nums text-slate-500">${escapeHtml(String(row?.nro ?? "").padStart(2, "0"))}</td>
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-2.5 text-[12px] tabular-nums text-slate-600">${escapeHtml(toDmy(row?.fecha))}</td>
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-2.5 text-right text-[12px] font-medium tabular-nums text-kp-ink">${money(amount)}</td>
                <td class="whitespace-nowrap border-t border-kp-border px-4 py-2.5 text-[11px] font-semibold text-slate-500">${escapeHtml(row?.es_balon ? "BALÓN" : "")}</td>
            </tr>
        `;
        })
        .join("");

    total.textContent = money(sum);
    wrapper.classList.remove("hidden");
}

function setupPromiseDetail(root) {
    $$(".js-ver-ficha", root).forEach((button) => {
        button.addEventListener("click", () => {
            const type = (button.dataset.tipo || "").toLowerCase();

            setText("f_dni", button.dataset.dni || "-");
            setText("f_op", button.dataset.operacion || "-");
            setText("t_fecha", button.dataset.fecha || "-");
            setText(
                "t_tipo",
                type
                    ? type === "cancelacion"
                        ? "Cancelación"
                        : "Convenio"
                    : "-",
            );
            setText("t_asesor", button.dataset.asesor || "-");
            setText("t_titular", button.dataset.titular || "-");
            setText("t_deuda", money(button.dataset.deuda));
            setText(
                "t_capital",
                money(button.dataset.capital || button.dataset.capitalRaw),
            );
            setText("t_neg", money(button.dataset.negociado));
            setText("t_estado", button.dataset.estado || "-");

            const noteGeneral =
                [
                    button.dataset.notaGen,
                    button.dataset.detalle,
                    button.dataset.nota,
                ]
                    .map((value) => (value || "").trim())
                    .find(Boolean) || "";
            const noteSupervisor =
                [button.dataset.notaSup, button.dataset.notaPreaprobacion]
                    .map((value) => (value || "").trim())
                    .find(Boolean) || "";

            const generalWrapper = $("#nota_general_wrap");
            const supervisorWrapper = $("#nota_sup_wrap");
            const emptyNote = $("#nota_empty");

            if (generalWrapper) {
                generalWrapper.classList.toggle("hidden", !noteGeneral);
                setText("nota_general_txt", noteGeneral);
            }

            if (supervisorWrapper) {
                supervisorWrapper.classList.toggle("hidden", !noteSupervisor);
                setText("nota_sup_txt", noteSupervisor);
            }

            if (emptyNote) {
                emptyNote.classList.toggle(
                    "hidden",
                    Boolean(noteGeneral || noteSupervisor),
                );
            }

            const accounts = parseBase64JsonAttribute(
                button,
                "data-cuentas-b64",
                parseJsonAttribute(button, "data-cuentas", []),
            );

            renderAccounts(accounts, button.dataset.operacion || "");
            renderSchedule(button, type);
        });
    });
}

function renderEmptyPayments(message, tone = "muted") {
    const body = $("#cna_pagos_tbody");
    const color = tone === "danger" ? "text-red-700" : "text-kp-muted";

    if (!body) return;

    body.innerHTML = `<tr><td colspan="7" class="border-t border-kp-border px-4 py-6 text-center text-[12px] ${color}">${escapeHtml(message)}</td></tr>`;
}

function badgeVariantForStatus(status) {
    const normalized = String(status || "").toLowerCase();

    if (normalized.includes("aprob") && !normalized.includes("pre"))
        return "success";
    if (normalized.includes("pre")) return "info";
    if (normalized.includes("rechaz")) return "danger";
    if (normalized.includes("pend")) return "warning";

    return "neutral";
}

function setBadgeVariant(element, variant) {
    if (!element) return;

    const variants = {
        neutral: "border-slate-200 bg-slate-50 text-slate-600",
        primary:
            "border-kp-primary-border bg-kp-primary-soft text-kp-primary-ink",
        success:
            "border-kp-primary-border bg-kp-success-soft text-kp-success-ink",
        danger: "border-red-200 bg-red-50 text-red-700",
        warning: "border-amber-200 bg-amber-50 text-amber-700",
        info: "border-kp-secondary-border bg-kp-secondary-soft text-kp-secondary-dark",
    };

    element.className = `inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-[0.04em] ${variants[variant] || variants.neutral}`;
}

function renderCnaTimeline(button) {
    const container = $("#cna_flujo");

    if (!container) return;

    const status = String(button.dataset.estado || "").toLowerCase();
    const steps = [];
    const requestedBy = (button.dataset.solicitante || "").trim();
    const requestedAt = toDmy(button.dataset.fecha);

    steps.push({
        title: "Solicitud registrada",
        detail:
            [requestedBy, requestedAt !== "-" ? requestedAt : ""]
                .filter(Boolean)
                .join(" · ") || "Registrada",
        tone: "primary",
    });

    const preBy = (button.dataset.preaprobadoPor || "").trim();
    const preAt = toDmy(button.dataset.preaprobadoAt);

    if (
        preBy ||
        preAt !== "-" ||
        status.includes("pre") ||
        (status.includes("aprob") && !status.includes("pre"))
    ) {
        steps.push({
            title: "Preaprobación",
            detail:
                [preBy, preAt !== "-" ? preAt : ""]
                    .filter(Boolean)
                    .join(" · ") || "Registrada",
            tone: "info",
        });
    }

    const approvedBy = (button.dataset.aprobadoPor || "").trim();
    const approvedAt = toDmy(button.dataset.aprobadoAt);

    if (
        approvedBy ||
        approvedAt !== "-" ||
        (status.includes("aprob") && !status.includes("pre"))
    ) {
        steps.push({
            title: "Aprobación",
            detail:
                [approvedBy, approvedAt !== "-" ? approvedAt : ""]
                    .filter(Boolean)
                    .join(" · ") || "Registrada",
            tone: "success",
        });
    }

    const rejectedBy = (button.dataset.rechazadoPor || "").trim();
    const rejectedAt = toDmy(button.dataset.rechazadoAt);
    const rejectionReason = (button.dataset.motivoRechazo || "").trim();

    if (rejectedBy || rejectedAt !== "-" || status.includes("rechaz")) {
        steps.push({
            title: "Rechazo",
            detail:
                [rejectedBy, rejectedAt !== "-" ? rejectedAt : ""]
                    .filter(Boolean)
                    .join(" · ") || "Registrado",
            note: rejectionReason,
            tone: "danger",
        });
    }

    if (steps.length === 1) {
        steps.push({
            title: "Revisión pendiente",
            detail: "La solicitud espera la decisión del siguiente nivel.",
            tone: "muted",
        });
    }

    const tones = {
        primary: "bg-kp-primary",
        info: "bg-kp-secondary",
        success: "bg-kp-primary",
        danger: "bg-red-500",
        muted: "bg-slate-300",
    };

    container.innerHTML = steps
        .map(
            (step) => `
        <li class="relative flex gap-3 pb-3 last:pb-0">
            <span class="mt-1.5 size-2 shrink-0 rounded-full ${tones[step.tone] || tones.muted}" aria-hidden="true"></span>
            <div class="min-w-0">
                <p class="text-[12px] font-semibold text-kp-ink">${escapeHtml(step.title)}</p>
                <p class="mt-0.5 text-[11px] text-kp-muted">${escapeHtml(step.detail)}</p>
                ${step.note ? `<p class="mt-1 whitespace-pre-line rounded-md border border-red-200 bg-red-50 px-2.5 py-1.5 text-[11px] leading-5 text-red-700">${escapeHtml(step.note)}</p>` : ""}
            </div>
        </li>
    `,
        )
        .join("");
}

function renderCnaDocument(button) {
    const panel = $("#cna_document_panel");

    if (!panel) return;

    const selector = (button.dataset.docTemplate || "").trim();
    const template = selector ? document.querySelector(selector) : null;

    panel.innerHTML = template
        ? template.innerHTML
        : '<div class="rounded-lg border border-dashed border-kp-border bg-slate-50 px-3.5 py-3 text-[12px] text-kp-muted">Sin información documental disponible.</div>';
}

function setCnaPaymentContext(payments, cnaOperations = null) {
    const count = $("#cna_pagos_count");
    const context = $("#cna_pagos_context");
    const entityCosecha = $("#cna_entidad_cosecha");
    const allRows = Array.isArray(payments) ? payments : [];
    const operationSet =
        cnaOperations instanceof Set ? cnaOperations : new Set();
    const hasCnaOperations = operationSet.size > 0;
    const matchingRows = hasCnaOperations
        ? allRows.filter((payment) =>
              operationSet.has(
                  String(payment?.operacion ?? payment?.oper ?? "").trim(),
              ),
          )
        : allRows;
    const entities = uniqueValues(
        matchingRows.map((payment) => payment?.entidad),
    );
    const harvests = uniqueValues(
        matchingRows.map((payment) => payment?.cosecha),
    );

    if (count) {
        count.textContent = `${allRows.length} ${allRows.length === 1 ? "pago" : "pagos"}`;
    }

    if (entityCosecha) {
        if (hasCnaOperations && matchingRows.length === 0) {
            entityCosecha.textContent =
                "Sin pagos de las operaciones de esta CNA";
            entityCosecha.title =
                "No hay pagos registrados para las operaciones de esta solicitud";
        } else {
            const entityText =
                entities.length === 0
                    ? "Sin entidad"
                    : entities.length === 1
                      ? entities[0]
                      : "Varias entidades";
            const harvestText =
                harvests.length === 0
                    ? "Sin cosecha"
                    : harvests.length === 1
                      ? harvests[0]
                      : "Varias cosechas";

            entityCosecha.textContent = `${entityText} · ${harvestText}`;
            entityCosecha.title =
                [entities.join(", "), harvests.join(", ")]
                    .filter(Boolean)
                    .join(" · ") || "Sin datos de pagos";
        }
    }

    if (context) {
        if (!allRows.length) {
            context.textContent = "Sin pagos registrados para este DNI.";
        } else if (!hasCnaOperations) {
            context.textContent = `${allRows.length} pagos del DNI. Histórico completo del cliente.`;
        } else if (matchingRows.length) {
            context.textContent = `${allRows.length} pagos del DNI. Entidad y cosecha según las operaciones de esta CNA.`;
        } else {
            context.textContent = `${allRows.length} pagos del DNI. No hay pagos de las operaciones de esta CNA; se muestra el histórico completo.`;
        }
    }
}

function setCnaPaymentContextError() {
    const count = $("#cna_pagos_count");
    const context = $("#cna_pagos_context");
    const entityCosecha = $("#cna_entidad_cosecha");

    if (count) count.textContent = "—";
    if (context)
        context.textContent = "No se pudieron cargar los pagos del DNI.";
    if (entityCosecha) {
        entityCosecha.textContent = "No disponible";
        entityCosecha.title = "No se pudieron cargar los pagos del DNI";
    }
}

function addPaymentRow(payment) {
    const body = $("#cna_pagos_tbody");

    if (!body) return;

    const operation =
        String(payment.operacion ?? payment.oper ?? "-").trim() || "-";

    body.insertAdjacentHTML(
        "beforeend",
        `
        <tr class="transition-colors hover:bg-slate-50/70">
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-2.5 font-mono text-[12px] text-slate-600">${escapeHtml(operation)}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-2.5 text-[12px] tabular-nums text-slate-600">${escapeHtml(toDmy(payment.fecha))}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-2.5 text-right text-[12px] font-medium tabular-nums text-kp-ink">${money(payment.monto_pagado ?? payment.monto ?? 0)}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-2.5 text-[12px] text-slate-600">${escapeHtml(payment.gestor ?? "-")}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-2.5 text-[12px] text-slate-600">${escapeHtml(payment.entidad ?? "-")}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-2.5 text-[12px] text-slate-600">${escapeHtml(payment.cosecha ?? "-")}</td>
            <td class="whitespace-nowrap border-t border-kp-border px-4 py-2.5 font-mono text-[12px] text-slate-500">${escapeHtml(payment.cuenta_recaudo ?? payment.cuenta ?? "-")}</td>
        </tr>
    `,
    );
}

function renderCnaPayments(payments, operations, body) {
    const paymentOperations = operationsFromPayments(payments);

    if (!operations.length && paymentOperations.length) {
        operations = paymentOperations;
        setText("cna_ops", operations.join(", "));
    }

    const cnaOperations = new Set(
        operations.map((operation) => operation.trim()),
    );
    setCnaPaymentContext(payments, cnaOperations);

    let total = 0;

    if (!payments.length) {
        renderEmptyPayments("Sin pagos registrados para este cliente.");
    } else if (body) {
        body.innerHTML = "";
        payments.forEach((payment) => {
            total +=
                Number(
                    String(
                        payment.monto_pagado ?? payment.monto ?? 0,
                    ).replaceAll(",", ""),
                ) || 0;
            addPaymentRow(payment);
        });
    }

    setText("cna_total_pagos", money(total));
}

function setupCnaDetail(root) {
    $$(".js-ver-cna", root).forEach((button) => {
        button.addEventListener("click", async () => {
            const dni = (button.dataset.dni || "").trim();
            const body = $("#cna_pagos_tbody");
            const routeTemplate = root.dataset.pagosUrlTemplate || "";
            const observation = (button.dataset.observacion || "").trim();
            const note = (button.dataset.nota || "").trim();
            const noteWrap = $("#cna_nota_wrap");
            const clientLink = $("#cna_ver_ficha");

            setText("cna_titular", button.dataset.titular || "-");
            setText("cna_dni", dni || "-");
            setText("cna_carta", button.dataset.nrocarta || "-");
            setText("cna_estado", button.dataset.estado || "-");
            setText("cna_solicitante", button.dataset.solicitante || "-");
            setText("cna_producto", button.dataset.producto || "-");
            setText("cna_fecha", toDmy(button.dataset.fecha));
            setText("cna_fecha_pago", toDmy(button.dataset.fechaPago));
            setText("cna_monto_pagado", money(button.dataset.montoPagado));
            setText("cna_obs", observation || "Sin observaciones registradas.");
            setBadgeVariant(
                $("#cna_estado"),
                badgeVariantForStatus(button.dataset.estado),
            );

            if (noteWrap) noteWrap.classList.toggle("hidden", !note);
            setText("cna_nota", note || "");

            if (clientLink) {
                const url = (button.dataset.clienteUrl || "").trim();
                clientLink.href = url || "#";
                clientLink.classList.toggle("hidden", !url);
            }

            renderCnaTimeline(button);
            renderCnaDocument(button);

            let operations = uniqueOperations(
                parseBase64JsonAttribute(
                    button,
                    "data-operaciones-b64",
                    parseJsonAttribute(button, "data-operaciones", []),
                ),
            );
            setText("cna_ops", operations.length ? operations.join(", ") : "-");

            const preloadedPayments = parseBase64JsonAttribute(
                button,
                "data-pagos-b64",
                null,
            );

            if (
                preloadedPayments !== null &&
                Array.isArray(preloadedPayments)
            ) {
                renderCnaPayments(preloadedPayments, operations, body);
                return;
            }

            setText("cna_entidad_cosecha", "Cargando...");

            if (body) {
                body.innerHTML =
                    '<tr><td colspan="7" class="border-t border-kp-border px-4 py-6 text-center text-[12px] text-kp-muted">Cargando pagos...</td></tr>';
            }
            setText("cna_total_pagos", money(0));

            const count = $("#cna_pagos_count");
            const context = $("#cna_pagos_context");

            if (count) count.textContent = "Cargando...";
            if (context) context.textContent = "Cargando pagos del DNI...";

            try {
                const url = routeTemplate.replace(
                    "__DNI__",
                    encodeURIComponent(dni || ""),
                );
                const response = await fetch(url, {
                    headers: { Accept: "application/json" },
                });

                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const json = await response.json();
                const payments = Array.isArray(json.pagos) ? json.pagos : [];
                renderCnaPayments(payments, operations, body);
            } catch (error) {
                console.error("CNA pagos fetch error:", error);
                setCnaPaymentContextError();
                renderEmptyPayments("Error al cargar los pagos.", "danger");
                setText("cna_total_pagos", money(0));
            }
        });
    });
}

ready(() => {
    const root = $('[data-module="autorizacion-index"]');

    if (!root) return;

    setupDecisionModals(root);
    setupPromiseDetail(root);
    setupCnaDetail(root);
    setupModals(root);
});
