/**
 * ผังที่นั่ง (SVG)
 *   new SeatMap(el, { url, mode: 'user'|'admin'|'preview', max, onChange(selected), holders })
 */
(function (global) {
  'use strict';

  const SVGNS = 'http://www.w3.org/2000/svg';
  const STATUS_TEXT = {
    available: 'ว่าง', held: 'มีผู้กำลังจอง', sold: 'ขายแล้ว',
    blocked: 'ไม่เปิดขาย', unavailable: 'ไม่เปิดขาย', mine: 'ที่นั่งที่คุณล็อกไว้',
  };

  function el(name, attrs, parent) {
    const n = document.createElementNS(SVGNS, name);
    for (const k in attrs || {}) n.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(n);
    return n;
  }

  function fmt(n) {
    return Number(n).toLocaleString('th-TH', { maximumFractionDigits: 2 });
  }

  class SeatMap {
    constructor(container, opts) {
      this.box = container;
      this.opts = Object.assign({ mode: 'user', max: 10, onChange: () => {}, holders: {} }, opts);
      this.selected = new Map();   // seatId -> seat
      this.nodes = new Map();      // seatId -> svg node
      this.zoom = 1;
      this.data = null;
    }

    async load() {
      const res = await fetch(this.opts.url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      this.data = await res.json();
      this.render();
      return this;
    }

    async refresh() {
      const res = await fetch(this.opts.url, { credentials: 'same-origin' });
      const data = await res.json();
      const byId = new Map(data.seats.map((s) => [s.id, s]));
      let lost = [];
      this.data.seats.forEach((s) => {
        const n = byId.get(s.id);
        if (!n) return;
        s.s = n.s; s.p = n.p;
        if (this.selected.has(s.id) && !this.isSelectable(s)) {
          this.selected.delete(s.id);
          lost.push(s.l);
        }
        this.paint(s);
      });
      if (lost.length) {
        this.opts.onChange(this.getSelected(), 'ที่นั่ง ' + lost.join(', ') + ' เพิ่งถูกจองไป');
      }
    }

    isSelectable(s) {
      if (this.opts.mode === 'preview') return false;
      if (this.opts.mode === 'admin') return s.s === 'available' || s.s === 'blocked';
      return s.s === 'available' || s.s === 'mine';
    }

    render() {
      const seats = this.data.seats;
      const size = this.data.seatSize || 18;
      const r = size * 0.4;
      let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
      seats.forEach((s) => {
        minX = Math.min(minX, s.x); maxX = Math.max(maxX, s.x);
        minY = Math.min(minY, s.y); maxY = Math.max(maxY, s.y);
      });
      const pad = 40, stageH = 50;
      const vx = minX - pad, vy = minY - pad - stageH - 20;
      const vw = maxX - minX + pad * 2, vh = maxY - vy + pad;
      this.baseWidth = vw;

      this.box.innerHTML = '';
      const svg = el('svg', { viewBox: `${vx} ${vy} ${vw} ${vh}`, class: 'seatmap-svg', role: 'img', 'aria-label': 'ผังที่นั่ง' }, this.box);
      this.svg = svg;

      // pattern สำหรับที่นั่งที่กันไว้
      const defs = el('defs', {}, svg);
      const pat = el('pattern', { id: 'hatch', width: 4, height: 4, patternUnits: 'userSpaceOnUse', patternTransform: 'rotate(45)' }, defs);
      el('rect', { width: 4, height: 4, fill: '#9aa0a6' }, pat);
      el('line', { x1: 0, y1: 0, x2: 0, y2: 4, stroke: '#5f6368', 'stroke-width': 2 }, pat);

      // เวที
      const stageW = Math.min(vw * 0.6, 600);
      el('rect', { x: -stageW / 2, y: minY - pad - stageH, width: stageW, height: stageH, rx: 10, class: 'stage' }, svg);
      const st = el('text', { x: 0, y: minY - pad - stageH / 2 + 6, class: 'stage-text', 'text-anchor': 'middle' }, svg);
      st.textContent = 'เวที (STAGE)';

      // ป้ายแถวและชั้น
      const rows = new Map();
      seats.forEach((s) => {
        const k = s.f + ':' + s.r;
        const o = rows.get(k) || { f: s.f, r: s.r, minX: Infinity, maxX: -Infinity, yL: 0, yR: 0 };
        if (s.x < o.minX) { o.minX = s.x; o.yL = s.y; }
        if (s.x > o.maxX) { o.maxX = s.x; o.yR = s.y; }
        rows.set(k, o);
      });
      const floorTop = {};
      rows.forEach((o) => {
        el('text', { x: o.minX - size, y: o.yL + 4, class: 'row-label', 'text-anchor': 'end' }, svg).textContent = o.r;
        el('text', { x: o.maxX + size, y: o.yR + 4, class: 'row-label', 'text-anchor': 'start' }, svg).textContent = o.r;
        floorTop[o.f] = Math.min(floorTop[o.f] ?? Infinity, o.yL, o.yR);
      });
      Object.keys(floorTop).forEach((f) => {
        if (f === '1') return;
        const y = floorTop[f] - 30;
        el('line', { x1: minX - pad / 2, x2: maxX + pad / 2, y1: y - 8, y2: y - 8, class: 'floor-line' }, svg);
        el('text', { x: 0, y: y + 6, class: 'floor-label', 'text-anchor': 'middle' }, svg).textContent = this.data.floors[f] || ('ชั้น ' + f);
      });

      // ที่นั่ง
      const g = el('g', { class: 'seats' }, svg);
      seats.forEach((s) => {
        const c = el('circle', { cx: s.x, cy: s.y, r: r, 'data-id': s.id, tabindex: this.isSelectable(s) ? 0 : -1 }, g);
        el('title', {}, c);
        if (s.w) {
          el('text', { x: s.x, y: s.y + 3, class: 'wc', 'text-anchor': 'middle', 'pointer-events': 'none' }, g).textContent = '♿';
        }
        this.nodes.set(s.id, c);
        if (s.s === 'mine') this.selected.set(s.id, s);
        this.paint(s);
      });

      g.addEventListener('click', (e) => {
        const id = Number(e.target.getAttribute('data-id'));
        if (id) this.toggle(id);
      });
      g.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          const id = Number(e.target.getAttribute('data-id'));
          if (id) { e.preventDefault(); this.toggle(id); }
        }
      });

      this.setZoom(this.zoom);
      this.opts.onChange(this.getSelected());
    }

    paint(s) {
      const c = this.nodes.get(s.id);
      if (!c) return;
      const zone = this.data.zones[s.z] || { color: '#888', label: s.z };
      const sel = this.selected.has(s.id);
      let fill = '#d5d8dc', cls = 'seat';
      if (s.s === 'blocked') fill = 'url(#hatch)';
      else if (s.s === 'available' || s.s === 'mine') { fill = zone.color; cls += ' seat-open'; }
      if (sel) cls += ' seat-selected';
      if (!this.isSelectable(s)) cls += ' seat-disabled';
      c.setAttribute('fill', sel ? '#212529' : fill);
      c.setAttribute('class', cls);
      c.setAttribute('tabindex', this.isSelectable(s) ? 0 : -1);
      let tip = `${s.l} · ${zone.label}`;
      if (s.p != null) tip += ` · ${fmt(s.p)} บาท`;
      tip += ` · ${STATUS_TEXT[s.s] || s.s}`;
      const h = this.opts.holders[s.id];
      if (h) tip += `\n${h.code} — ${h.display_name}`;
      if (s.w) tip += '\n(ที่นั่งสำหรับวีลแชร์)';
      c.firstChild.textContent = tip;
    }

    toggle(id) {
      const s = this.data.seats.find((x) => x.id === id);
      if (!s || !this.isSelectable(s)) return;
      if (this.selected.has(id)) {
        this.selected.delete(id);
      } else {
        if (this.opts.mode !== 'admin' && this.selected.size >= this.opts.max) {
          this.opts.onChange(this.getSelected(), `เลือกได้สูงสุด ${this.opts.max} ที่นั่ง`);
          return;
        }
        this.selected.set(id, s);
      }
      this.paint(s);
      this.opts.onChange(this.getSelected());
    }

    clear() {
      const ids = [...this.selected.keys()];
      this.selected.clear();
      ids.forEach((id) => this.paint(this.data.seats.find((x) => x.id === id)));
      this.opts.onChange(this.getSelected());
    }

    getSelected() {
      return [...this.selected.values()].sort((a, b) => a.f - b.f || a.r.localeCompare(b.r) || a.n - b.n);
    }

    setZoom(z) {
      this.zoom = Math.max(1, Math.min(4, z));
      const w = Math.max(this.box.clientWidth, 320) * this.zoom;
      this.svg.style.width = w + 'px';
    }

    zoomIn() { this.centerAfter(() => this.setZoom(this.zoom * 1.4)); }
    zoomOut() { this.centerAfter(() => this.setZoom(this.zoom / 1.4)); }

    centerAfter(fn) {
      const b = this.box;
      const cx = (b.scrollLeft + b.clientWidth / 2) / b.scrollWidth;
      const cy = (b.scrollTop + b.clientHeight / 2) / b.scrollHeight;
      fn();
      b.scrollLeft = cx * b.scrollWidth - b.clientWidth / 2;
      b.scrollTop = cy * b.scrollHeight - b.clientHeight / 2;
    }

    legend(target) {
      const z = this.data.zones;
      const used = new Set(this.data.seats.map((s) => s.z));
      let html = '';
      Object.keys(z).filter((k) => used.has(k)).forEach((k) => {
        html += `<span class="legend-item"><i style="background:${z[k].color}"></i>${z[k].label}</span>`;
      });
      html += '<span class="legend-item"><i style="background:#212529"></i>ที่เลือก</span>';
      html += '<span class="legend-item"><i style="background:#d5d8dc"></i>ไม่ว่าง</span>';
      html += '<span class="legend-item"><i class="hatch"></i>ไม่เปิดขาย</span>';
      target.innerHTML = html;
    }
  }

  global.SeatMap = SeatMap;
})(window);
