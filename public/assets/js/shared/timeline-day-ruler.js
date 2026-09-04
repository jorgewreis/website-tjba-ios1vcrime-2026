(function () {
    'use strict';

    function normalizeStatus(statusRaw) {
        var s = String(statusRaw || '').trim().toLowerCase();
        if (s === 'agendada') return 'designada';
        if (s === 'adiada') return 'redesignada';
        if (s === 'encerrada') return 'finalizada';
        return s;
    }

    function isFinishedStatus(statusRaw) {
        var st = normalizeStatus(statusRaw);
        return st === 'finalizada' || st === 'realizada';
    }

    function classByStatus(statusRaw, studentPanel) {
        var st = normalizeStatus(statusRaw);
        if (studentPanel) {
            if (st === 'em_andamento') return 'is-current';
            if (st === 'cancelada' || st === 'redesignada' || st === 'finalizada' || st === 'realizada') return 'is-closed';
            return 'is-pending';
        }
        if (st === 'em_andamento') return 'is-warning';
        if (st === 'finalizada' || st === 'realizada') return 'is-success';
        if (st === 'cancelada') return 'is-danger';
        return 'is-warning';
    }

    function parseDateTime(v) {
        var s = String(v || '').trim();
        if (!s) return NaN;
        var m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        if (!m) return NaN;
        var y = Number(m[1]);
        var mo = Number(m[2]) - 1;
        var d = Number(m[3]);
        var hh = Number(m[4] || '0');
        var mm = Number(m[5] || '0');
        var ss = Number(m[6] || '0');
        var dt = new Date(y, mo, d, hh, mm, ss, 0);
        var t = dt.getTime();
        return isNaN(t) ? NaN : t;
    }

    function toMinuteValue(ts) {
        var d = new Date(ts);
        if (isNaN(d.getTime())) return NaN;
        return d.getHours() * 60 + d.getMinutes() + (d.getSeconds() / 60);
    }

    function fmtHM(minuteValue) {
        var hh = String(Math.floor(minuteValue / 60)).padStart(2, '0');
        var mm = String(Math.floor(minuteValue % 60)).padStart(2, '0');
        return hh + ':' + mm;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function render(root, items, options) {
        if (!root) return;
        var studentPanel = !!(options && options.studentPanel);
        root.classList.toggle('timeline-day-ruler-student', studentPanel);
        var dayStart = (options && options.dayStart) || (8 * 60);
        var dayEnd = (options && options.dayEnd) || (18 * 60);
        var range = dayEnd - dayStart;
        var clamp = function (m) { return Math.max(dayStart, Math.min(dayEnd, m)); };
        var pct = function (m) { return ((clamp(m) - dayStart) / range) * 100; };

        var sourceItems = [];
        (Array.isArray(items) ? items : []).forEach(function (item) {
            var schedTs = parseDateTime((String(item.data_audiencia || '').trim() + ' ' + String(item.hora_audiencia || '').trim()).trim());
            if (isNaN(schedTs)) return;
            var sMin = toMinuteValue(schedTs);
            var prevDur = parseInt(item.duracao_estimada_minutos || 0, 10);
            if (!Number.isFinite(prevDur) || prevDur < 5) prevDur = 5;
            var rsTs = parseDateTime(item.data_hora_inicio_efetivo || '');
            var reTs = parseDateTime(item.data_hora_fim_efetivo || '');
            var rs = isNaN(rsTs) ? null : toMinuteValue(rsTs);
            var re = isNaN(reTs) ? null : toMinuteValue(reTs);
            sourceItems.push({
                plannedStart: sMin,
                plannedDur: prevDur,
                realStart: rs,
                realEnd: re,
                status: normalizeStatus(item.status || ''),
                sortStart: rs !== null ? rs : sMin
            });
        });

        sourceItems.sort(function (a, b) {
            if (a.sortStart !== b.sortStart) return a.sortStart - b.sortStart;
            return a.plannedStart - b.plannedStart;
        });

        var now = new Date();
        var nowMin = now.getHours() * 60 + now.getMinutes() + (now.getSeconds() / 60);
        if (studentPanel) {
            sourceItems.forEach(function (ongoing) {
                if (ongoing.status !== 'em_andamento') return;
                var nextPending = null;
                sourceItems.forEach(function (candidate) {
                    if (candidate.status !== 'designada' || candidate.plannedStart <= ongoing.plannedStart || candidate.plannedStart >= nowMin) return;
                    if (!nextPending || candidate.plannedStart < nextPending.plannedStart) nextPending = candidate;
                });
                if (nextPending) {
                    nextPending.plannedStart = nowMin + 5;
                    nextPending.forecasted = true;
                }
            });
        }
        var prevBars = [];
        var delayBars = [];
        var realBars = [];
        var cursorEnd = dayStart;

        sourceItems.forEach(function (slot) {
            var displayStart = Math.max(slot.plannedStart, cursorEnd);
            var plannedEnd = displayStart + slot.plannedDur;
            var realStartDisplay = slot.realStart !== null ? slot.realStart : null;
            var runningEnd = (realStartDisplay !== null && slot.realEnd === null) ? Math.max(realStartDisplay, nowMin) : null;
            var effectiveRealEnd = realStartDisplay !== null
                ? (slot.realEnd !== null ? Math.max(slot.realEnd, realStartDisplay) : runningEnd)
                : null;
            var blockEnd = effectiveRealEnd !== null ? Math.max(plannedEnd, effectiveRealEnd) : plannedEnd;

            prevBars.push({
                l: pct(displayStart),
                w: Math.max(0.4, pct(blockEnd) - pct(displayStart)),
                title: 'Previsto: ' + fmtHM(displayStart) + ' - ' + fmtHM(plannedEnd)
            });

            var delayStart = displayStart;
            var delayEnd = realStartDisplay !== null ? realStartDisplay : (nowMin > displayStart ? nowMin : null);
            if (delayEnd !== null && delayEnd > delayStart) {
                delayBars.push({
                    l: pct(delayStart),
                    w: Math.max(0.4, pct(delayEnd) - pct(delayStart)),
                    title: 'Aguardando início real: ' + fmtHM(delayStart) + ' - ' + fmtHM(delayEnd)
                });
            }

            if (realStartDisplay !== null) {
                var realStart = realStartDisplay;
                var realEnd = slot.realEnd !== null ? Math.max(slot.realEnd, realStart) : Math.max(realStart, nowMin);
                if (realEnd >= realStart) {
                    realBars.push({
                        l: pct(realStart),
                        w: Math.max(0.4, pct(realEnd) - pct(realStart)),
                        status: slot.status,
                        title: 'Real: ' + fmtHM(realStart) + ' - ' + fmtHM(realEnd) + ' | Previsto: ' + fmtHM(displayStart) + ' - ' + fmtHM(plannedEnd)
                    });
                }
            }

            cursorEnd = Math.max(cursorEnd, blockEnd);
        });

        if (!prevBars.length && !realBars.length && !delayBars.length) {
            root.innerHTML = '<span class="timeline-day-ruler-empty">Régua do tempo (08:00–18:00) indisponível no momento.</span>';
            return;
        }

        var prevHtml = prevBars.map(function (b) {
            var pendingClass = studentPanel ? ' is-pending' : '';
            return '<span class="timeline-day-ruler-bar-prev' + pendingClass + '" title="' + escapeHtml(b.title) + '" style="left:' + b.l + '%;width:' + b.w + '%"></span>';
        }).join('');
        var delayHtml = delayBars.map(function (b) {
            var pendingClass = studentPanel ? ' is-pending' : '';
            return '<span class="timeline-day-ruler-bar-real timeline-day-ruler-bar-delay' + pendingClass + '" title="' + escapeHtml(b.title) + '" style="left:' + b.l + '%;width:' + b.w + '%"></span>';
        }).join('');
        var realHtml = realBars.map(function (b) {
            return '<span class="timeline-day-ruler-bar-real ' + classByStatus(b.status, studentPanel) + '" title="' + escapeHtml(b.title) + '" style="left:' + b.l + '%;width:' + b.w + '%"></span>';
        }).join('');
        var tickHtml = Array.from({ length: 11 }, function (_, i) {
            var left = (i / 10) * 100;
            var hiddenClass = (i === 0 || i === 10) ? ' hidden' : '';
            return '<span class="timeline-day-ruler-hour-tick' + hiddenClass + '" style="left:' + left + '%"></span>';
        }).join('');
        var labelsHtml = Array.from({ length: 11 }, function (_, i) {
            var hour = String(8 + i).padStart(2, '0');
            var left = (i / 10) * 100;
            var hiddenClass = (i === 0 || i === 10) ? ' hidden' : '';
            return '<span class="timeline-day-ruler-label' + hiddenClass + '" style="left:' + left + '%">' + hour + ':00</span>';
        }).join('');
        var nowHtml = (nowMin >= dayStart && nowMin <= dayEnd)
            ? '<span class="timeline-day-ruler-now" title="Agora: ' + fmtHM(nowMin) + '" style="left:' + pct(nowMin) + '%"></span>'
            : '';

        var legendHtml = studentPanel
            ? '<span><i class="timeline-day-ruler-legend-dot is-pending"></i> Previsto / aguardando início / atrasada</span>' +
              '<span><i class="timeline-day-ruler-legend-dot is-current"></i> Em andamento</span>' +
              '<span><i class="timeline-day-ruler-legend-dot is-closed"></i> Cancelada / finalizada / redesignada</span>'
            : '<span><i class="timeline-day-ruler-legend-dot" style="background: #20202050"></i> Previsto</span>' +
              '<span><i class="timeline-day-ruler-legend-dot" style="background: var(--color-primary)"></i> Real</span>';

        root.innerHTML =
            '<div class="timeline-day-ruler-track">' +
            '<span class="timeline-day-ruler-shade" style="left:0%;width:9%"></span>' +
            '<span class="timeline-day-ruler-shade" style="left:91%;width:9%"></span>' +
            tickHtml + prevHtml + delayHtml + realHtml + nowHtml + '</div>' +
            '<div class="timeline-day-ruler-labels">' + labelsHtml + '</div>' +
            '<div class="timeline-day-ruler-legend">' + legendHtml + '</div>';
    }

    window.TimelineDayRuler = {
        normalizeStatus: normalizeStatus,
        isFinishedStatus: isFinishedStatus,
        parseDateTime: parseDateTime,
        render: render
    };
})();
