/**
 * Storyblok field type "grid-control": a column blok's start and span on the section's grid, per viewport.
 *
 * Paste this into the field type editor in Storyblok (Partner Portal > Field types > grid-control) and publish it.
 *
 * Field options (set where the field is added to a blok):
 *   viewports        Comma-separated: mobile, tablet, desktop. Default: all three.
 *   mobile_columns   Columns in the section's grid on mobile. Default: 4.
 *   tablet_columns   Default: 8.
 *   desktop_columns  Default: 12.
 *
 * Stored value:
 *   { "plugin": "grid-control", "mobile": { "start": 1, "span": 4 }, "desktop": { "start": 5, "span": 6 } }
 *
 * The field only knows about its own column. If it overlaps the column before it, the grid moves it to a new row,
 * which editors see in the Visual Editor.
 */
// Everything is scoped in this function: the editor only expects Fieldtype, and names like CSS are browser globals.
const Fieldtype = (() => {
  const VIEWPORTS = ['mobile', 'tablet', 'desktop'];
  const DEFAULT_COLUMNS = { mobile: 4, tablet: 8, desktop: 12 };
  const LABELS = { mobile: 'Mobile', tablet: 'Tablet', desktop: 'Desktop' };
  const GUTTER = 3; // px between the column guides, matched by the bar

  // Field types render inside the Storyblok app, so styles are scoped under .sbgc and added once.
  const STYLE_ID = 'sbgc-styles';
  const STYLES = `
  .sbgc { display: grid; gap: 10px; font-size: 13px; }
  .sbgc__tabs { display: inline-flex; justify-self: start; border: 1px solid #dfe3e8; border-radius: 6px; overflow: hidden; }
  .sbgc__tab { border: 0; border-right: 1px solid #dfe3e8; background: #fff; color: #1b243f; padding: 6px 10px; font: inherit; cursor: pointer; }
  .sbgc__tab:last-child { border-right: 0; }
  .sbgc__tab.is-active { background: #00b3b0; color: #fff; }
  .sbgc__ruler, .sbgc__cols { display: grid; gap: ${GUTTER}px; }
  .sbgc__ruler { font: 10px/1 monospace; color: #8d919f; text-align: center; }
  .sbgc__track { position: relative; height: 36px; }
  .sbgc__cols { position: absolute; inset: 0; }
  .sbgc__cols span { background: #eff1f3; border-radius: 3px; }
  .sbgc__bar { position: absolute; top: 0; bottom: 0; background: #00b3b0; border-radius: 4px; cursor: grab; touch-action: none; user-select: none; }
  .sbgc__bar.is-dragging { cursor: grabbing; }
  .sbgc__bar:focus-visible, .sbgc__tab:focus-visible { outline: 2px solid #1b243f; outline-offset: 2px; }
  .sbgc__handle { position: absolute; top: 0; bottom: 0; width: 12px; cursor: ew-resize; display: flex; align-items: center; justify-content: center; }
  .sbgc__handle::after { content: ""; width: 3px; height: 14px; border-radius: 2px; background: #fff; opacity: .85; }
  .sbgc__handle--start { left: 0; }
  .sbgc__handle--end { right: 0; }
  .sbgc__inputs { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
  .sbgc__inputs label { display: inline-flex; gap: 6px; align-items: center; color: #546078; }
  .sbgc__inputs input { width: 56px; }
  .sbgc__summary { color: #8d919f; }
  `;

  return {
    mixins: [window.Storyblok.plugin],
    template: `
      <div class="sbgc">
        <div v-if="viewports.length > 1" class="sbgc__tabs" role="group" aria-label="Viewport">
          <button
            v-for="viewport in viewports"
            :key="viewport"
            type="button"
            class="sbgc__tab"
            :class="{ 'is-active': viewport === current }"
            :aria-pressed="String(viewport === current)"
            @click="active = viewport"
          >{{ labels[viewport] }}</button>
        </div>

        <div class="sbgc__ruler" :style="gridStyle" aria-hidden="true">
          <span v-for="n in columns" :key="n">{{ n }}</span>
        </div>

        <div class="sbgc__track" ref="track">
          <div class="sbgc__cols" :style="gridStyle"><span v-for="n in columns" :key="n"></span></div>
          <div
            class="sbgc__bar"
            :class="{ 'is-dragging': drag }"
            :style="barStyle"
            tabindex="0"
            role="slider"
            :aria-label="labels[current] + ' placement: starts at column ' + placement.start + ', spans ' + placement.span"
            aria-valuemin="1"
            :aria-valuemax="columns"
            :aria-valuenow="placement.start"
            @pointerdown="startDrag($event, 'move')"
            @keydown="onKey"
          >
            <span class="sbgc__handle sbgc__handle--start" @pointerdown.stop="startDrag($event, 'start')"></span>
            <span class="sbgc__handle sbgc__handle--end" @pointerdown.stop="startDrag($event, 'end')"></span>
          </div>
        </div>

        <div class="sbgc__inputs">
          <label>Start <input type="number" min="1" :max="columns" :value="placement.start" @change="setFromInput('start', $event)"></label>
          <label>Span <input type="number" min="1" :max="columns" :value="placement.span" @change="setFromInput('span', $event)"></label>
          <span class="sbgc__summary">Columns {{ placement.start }} to {{ placement.start + placement.span - 1 }} of {{ columns }}</span>
        </div>
      </div>
    `,
    data() {
      return {
        labels: LABELS,
        active: null,
        drag: null,
      };
    },
    computed: {
      viewports() {
        const configured = String(this.options.viewports || '')
          .split(',')
          .map(viewport => viewport.trim().toLowerCase())
          .filter(viewport => VIEWPORTS.includes(viewport));

        return configured.length ? VIEWPORTS.filter(viewport => configured.includes(viewport)) : VIEWPORTS;
      },
      current() {
        return this.viewports.includes(this.active) ? this.active : this.viewports[this.viewports.length - 1];
      },
      columns() {
        return this.columnsFor(this.current);
      },
      placement() {
        return this.model[this.current] || { start: 1, span: this.columns };
      },
      gridStyle() {
        return { gridTemplateColumns: `repeat(${this.columns}, minmax(0, 1fr))` };
      },
      barStyle() {
        const n = this.columns;
        const { start, span } = this.placement;
        const column = `(100% - ${(n - 1) * GUTTER}px) / ${n}`;

        return {
          left: `calc(${column} * ${start - 1} + ${(start - 1) * GUTTER}px)`,
          width: `calc(${column} * ${span} + ${(span - 1) * GUTTER}px)`,
        };
      },
    },
    methods: {
      initWith() {
        return {
          plugin: 'grid-control',
        };
      },
      pluginCreated() {
        if (!document.getElementById(STYLE_ID)) {
          const style = document.createElement('style');
          style.id = STYLE_ID;
          style.textContent = STYLES;
          document.head.appendChild(style);
        }

        // New fields start full width; saved values are kept inside the grid if the column count has changed.
        this.viewports.forEach(viewport => {
          const saved = this.model[viewport] || {};
          this.place(viewport, Number(saved.start) || 1, Number(saved.span) || this.columnsFor(viewport));
        });
      },
      columnsFor(viewport) {
        const columns = parseInt(this.options[viewport + '_columns'], 10);

        return columns > 0 ? columns : DEFAULT_COLUMNS[viewport];
      },
      place(viewport, start, span) {
        const columns = this.columnsFor(viewport);
        span = Math.min(Math.max(span, 1), columns);
        start = Math.min(Math.max(start, 1), columns - span + 1);

        const saved = this.model[viewport];

        if (!saved || saved.start !== start || saved.span !== span) {
          this.$set(this.model, viewport, { start, span });
        }
      },
      setFromInput(key, event) {
        const value = parseInt(event.target.value, 10);
        const { start, span } = this.placement;

        if (value > 0) {
          this.place(this.current, key === 'start' ? value : start, key === 'span' ? value : span);
        }

        event.target.value = this.placement[key];
      },
      startDrag(event, mode) {
        event.preventDefault();
        this.$el.querySelector('.sbgc__bar').focus({ preventScroll: true });

        this.drag = {
          mode,
          x: event.clientX,
          columnWidth: this.$refs.track.clientWidth / this.columns,
          start: this.placement.start,
          span: this.placement.span,
        };

        window.addEventListener('pointermove', this.onDrag);
        window.addEventListener('pointerup', this.endDrag, { once: true });
        window.addEventListener('pointercancel', this.endDrag, { once: true });
      },
      onDrag(event) {
        const { mode, x, columnWidth, start, span } = this.drag;
        const moved = Math.round((event.clientX - x) / columnWidth);

        if (mode === 'move') {
          this.place(this.current, start + moved, span);
        } else if (mode === 'end') {
          this.place(this.current, start, span + moved);
        } else {
          // Dragging the start edge keeps the end column where it is.
          const newStart = Math.min(Math.max(start + moved, 1), start + span - 1);
          this.place(this.current, newStart, span + start - newStart);
        }
      },
      endDrag() {
        window.removeEventListener('pointermove', this.onDrag);
        window.removeEventListener('pointerup', this.endDrag);
        window.removeEventListener('pointercancel', this.endDrag);
        this.drag = null;
      },
      onKey(event) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
          return;
        }

        event.preventDefault();
        const step = event.key === 'ArrowRight' ? 1 : -1;
        const { start, span } = this.placement;

        // Arrows move the column; Shift + arrows change its span.
        this.place(this.current, event.shiftKey ? start : start + step, event.shiftKey ? span + step : span);
      },
    },
    beforeDestroy() {
      this.endDrag();
    },
    watch: {
      model: {
        handler(value) {
          this.$emit('changed-model', value);
        },
        deep: true,
      },
    },
  };
})();
