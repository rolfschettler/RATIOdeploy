<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Adressen – RATIO Server</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        html, body { height: 100%; margin: 0; background: #f0f4f9; }
        body { display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }

        /* ── Header ─────────────────────────────────────────────────────── */
        #pageHeader {
            position: sticky; top: 0; z-index: 100;
            background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%);
            padding: .8rem 1.75rem;
            box-shadow: 0 3px 16px rgba(26,58,92,.3);
        }
        #pageTitle { color: #fff; font-size: 1.05rem; font-weight: 700; letter-spacing: .02em; margin: 0; }
        #recordCount {
            background: rgba(255,255,255,.2); color: #fff;
            font-size: .72rem; font-weight: 500; border-radius: 20px;
            padding: .2em .7em; letter-spacing: .02em;
        }
        #searchInput {
            background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.3);
            color: #fff; border-radius: 20px; padding: .35rem 1rem; font-size: .82rem;
            transition: background .2s, border-color .2s;
        }
        #searchInput::placeholder { color: rgba(255,255,255,.55); }
        #searchInput:focus {
            background: rgba(255,255,255,.25); border-color: rgba(255,255,255,.65);
            color: #fff; box-shadow: 0 0 0 3px rgba(255,255,255,.15); outline: none;
        }
        .btn-header {
            border-radius: 20px; font-size: .8rem; padding: .35rem 1rem;
            font-weight: 500; transition: background .2s, box-shadow .2s; border: none; cursor: pointer;
        }
        .btn-header.primary { background: rgba(255,255,255,.95); color: #1a3a5c; }
        .btn-header.primary:hover { background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
        .btn-header.ghost { background: rgba(255,255,255,.12); color: rgba(255,255,255,.85); border: 1px solid rgba(255,255,255,.25); }
        .btn-header.ghost:hover { background: rgba(255,255,255,.22); color: #fff; }

        /* ── Content ─────────────────────────────────────────────────────── */
        #pageContent { flex: 1; overflow-y: auto; padding: 1.5rem 1.75rem; }

        /* ── Footer ─────────────────────────────────────────────────────── */
        #pageFooter {
            background: #1a3a5c; color: rgba(255,255,255,.55);
            font-size: .75rem; padding: .5rem 1.75rem;
            display: flex; align-items: center; justify-content: space-between;
            flex-shrink: 0; letter-spacing: .02em;
        }

        /* ── Table card ──────────────────────────────────────────────────── */
        .table-card {
            background: #fff; border-radius: 14px;
            box-shadow: 0 4px 28px rgba(26,58,92,.09);
            overflow: hidden;
        }
        .table { font-size: .82rem; border-collapse: collapse; margin: 0; width: 100%; }
        .table thead th {
            background: linear-gradient(135deg, #1e4470 0%, #2d6a9f 100%);
            color: #fff; white-space: nowrap;
            padding: .7rem 1rem; font-weight: 600; font-size: .75rem;
            letter-spacing: .06em; text-transform: uppercase; border: none;
            position: sticky; top: 0; z-index: 10;
        }
        .table td {
            vertical-align: middle; padding: .6rem 1rem; white-space: nowrap;
            border: none; border-bottom: 1px solid #edf1f7; color: #2c3e50;
        }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr { transition: background .12s; }
        .table tbody tr:hover td { background: #eef5ff; }
        .table tbody tr:nth-child(even) td { background: #f8fafd; }
        .table tbody tr:nth-child(even):hover td { background: #eef5ff; }

        /* Sort */
        .table thead th[data-col] { cursor: pointer; user-select: none; }
        .table thead th[data-col]:hover { background: linear-gradient(135deg, #255080, #3a7dc0); }
        .table thead th[data-col].sort-active { background: linear-gradient(135deg, #2d6a9f, #4a90d9); }

        /* ── Cells ───────────────────────────────────────────────────────── */
        .kz-badge {
            display: inline-block; background: #eef5ff; color: #1a3a5c;
            border-radius: 20px; padding: .15em .65em;
            font-size: .72rem; font-weight: 700; font-family: monospace; letter-spacing: .03em;
        }
        .gruppe-chip {
            display: inline-block; background: #f0f7ff; color: #2d6a9f;
            border: 1px solid #c5dcf5; border-radius: 20px;
            padding: .1em .6em; font-size: .72rem; font-weight: 500;
        }
        td a { color: #2d6a9f; text-decoration: none; }
        td a:hover { text-decoration: underline; }

        /* ── Action buttons ──────────────────────────────────────────────── */
        .btn-action {
            border: none; background: transparent; border-radius: 7px;
            padding: .28rem .42rem; line-height: 1; font-size: .88rem;
            cursor: pointer; transition: background .15s, color .15s;
        }
        .btn-action.edit  { color: #2d6a9f; }
        .btn-action.edit:hover  { background: #deeafa; color: #1a3a5c; }
        .btn-action.delete { color: #bbb; }
        .btn-action.delete:hover { background: #fdecea; color: #c0392b; }
        .btn-action:disabled { opacity: .4; cursor: default; }
    </style>
</head>
<body>

<!-- Sticky Header -->
<div id="pageHeader">
    <div class="d-flex align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3 flex-shrink-0">
            <h5 id="pageTitle">Adressen</h5>
            <span id="recordCount">…</span>
        </div>
        <div class="flex-grow-1" style="max-width:340px">
            <input type="search" id="searchInput" placeholder="Name suchen …" autocomplete="off" disabled>
        </div>
        <button class="btn-header primary flex-shrink-0" id="insertBtn">+ Neu</button>
        <button class="btn-header ghost flex-shrink-0" id="logoutBtn">Abmelden</button>
    </div>
</div>

<!-- Inhalt -->
<div id="pageContent">
    <div id="errorAlert" class="alert alert-danger d-none" role="alert"></div>
    <div id="loadingSpinner" class="text-center py-5">
        <div class="spinner-border" style="color:#2d6a9f" role="status"></div>
        <div class="text-muted mt-2">Daten werden geladen …</div>
    </div>
    <div id="tableContainer" class="d-none">
        <div class="table-card table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th data-col="0" data-label="Kennziffer">Kennziffer</th>
                        <th data-col="1" data-label="Name 1">Name 1</th>
                        <th data-col="2" data-label="Name 2">Name 2</th>
                        <th data-col="3" data-label="Gruppe">Gruppe</th>
                        <th data-col="4" data-label="Adresse">Adresse</th>
                        <th data-col="5" data-label="PLZ / Ort">PLZ / Ort</th>
                        <th data-col="6" data-label="E-Mail">E-Mail</th>
                        <th data-col="7" data-label="Letzter Vorgang">Letzter Vorgang</th>
                        <th class="text-center">Aktionen</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Neu-Modal -->
<div class="modal fade" id="insertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a3a5c;color:#fff">
                <h6 class="modal-title">Neuer Datensatz</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2" style="font-size:.78rem;color:#6c757d">
                    Kennziffer: <strong id="insKennzifferDisplay"></strong>
                </div>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label form-label-sm mb-1">Gruppe <span style="color:#c0392b">*</span></label>
                        <select id="insGruppe" class="form-select form-select-sm" required>
                            <option value="">— bitte wählen —</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Anrede</label>
                        <select id="insAnrede" class="form-select form-select-sm">
                            <option value=""></option>
                            <option value="Herr">Herr</option>
                            <option value="Frau">Frau</option>
                            <option value="Familie">Familie</option>
                            <option value="Firma">Firma</option>
                            <option value="Divers">Divers</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Titel</label>
                        <input type="text" id="insTitel" class="form-control form-control-sm" maxlength="20">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Name 1</label>
                        <input type="text" id="insName1" class="form-control form-control-sm" maxlength="30">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Name 2</label>
                        <input type="text" id="insName2" class="form-control form-control-sm" maxlength="30">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Straße</label>
                        <input type="text" id="insStrasse" class="form-control form-control-sm" maxlength="30">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">PLZ</label>
                        <input type="text" id="insPlz" class="form-control form-control-sm" maxlength="15">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Ort</label>
                        <input type="text" id="insOrt" class="form-control form-control-sm" maxlength="30">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Telefon</label>
                        <input type="text" id="insTelefon1" class="form-control form-control-sm" maxlength="25">
                    </div>
                    <div class="col-12">
                        <label class="form-label form-label-sm mb-1">E-Mail</label>
                        <input type="email" id="insEmail" class="form-control form-control-sm" maxlength="60">
                    </div>
                </div>
                <div id="insAlert" class="alert alert-danger mt-2" role="alert"
                     style="visibility:hidden;margin-bottom:0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-sm" style="background:#2d6a9f;color:#fff" id="insSaveBtn">Speichern</button>
            </div>
        </div>
    </div>
</div>

<!-- Bearbeiten-Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a3a5c;color:#fff">
                <h6 class="modal-title">Adresse bearbeiten</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2" style="font-size:.78rem;color:#6c757d">
                    Kennziffer: <strong id="editKennzifferDisplay"></strong>
                </div>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label form-label-sm mb-1">Gruppe <span style="color:#c0392b">*</span></label>
                        <select id="editGruppe" class="form-select form-select-sm" required>
                            <option value="">— bitte wählen —</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Anrede</label>
                        <select id="editAnrede" class="form-select form-select-sm">
                            <option value=""></option>
                            <option value="Herr">Herr</option>
                            <option value="Frau">Frau</option>
                            <option value="Familie">Familie</option>
                            <option value="Firma">Firma</option>
                            <option value="Divers">Divers</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Titel</label>
                        <input type="text" id="editTitel" class="form-control form-control-sm" maxlength="20">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Vorname</label>
                        <input type="text" id="editName1" class="form-control form-control-sm" maxlength="30">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Nachname</label>
                        <input type="text" id="editName2" class="form-control form-control-sm" maxlength="30">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Straße</label>
                        <input type="text" id="editStrasse" class="form-control form-control-sm" maxlength="30">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">PLZ</label>
                        <input type="text" id="editPlz" class="form-control form-control-sm" maxlength="15">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Ort</label>
                        <input type="text" id="editOrt" class="form-control form-control-sm" maxlength="30">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-label-sm mb-1">Telefon</label>
                        <input type="text" id="editTelefon1" class="form-control form-control-sm" maxlength="25">
                    </div>
                    <div class="col-12">
                        <label class="form-label form-label-sm mb-1">E-Mail</label>
                        <input type="email" id="editEmail" class="form-control form-control-sm" maxlength="60">
                    </div>
                </div>
                <div id="editAlert" class="alert alert-danger mt-2" role="alert"
                     style="visibility:hidden;margin-bottom:0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-sm" style="background:#2d6a9f;color:#fff" id="editSaveBtn">Speichern</button>
            </div>
        </div>
    </div>
</div>

<!-- Bestätigungs-Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:#1a3a5c;color:#fff">
                <h6 class="modal-title">Datensatz löschen</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="deleteModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-sm btn-danger" id="deleteConfirmBtn">Löschen</button>
            </div>
        </div>
    </div>
</div>

<!-- Fussbereich -->
<div id="pageFooter">
    <span>RATIO Server</span>
    <span id="footerInfo">&nbsp;</span>
</div>

<script>
    // ── Auth ─────────────────────────────────────────────────────────────────
    const token = localStorage.getItem('app_token');
    if (!token) window.location.href = '/php/login';

    document.getElementById('logoutBtn').addEventListener('click', () => {
        localStorage.removeItem('app_token');
        window.location.href = '/php/login';
    });

    // ── Hilfsfunktionen ──────────────────────────────────────────────────────
    function esc(val) {
        if (val === null || val === undefined || val === '') return '-';
        const d = document.createElement('div');
        d.textContent = String(val);
        return d.innerHTML;
    }

    function formatDate(str) {
        if (!str) return '-';
        const d = new Date(str);
        return isNaN(d) ? str : d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function formatError(msg) {
        const m = String(msg).match(/ERR:\S+\s+([\s\S]+)/);
        return m ? m[1].trim() : msg;
    }

    function updateCounter(count) {
        document.getElementById('recordCount').textContent = count + ' Datensätze';
        document.getElementById('footerInfo').textContent  = count + ' Datensätze angezeigt';
    }

    // ── API-Calls (rufen AdresseController-Endpunkte auf) ───────────────────
    const api = {
        headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },

        async load() {
            const r = await fetch('/php/adressen2/load', { headers: this.headers });
            if (r.status === 401) { localStorage.removeItem('app_token'); window.location.href = '/php/login'; }
            const j = await r.json();
            if (!r.ok) throw new Error(j.message ?? 'Ladefehler');
            return j.data ?? [];
        },

        async delete(kennziffer) {
            const r = await fetch('/php/adressen2/delete', {
                method: 'POST', headers: this.headers,
                body: JSON.stringify({ kennziffer })
            });
            const j = await r.json();
            if (!r.ok) throw new Error(j.message ?? 'Löschfehler');
            return j;
        },

        async nextkennziffer() {
            const r = await fetch('/php/adressen2/nextkennziffer', { headers: this.headers });
            if (r.status === 401) { localStorage.removeItem('app_token'); window.location.href = '/php/login'; }
            const j = await r.json();
            if (!r.ok) throw new Error(j.message ?? 'Fehler beim Ermitteln der Kennziffer');
            return j.data.kennziffer;
        },

        async insert(data) {
            const r = await fetch('/php/adressen2/insert', {
                method: 'POST', headers: this.headers,
                body: JSON.stringify(data)
            });
            const j = await r.json();
            if (!r.ok) throw new Error(j.message ?? 'Einfügefehler');
            return j;
        },

        async kategorien() {
            const r = await fetch('/php/adressen2/kategorien', { headers: this.headers });
            if (r.status === 401) { localStorage.removeItem('app_token'); window.location.href = '/php/login'; }
            const j = await r.json();
            if (!r.ok) throw new Error(j.message ?? 'Ladefehler Kategorien');
            return j.data ?? [];
        },

        async get(kennziffer) {
            const r = await fetch('/php/adressen2/get?kennziffer=' + encodeURIComponent(kennziffer), { headers: this.headers });
            if (r.status === 401) { localStorage.removeItem('app_token'); window.location.href = '/php/login'; }
            const j = await r.json();
            if (!r.ok) throw new Error(j.message ?? 'Ladefehler');
            return j.data;
        },

        async update(data) {
            const r = await fetch('/php/adressen2/update', {
                method: 'POST', headers: this.headers,
                body: JSON.stringify(data)
            });
            const j = await r.json();
            if (!r.ok) throw new Error(j.message ?? 'Speicherfehler');
            return j;
        }
    };

    // ── Tabelle rendern ──────────────────────────────────────────────────────
    let allRows       = [];
    let kategorienMap = new Map();

    function renderTable(adressen) {
        document.getElementById('loadingSpinner').classList.add('d-none');

        if (adressen.length === 0) {
            const el = document.getElementById('errorAlert');
            el.textContent = 'Keine Datensätze gefunden.';
            el.className = 'alert alert-info';
            el.classList.remove('d-none');
            return;
        }

        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        allRows = [];

        adressen.forEach(a => {
            const plzOrt    = [a.plz, a.ort].filter(Boolean).join(' ') || '-';
            const gruppe      = String(a.gruppe ?? '').trim();
            const gruppeLabel = gruppe ? (kategorienMap.get(gruppe) ?? gruppe) : '-';
            const tr = document.createElement('tr');
            tr.dataset.kennziffer = a.kennziffer ?? '';
            tr.dataset.name2      = a.name2 ?? '';
            allRows.push(tr);
            tr.innerHTML = `
                <td><span class="kz-badge">${esc(a.kennziffer)}</span></td>
                <td>${esc(a.name1)}</td>
                <td>${esc(a.name2)}</td>
                <td title="${esc(gruppe)}"><span class="gruppe-chip">${esc(gruppeLabel)}</span></td>
                <td>${esc(a.strasse)}</td>
                <td>${esc(plzOrt)}</td>
                <td>${a.email ? `<a href="mailto:${esc(a.email)}">${esc(a.email)}</a>` : '-'}</td>
                <td>${esc(formatDate(a.lvorgang))}</td>
                <td style="white-space:nowrap;text-align:center">
                    <button class="btn-action edit btn-edit" title="Bearbeiten"
                            data-kennziffer="${esc(a.kennziffer)}">&#9998;</button>
                    <button class="btn-action delete btn-delete" title="Löschen"
                            data-kennziffer="${esc(a.kennziffer)}"
                            data-info="${esc(a.name1)} ${esc(a.name2)}">&#128465;</button>
                </td>`;
            tbody.appendChild(tr);
        });

        updateCounter(adressen.length);
        document.getElementById('tableContainer').classList.remove('d-none');
        document.getElementById('searchInput').disabled = false;
        document.getElementById('searchInput').focus();
    }

    // ── Suche ────────────────────────────────────────────────────────────────
    document.getElementById('searchInput').addEventListener('input', function () {
        const term = this.value.trim().toLowerCase();
        let visible = 0;
        allRows.forEach(row => {
            const show = term.length < 2 || (row.dataset.name2 ?? '').toLowerCase().includes(term);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        updateCounter(visible);
    });

    // ── Sortierung ───────────────────────────────────────────────────────────
    let sortState = { col: null, dir: 'asc' };

    function getCellValue(tr, col) {
        if (col === 0) return parseInt(tr.dataset.kennziffer) || 0;
        const text = tr.querySelectorAll('td')[col]?.textContent?.trim() ?? '';
        if (col === 7) { // Datum dd.mm.yyyy → yyyymmdd für korrekte Sortierung
            const parts = text.split('.');
            return parts.length === 3 ? parts[2] + parts[1] + parts[0] : '';
        }
        return text;
    }

    function sortTable(col) {
        if (sortState.col === col) {
            sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
        } else {
            sortState.col = col;
            sortState.dir = 'asc';
        }
        const dir = sortState.dir === 'asc' ? 1 : -1;

        allRows.sort((a, b) => {
            const va = getCellValue(a, col);
            const vb = getCellValue(b, col);
            if (col === 0) return dir * (va - vb);
            return dir * String(va).localeCompare(String(vb), 'de');
        });

        const tbody = document.getElementById('tableBody');
        allRows.forEach(tr => tbody.appendChild(tr));

        document.querySelectorAll('thead th[data-col]').forEach(th => {
            const c = parseInt(th.dataset.col);
            th.classList.toggle('sort-active', c === col);
            th.textContent = th.dataset.label + (c === col ? (sortState.dir === 'asc' ? ' ▲' : ' ▼') : '');
        });
    }

    document.querySelector('thead').addEventListener('click', e => {
        const th = e.target.closest('th[data-col]');
        if (!th || !allRows.length) return;
        sortTable(parseInt(th.dataset.col));
    });

    // ── Löschen ──────────────────────────────────────────────────────────────
    let deleteKennziffer = null;
    let deleteRow        = null;
    const deleteModal    = new bootstrap.Modal(document.getElementById('deleteModal'));

    document.getElementById('tableBody').addEventListener('click', e => {
        const btn = e.target.closest('.btn-delete');
        if (!btn) return;
        deleteKennziffer = btn.dataset.kennziffer;
        deleteRow        = btn.closest('tr');
        document.getElementById('deleteModalBody').textContent =
            `Soll "${btn.dataset.info}" (Kennziffer ${deleteKennziffer}) wirklich gelöscht werden?`;
        deleteModal.show();
    });

    document.getElementById('deleteConfirmBtn').addEventListener('click', async function () {
        this.disabled = true;
        this.textContent = '…';
        try {
            await api.delete(deleteKennziffer);
            allRows = allRows.filter(r => r !== deleteRow);
            deleteRow.remove();
            deleteModal.hide();
            updateCounter(allRows.filter(r => r.style.display !== 'none').length);
        } catch (err) {
            alert('Fehler: ' + formatError(err.message));
        } finally {
            this.disabled = false;
            this.textContent = 'Löschen';
        }
    });

    // ── Neu ──────────────────────────────────────────────────────────────────
    let insNextKennziffer = null;
    const insertModal     = new bootstrap.Modal(document.getElementById('insertModal'));

    document.getElementById('insertBtn').addEventListener('click', async function () {
        const alertEl = document.getElementById('insAlert');
        alertEl.style.visibility = 'hidden';

        // Felder leeren
        ['insGruppe','insAnrede','insTitel','insName1','insName2',
         'insStrasse','insPlz','insOrt','insTelefon1','insEmail'].forEach(id => {
            const el = document.getElementById(id);
            el.tagName === 'SELECT' ? el.value = '' : el.value = '';
        });

        this.disabled = true;
        try {
            insNextKennziffer = await api.nextkennziffer();
            document.getElementById('insKennzifferDisplay').textContent = insNextKennziffer;
            insertModal.show();
        } catch (err) {
            alertEl.textContent = 'Fehler: ' + formatError(err.message);
            alertEl.style.visibility = 'visible';
        } finally {
            this.disabled = false;
        }
    });

    document.getElementById('insSaveBtn').addEventListener('click', async function () {
        this.disabled = true;
        this.textContent = '…';
        const alertEl = document.getElementById('insAlert');
        alertEl.style.visibility = 'hidden';

        const payload = {
            kennziffer: insNextKennziffer,
            gruppe:     document.getElementById('insGruppe').value,
            anrede:     document.getElementById('insAnrede').value,
            titel:      document.getElementById('insTitel').value,
            name1:      document.getElementById('insName1').value,
            name2:      document.getElementById('insName2').value,
            strasse:    document.getElementById('insStrasse').value,
            plz:        document.getElementById('insPlz').value,
            ort:        document.getElementById('insOrt').value,
            telefon1:   document.getElementById('insTelefon1').value,
            email:      document.getElementById('insEmail').value
        };

        if (!payload.gruppe) {
            alertEl.textContent = 'Bitte eine Gruppe auswählen.';
            alertEl.style.visibility = 'visible';
            this.disabled = false;
            this.textContent = 'Speichern';
            return;
        }

        try {
            await api.insert(payload);

            // Neue Zeile direkt in die Tabelle einfügen
            const plzOrt      = [payload.plz, payload.ort].filter(Boolean).join(' ') || '-';
            const gruppeLabel = payload.gruppe
                ? (kategorienMap.get(payload.gruppe) ?? payload.gruppe)
                : '-';
            const tr = document.createElement('tr');
            tr.dataset.kennziffer = payload.kennziffer;
            tr.dataset.name2      = payload.name2 ?? '';
            tr.innerHTML = `
                <td><span class="kz-badge">${esc(payload.kennziffer)}</span></td>
                <td>${esc(payload.name1)}</td>
                <td>${esc(payload.name2)}</td>
                <td title="${esc(payload.gruppe)}"><span class="gruppe-chip">${esc(gruppeLabel)}</span></td>
                <td>${esc(payload.strasse)}</td>
                <td>${plzOrt}</td>
                <td>${payload.email ? `<a href="mailto:${esc(payload.email)}">${esc(payload.email)}</a>` : '-'}</td>
                <td>-</td>
                <td style="white-space:nowrap;text-align:center">
                    <button class="btn-action edit btn-edit" title="Bearbeiten"
                            data-kennziffer="${esc(payload.kennziffer)}">&#9998;</button>
                    <button class="btn-action delete btn-delete" title="Löschen"
                            data-kennziffer="${esc(payload.kennziffer)}"
                            data-info="${esc(payload.name1)} ${esc(payload.name2)}">&#128465;</button>
                </td>`;
            document.getElementById('tableBody').prepend(tr);
            allRows.unshift(tr);
            updateCounter(allRows.filter(r => r.style.display !== 'none').length);

            insertModal.hide();
        } catch (err) {
            alertEl.textContent = 'Fehler: ' + formatError(err.message);
            alertEl.style.visibility = 'visible';
        } finally {
            this.disabled = false;
            this.textContent = 'Speichern';
        }
    });

    // ── Bearbeiten ───────────────────────────────────────────────────────────
    let editCurrentKennziffer = null;
    let editCurrentRow        = null;
    const editModal           = new bootstrap.Modal(document.getElementById('editModal'));

    document.getElementById('tableBody').addEventListener('click', async e => {
        const btn = e.target.closest('.btn-edit');
        if (!btn) return;

        editCurrentKennziffer = btn.dataset.kennziffer;
        editCurrentRow        = btn.closest('tr');

        const alert = document.getElementById('editAlert');
        alert.style.visibility = 'hidden';
        btn.disabled = true;

        try {
            const rec = await api.get(editCurrentKennziffer);

            document.getElementById('editKennzifferDisplay').textContent = rec.kennziffer ?? editCurrentKennziffer;
            document.getElementById('editGruppe').value   = String(rec.gruppe  ?? '').trim();
            document.getElementById('editAnrede').value   = (rec.anrede  ?? '').trim();
            document.getElementById('editTitel').value    = (rec.titel   ?? '').trim();
            document.getElementById('editName1').value    = (rec.name1   ?? '').trim();
            document.getElementById('editName2').value    = (rec.name2   ?? '').trim();
            document.getElementById('editStrasse').value  = (rec.strasse ?? '').trim();
            document.getElementById('editPlz').value      = (rec.plz     ?? '').trim();
            document.getElementById('editOrt').value      = (rec.ort     ?? '').trim();
            document.getElementById('editTelefon1').value = (rec.telefon1 ?? '').trim();
            document.getElementById('editEmail').value    = (rec.email   ?? '').trim();

            editModal.show();
        } catch (err) {
            alert.textContent = 'Fehler: ' + formatError(err.message);
            alert.style.visibility = 'visible';
        } finally {
            btn.disabled = false;
        }
    });

    document.getElementById('editSaveBtn').addEventListener('click', async function () {
        this.disabled = true;
        this.textContent = '…';
        const alertEl = document.getElementById('editAlert');
        alertEl.style.visibility = 'hidden';

        const payload = {
            kennziffer: editCurrentKennziffer,
            gruppe:     document.getElementById('editGruppe').value,
            anrede:     document.getElementById('editAnrede').value,
            titel:      document.getElementById('editTitel').value,
            name1:      document.getElementById('editName1').value,
            name2:      document.getElementById('editName2').value,
            strasse:    document.getElementById('editStrasse').value,
            plz:        document.getElementById('editPlz').value,
            ort:        document.getElementById('editOrt').value,
            telefon1:   document.getElementById('editTelefon1').value,
            email:      document.getElementById('editEmail').value
        };

        if (!payload.gruppe) {
            alertEl.textContent = 'Bitte eine Gruppe auswählen.';
            alertEl.style.visibility = 'visible';
            this.disabled = false;
            this.textContent = 'Speichern';
            return;
        }

        try {
            await api.update(payload);

            // Tabellenzeile direkt aktualisieren
            // Spaltenreihenfolge: 0=Kennziffer, 1=Name1, 2=Name2, 3=Gruppe, 4=Adresse, 5=PLZ/Ort, 6=E-Mail
            if (editCurrentRow) {
                const plzOrt      = [payload.plz, payload.ort].filter(Boolean).join(' ') || '-';
                const gruppeLabel = payload.gruppe
                    ? (kategorienMap.get(payload.gruppe) ?? payload.gruppe)
                    : '-';
                editCurrentRow.dataset.name2 = payload.name2;
                const cells = editCurrentRow.querySelectorAll('td');
                cells[1].textContent  = payload.name1   || '-';
                cells[2].textContent  = payload.name2   || '-';
                cells[3].title        = payload.gruppe;
                cells[3].innerHTML    = `<span class="gruppe-chip">${esc(gruppeLabel)}</span>`;
                cells[4].textContent  = payload.strasse || '-';
                cells[5].textContent  = plzOrt;
                if (payload.email) {
                    cells[6].innerHTML = `<a href="mailto:${esc(payload.email)}">${esc(payload.email)}</a>`;
                } else {
                    cells[6].textContent = '-';
                }
                const delBtn = editCurrentRow.querySelector('.btn-delete');
                if (delBtn) delBtn.dataset.info = `${payload.name1} ${payload.name2}`;
            }

            editModal.hide();
        } catch (err) {
            alertEl.textContent = 'Fehler: ' + formatError(err.message);
            alertEl.style.visibility = 'visible';
        } finally {
            this.disabled = false;
            this.textContent = 'Speichern';
        }
    });

    // ── Start ────────────────────────────────────────────────────────────────
    Promise.all([api.load(), api.kategorien()])
        .then(([adressen, kategorien]) => {
            // Lookup-Map aufbauen: gruppe → bezeichnung
            kategorienMap = new Map(kategorien.map(k => [String(k.gruppe ?? '').trim(), String(k.bezeichnung ?? '').trim()]));

            // Dropdowns befüllen (Edit- und Insert-Modal)
            [document.getElementById('editGruppe'), document.getElementById('insGruppe')].forEach(sel => {
                kategorien.forEach(k => {
                    const opt = document.createElement('option');
                    opt.value       = String(k.gruppe ?? '').trim();
                    opt.textContent = `${String(k.gruppe ?? '').trim()} – ${String(k.bezeichnung ?? '').trim()}`;
                    sel.appendChild(opt);
                });
            });

            renderTable(adressen);
        })
        .catch(err => {
            document.getElementById('loadingSpinner').classList.add('d-none');
            const el = document.getElementById('errorAlert');
            el.textContent = err.message;
            el.classList.remove('d-none');
        });
</script>

</body>
</html>
