<template>
  <div class="editorSidebar__listItems">
    <!-- eslint-disable vue/no-mutating-props -->
    <draggable
      class="editorSidebar__blocks editorSidebar__categories"
      :class="editorSidebarClasses"
      v-model="renderOrder"
      :item-key="'id'"
      :options="categoryDragOptions"
      @change="saveOrderFromRender"
      @start="isDragging = true"
      @end="isDragging = false; _dragClickSuppressUntil = Date.now() + 250"
    >
      <!--eslint-enable-->

      <div
        v-for="cat in renderOrder"
        :key="cat.id"
        class="editorSidebar__category"
      >
        <button
          @click="onHeaderClick($event, cat.name)"
          class="editorSidebar__categoryHeader"
          type="button"
          :title="`Drag to reorder ${cat.name}`"
        >
          <span class="editorSidebar__categoryTitle">{{ cat.name }}</span>
          <span
            class="editorSidebar__categoryIcon"
            :class="{ 'is-open': !isCollapsed(cat.name) }"
          >▼</span>

          <!-- dedicated drag handle -->
          <span
            class="editorSidebar__dragHandle"
            title="Drag to reorder category"
            @mousedown.stop
            @touchstart.stop
            aria-label="Drag handle"
          >⠿</span>
        </button>

        <transition name="collapse">
          <div v-show="!isCollapsed(cat.name)" class="editorSidebar__panel">
            <draggable
              class="editorSidebar__blocks"
              :class="[editorSidebarClasses]"
              :style="{ backgroundColor: getCategoryColor(cat.name) }"
              :list="blocks"
              :group="{ name: 'editorBlocks', pull: 'clone', put: false }"
              :sort="false"
              handle=".editorSidebar__button"
              @start="isDragging = true"
              @end="isDragging = false; _dragClickSuppressUntil = Date.now() + 250"
            >
              <!-- SINGLE v-for ONLY -->
              <div
                v-for="block in groupedBlocks[cat.name]"
                :key="block.component"
                class="editorSidebar__button"
                :class="{ 'editorSidebar__button--full-width': hasOnlyOneBlock(cat.name) }"
                :data-title="block.title"
                :data-icon="block.icon"
                :data-component="block.component"
                tabindex="0"
              >
                <span v-svg :symbol="iconSymbol(block.icon)"></span>
                <span class="editorSidebar__buttonLabel">
                  {{ block.title }}
                  <span
                    v-if="block.help"
                    class="editorSidebar__helpIcon"
                    :title="block.help"
                    @click.stop
                  >?</span>
                </span>
                <span
                  class="editorSidebar__previewGlyph"
                  title="Preview"
                  aria-hidden="true"
                  @mouseenter="onEyeEnter($event, block)"
                  @mouseleave="onEyeLeave"
                >👁</span>
              </div>
            </draggable>
          </div>
        </transition>
      </div>
    </draggable>

    <!-- Single global popover (no per-item duplicates) -->
    <teleport to="body">
      <div
        v-if="preview.open"
        class="blockPreviewPopover"
        :style="popoverStyle"
        role="dialog"
        aria-live="polite"
        @pointerenter="onPopoverEnter"
        @pointerleave="onPopoverLeave"
      >
        <div
          class="blockPreviewPopover__content"
          :style="resizeBoxStyle"
          ref="resizeBox"
        >
          <iframe
            class="blockPreviewPopover__frame"
            ref="previewFrame"
            :srcdoc="preview.html"
            @load="autosizeIframe"
            style="width: 100%; height: 100%; border: 0;"
          ></iframe>

          <div
            class="blockPreviewPopover__resizeHandle"
            title="Resize"
            @pointerdown.prevent="startResize"
          >⋰</div>
        </div>
      </div>
    </teleport>
  </div>
</template>


<script>
  import {VueDraggableNext} from 'vue-draggable-next'
  import {DraggableMixin} from '@/mixins'
  import tinycolor from 'tinycolor2'

  const DEFAULT_STORAGE_KEY = 'twill:editorSidebarLayout'

  export default {
    name: 'A17EditorSidebarBlockList',
    props: {
      blocks: {
        type: Array,
        default: () => []
      },
      inFieldset: {
        type: Boolean,
        default: false
      },
      storageKey: {type: String, default: DEFAULT_STORAGE_KEY}
    },
    mixins: [DraggableMixin],
    components: {draggable: VueDraggableNext},
    data() {
      return {
        collapsedCategories: {},
        categoryOrder: [],
        renderOrder: [],
        hydrated: false,
        isDragging: false,
        // eslint-disable-next-line vue/no-reserved-keys
        _dragClickSuppressUntil: 0,

        preview: {
          open: false,
          loading: false,
          html: '',
          error: '',
          forType: null,
          anchorRect: null,
          size: { w: 520, h: 360 },
          resized: false
        },
        lockOpen: false,
        previewSizes: Object.create(null), // { [type]: { w, h } }
        isResizing: false,
        previewCache: Object.create(null), // { [type]: html }
        // eslint-disable-next-line vue/no-reserved-keys
        _previewHoverTimer: null,
        // eslint-disable-next-line vue/no-reserved-keys
        _previewCloseTimer: null
      }
    },
    computed: {
      popoverStyle() {
        if (!this.preview.open) return {}

        // Use fixed so it’s relative to the viewport and immune to page scroll jitter
        const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0)
        const vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0)

        const w = this.preview.size?.w || 520
        const h = this.preview.size?.h || 360
        const margin = 8

        // Center, then clamp so we never overflow left/top/right/bottom
        const left = Math.max(margin, Math.min(vw - w - margin, (vw - w) / 2))
        const top  = Math.max(margin, Math.min(vh - h - margin, (vh - h) / 2))

        return {
          position: 'fixed',
          left: `${left}px`,
          top: `${top}px`,
          zIndex: 9999,
          padding: '0'
        }
      },
      resizeBoxStyle() {
        const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0)
        const vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0)
        const margin = 8

        const w = this.preview.size?.w || 520
        const h = this.preview.size?.h || 360

        // Don’t let the rendered box overflow the viewport; keep a tiny margin
        const renderW = Math.min(w, vw - margin * 2)
        const renderH = Math.min(h, vh - margin * 2)

        return {
          width:  renderW + 'px',
          height: renderH + 'px',
          overflow: 'auto' // allow inner scroll if content is taller than renderH
        }
      },
      categoryDragOptions() {
        return {
          handle: '.editorSidebar__dragHandle',
          animation: 180,
          easing: 'ease',
          direction: 'vertical',
          swapThreshold: 0.15,
          invertSwap: true,
          fallbackOnBody: true,
          forceFallback: true,
          ghostClass: 'is-ghost',
          chosenClass: 'is-chosen',
          dragClass: 'is-drag',
          delayOnTouchOnly: true,
          delay: 120,
          touchStartThreshold: 6
        }
      },
      groupedBlocks() {
        return this.blocks.reduce((acc, block) => {
          const category = block.title.split(' ')[0]
          if (!acc[category]) acc[category] = []
          acc[category].push(block)
          return acc
        }, {})
      },
      categoriesFromData() {
        return Object.keys(this.groupedBlocks) // names
      },
      hasOnlyOneBlock() {
        return category => (this.groupedBlocks[category] || []).length === 1
      },
      editorSidebarClasses() {
        return {'editorSidebar__blocks--in-fieldset': this.inFieldset}
      }
    },
    watch: {
      groupedBlocks: {
        immediate: false,
        handler() {
          this.reconcileOrder()
        }
      },
      collapsedCategories: {
        deep: true,
        handler() {
          this.saveCollapsed()
        }
      }
    },
    created() {
      this.loadLayout()
      this.loadSizes()
    },
    mounted() {
      // Run once after mount; if blocks already available, this applies order.
      this.reconcileOrder()
    },
    methods: {
      isOverPopover(evt) {
        const el = document.elementFromPoint(evt.clientX, evt.clientY)
        return !!el && !!el.closest('.blockPreviewPopover')
      },
      hasRenderableHtml(html) {
        if (!html) return false
        const stripped = String(html)
          .replace(/<!--[\s\S]*?-->/g, '')
          .replace(/&nbsp;/g, ' ')
          .trim()
        return stripped.length > 0
      },
      onPopoverEnter() {
        this.lockOpen = true
        clearTimeout(this._previewCloseTimer)
      },
      onPopoverLeave() {
        if (this.isResizing) return // don’t close mid-resize
        this.lockOpen = false
        this._previewCloseTimer = setTimeout(() => {
          if (!this.lockOpen) this.closePreview()
        }, 320)
      },
      sizesStorageKey() { return 'twill:blockPreviewSizes' },
      startResize(e) {
        const box = this.$refs.resizeBox
        if (!box) return

        const handle = e.currentTarget
        try {
          handle.setPointerCapture(e.pointerId)
        } catch (_) {
        }

        const rect = box.getBoundingClientRect()

        this.isResizing = true
        this.lockOpen = true // keep popover open while resizing
        this._resizeTarget = handle
        this._resizeStart = {
          pointerId: e.pointerId,
          mouseX: e.clientX,
          mouseY: e.clientY,
          startW: rect.width,
          startH: rect.height
        }

        // Global listeners (in addition to pointer capture, for robustness)
        window.addEventListener('pointermove', this.onResizeMove, { passive: false })
        window.addEventListener('pointerup', this.stopResize, { passive: false })
        window.addEventListener('pointercancel', this.stopResize, { passive: false })

        document.body.style.cursor = 'nwse-resize'
        document.body.style.userSelect = 'none'
      },

      onResizeMove(e) {
        if (!this.isResizing) return
        if (this._resizeStart?.pointerId != null && e.pointerId != null && e.pointerId !== this._resizeStart.pointerId) {
          return
        }

        const dx = e.clientX - this._resizeStart.mouseX
        const dy = e.clientY - this._resizeStart.mouseY

        // Allow free resize; just guard against collapsing to zero
        const w = Math.max(50, this._resizeStart.startW + dx)
        const h = Math.max(50, this._resizeStart.startH + dy)

        this.preview.size = { w, h }
        this.preview.resized = true

        if (this.preview.forType) this.setSizeFor(this.preview.forType, { w, h })
      },

      stopResize(e) {
        if (!this.isResizing) return
        this.isResizing = false

        if (this._resizeTarget && this._resizeStart?.pointerId != null) {
          try { this._resizeTarget.releasePointerCapture(this._resizeStart.pointerId) } catch (_) {}
        }

        window.removeEventListener('pointermove', this.onResizeMove)
        window.removeEventListener('pointerup', this.stopResize)
        window.removeEventListener('pointercancel', this.stopResize)

        document.body.style.cursor = ''
        document.body.style.userSelect = ''

        this._resizeTarget = null
        this._resizeStart = null

        if (e && this.isOverPopover(e)) {
          this.lockOpen = true
          return
        }
        this.lockOpen = false
        this.scheduleClose()
      },
      loadSizes() {
        try {
          const raw = localStorage.getItem(this.sizesStorageKey()) || '{}'
          this.previewSizes = JSON.parse(raw)
        } catch { this.previewSizes = Object.create(null) }
      },

      saveSizes() {
        try {
          localStorage.setItem(this.sizesStorageKey(), JSON.stringify(this.previewSizes))
        } catch {}
      },

      getDefaultSize() {
        // sensible defaults
        return { w: 520, h: 360 }
      },

      getSizeFor(type) {
        return this.previewSizes[type] || this.getDefaultSize()
      },

      setSizeFor(type, size) {
        this.previewSizes[type] = { w: Math.round(size.w), h: Math.round(size.h) }
        this.saveSizes()
      },
      onEyeEnter(evt, block) {
        if (this.isDragging || Date.now() < this._dragClickSuppressUntil) return
        const el = evt.currentTarget
        const rect = el.getBoundingClientRect()
        clearTimeout(this._previewCloseTimer)
        clearTimeout(this._previewHoverTimer)
        this._previewHoverTimer = setTimeout(() => {
          this.openPreview(block.component, rect)
        }, 120)
      },
      onEyeLeave() {
        clearTimeout(this._previewHoverTimer)
        this._previewCloseTimer = setTimeout(() => {
          if (!this.lockOpen) this.closePreview()
        }, 320)
      },
      autosizeIframe() {
        const iframe = this.$refs.previewFrame
        if (!iframe) return
        if (this.preview.resized) return

        try {
          const doc = iframe.contentDocument || iframe.contentWindow?.document
          if (!doc) return
          const body = doc.body
          const html = doc.documentElement
          const naturalH = Math.max(body?.scrollHeight || 0, html?.scrollHeight || 0)
          const h = Math.min(Math.max(naturalH, 240), 520)
          // Keep current width, adjust height only if not resized
          this.preview.size = { w: this.preview.size?.w || 520, h }
        } catch (e) { /* ignore */ }
      },
      iconSymbol: function (icon) {
        // Future block editor icons will have two variations: small and large.
        // Small formats will be used by default in the dropdown, and large
        // formats (named with `-lg` suffix) will be used in the sidebar.
        return this.hasLgIconVariation(icon) ? `${icon}-lg` : icon
      },
      hasLgIconVariation(icon) {
        return Boolean(document.querySelector(`#icon--${icon}-lg`))
      },
      closePreview() {
        this.preview.open = false
        this.preview.loading = false
        this.preview.error = ''
        this.preview.html = ''
        this.preview.forType = null
        this.preview.anchorRect = null
      },
      scheduleClose() {
        clearTimeout(this._previewCloseTimer)
        this._previewCloseTimer = setTimeout(() => {
          if (!this.lockOpen && !this.isResizing) this.closePreview()
        }, 300)
      },
      async fetchPreview(type) {
        const url = `${window.location.origin}/admin/blocks/sidebar-preview?type=${encodeURIComponent(type)}`
        const res = await fetch(url, {credentials: 'include', headers: {'Accept': 'text/html'}})
        if (!res.ok) {
          const text = await res.text()
          throw new Error(text || `HTTP ${res.status}`)
        }
        return await res.text()
      },
      openPreview(type, rect) {
        this.preview.forType = type
        this.preview.anchorRect = rect
        this.preview.error = ''

        // apply remembered size immediately
        const { w, h } = this.getSizeFor(type)
        this.preview.size = { w, h }
        this.preview.resized = true

        // If we already know the cache outcome, respect it
        if (Object.prototype.hasOwnProperty.call(this.previewCache, type)) {
          const html = this.previewCache[type]
          if (this.hasRenderableHtml(html)) {
            this.preview.html = html
            this.preview.open = true
          } else {
            this.preview.open = false
            this.preview.html = ''
          }
          return
        }
        this.preview.html = ''
        this.preview.open = false

        this.fetchPreview(type)
          .then(html => {
            if (this.preview.forType !== type) return

            const has = this.hasRenderableHtml(html)
            this.previewCache[type] = has ? html : null

            if (has) {
              this.preview.html = html
              this.preview.open = true
            } else {
              // No preview: keep it hidden
              this.preview.html = ''
              this.preview.open = false
            }
          })
          .catch(() => {
            if (this.preview.forType !== type) return
            this.previewCache[type] = null
            this.preview.open = false
            this.preview.html = ''
          })
      },
      // safer header click: ignore when dragging/just finished a drag or if clicking handle
      onHeaderClick(evt, name) {
        const now = Date.now()
        if (this.isDragging || now < this._dragClickSuppressUntil) return
        if (evt.target.closest('.editorSidebar__dragHandle')) return
        this.toggleCollapse(name)
      },

      // ---------- collapse ----------
      toggleCollapse(name) {
        this.collapsedCategories[name] = !this.isCollapsed(name)
      },
      isCollapsed(name) {
        return !!this.collapsedCategories[name]
      },

      // ---------- colors ----------
      getCategoryColor(name) {
        const hash = name
          .split('')
          .reduce((acc, ch) => ch.charCodeAt(0) + ((acc << 5) - acc), 0)
        return tinycolor({h: hash % 360, s: 30, l: 97}).toHexString()
      },

      // ---------- ids & mapping ----------
      catId(name) {
        const slug = String(name)
          .toLowerCase()
          .replace(/\s+/g, '-')
        let h = 0
        for (let i = 0; i < name.length; i++)
          h = ((h << 5) - h + name.charCodeAt(i)) | 0
        return `${slug}__${Math.abs(h)}`
      },
      toObjects(names) {
        return names.map(n => ({id: this.catId(n), name: n}))
      },
      toNames(objs) {
        return objs.map(o => o.name)
      },

      // ---------- persistence ----------
      storageRead() {
        try {
          return JSON.parse(localStorage.getItem(this.storageKey) || '{}')
        } catch {
          return {}
        }
      },
      storageWrite(payload) {
        localStorage.setItem(this.storageKey, JSON.stringify(payload))
      },
      loadLayout() {
        const {order = [], collapsed = {}} = this.storageRead()
        this.categoryOrder = Array.isArray(order) ? order : []
        this.collapsedCategories =
          collapsed && typeof collapsed === 'object' ? collapsed : {}
        this.hydrated = true
      },
      saveOrderFromRender() {
        if (!this.hydrated) return
        this.categoryOrder = this.toNames(this.renderOrder)
        const saved = this.storageRead()
        this.storageWrite({...saved, order: this.categoryOrder})
      },
      saveCollapsed() {
        if (!this.hydrated) return
        const saved = this.storageRead()
        this.storageWrite({...saved, collapsed: this.collapsedCategories})
      },

      // Keep user order; add/remove categories as data changes; compute visible & render objects
      reconcileOrder() {
        if (!this.hydrated) return

        const present = this.categoriesFromData // names available now
        // If blocks haven't populated yet, don't write back; just show saved order in UI if any
        if (present.length === 0) {
          if (this.categoryOrder.length && this.renderOrder.length === 0) {
            this.renderOrder = this.toObjects(this.categoryOrder)
          }
          return
        }

        let base = this.categoryOrder.length
          ? [...this.categoryOrder]
          : [...present]

        // remove names no longer present
        base = base.filter(n => present.includes(n))
        // append new names at the end
        const missing = present.filter(n => !base.includes(n))
        if (missing.length) base.push(...missing)

        // visible = only categories that currently have blocks
        const visibleNames = base.filter(
          n => (this.groupedBlocks[n] || []).length > 0
        )
        const nextObjects = this.toObjects(visibleNames)

        // update renderOrder only if changed (avoid churn)
        if (JSON.stringify(this.renderOrder) !== JSON.stringify(nextObjects)) {
          this.renderOrder = nextObjects
        }

        // Only persist when we actually have present categories (avoid overwriting with [])
        if (JSON.stringify(this.categoryOrder) !== JSON.stringify(base)) {
          this.categoryOrder = base
          const saved = this.storageRead()
          this.storageWrite({...saved, order: this.categoryOrder})
        }

        // scrub collapsed keys for removed categories
        const cleaned = {}
        for (const n of present) {
          if (
            Object.prototype.hasOwnProperty.call(this.collapsedCategories, n)
          ) {
            cleaned[n] = this.collapsedCategories[n]
          }
        }
        if (
          JSON.stringify(cleaned) !== JSON.stringify(this.collapsedCategories)
        ) {
          this.collapsedCategories = cleaned
          this.saveCollapsed()
        }
      }
    }
  }
</script>

<style lang="scss" scoped>
  @import '~styles/setup/_mixins-colors-vars.scss';

  .editorSidebar__listItems {
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
  }

  .editorSidebar__listItems > .editorSidebar__blocks.editorSidebar__categories {
    flex: 1 1 auto;
    min-height: 0;
    height: 100%;
    max-height: 100%;
    overflow-y: scroll !important;
    overflow-x: hidden !important;
    -webkit-overflow-scrolling: touch;
    padding: 0 !important;
    border: 0 !important;
    gap: 10px;
    scrollbar-gutter: stable both-edges;
  }

  .editorSidebar__categories,
  .editorSidebar__panel {
    min-width: 0;
  }

  .editorSidebar__categories {
    display: flex;
    flex-direction: column;
  }

  .editorSidebar__category {
    width: 100%;
    min-height: var(--cat-min-h, 44px);
    position: relative;
    contain: layout paint;
  }

  .editorSidebar__categoryHeader {
    @include btn-reset;
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: $color__background;
    border-radius: $border-radius;
    border: 1px solid $color__border;
    user-select: none;
    -webkit-user-drag: none;

    &:hover {
      border-color: $color__border--focus;
    }
  }

  .editorSidebar__categoryTitle {
    font-weight: 600;
    color: $color__text;
  }

  .editorSidebar__categoryIcon {
    font-size: 10px;
    transition: transform 0.2s ease;
    color: $color__text--light;

    &.is-open {
      transform: rotate(180deg);
    }
  }

  /* dedicated drag handle (only draggable hotspot) */
  .editorSidebar__dragHandle {
    margin-left: auto;
    padding: 4px 6px;
    border-radius: 6px;
    cursor: grab;
    font-size: 14px;
    line-height: 1;
    color: $color__text--light;

    &:active {
      cursor: grabbing;
    }

    &:hover {
      background: rgba(0, 0, 0, 0.05);
      color: $color__text;
    }
  }

  .editorSidebar__panel {
    overflow: hidden;
  }

  .collapse-enter-active,
  .collapse-leave-active {
    transition: max-height 0.3s ease-out, opacity 0.2s ease-out;
    max-height: 1000px;
  }

  .collapse-enter,
  .collapse-leave-to {
    max-height: 0;
    opacity: 0;
  }

  .editorSidebar__blocks {
    box-sizing: border-box;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-start;
    gap: 10px;
    width: 100%;
    max-height: none;
    border-radius: $border-radius;
    padding: 10px;
    border: 1px solid rgba(0, 0, 0, 0.05);
    overflow-x: hidden;
  }

  .editorSidebar__blocks--in-fieldset {
    padding-top: 20px;

    .editorSidebar__button:last-child {
      padding-bottom: 0;
    }
  }

  .editorSidebar__button {
    @include btn-reset;
    @include font-tiny-btn;
    box-sizing: border-box;
    cursor: move;
    display: flex;
    flex-direction: column;
    flex: 1 1 calc(50% - 10px);
    height: 100px;
    padding: 8px 20px;
    margin: 0;
    background: $color__background;
    border-radius: $border-radius;
    border: 1px solid $color__border;
    color: $color__text--light;
    text-align: center;

    &--full-width {
      flex-basis: 100%;
    }

    .icon {
      flex-grow: 1;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: $color__icons;
    }

    .editorSidebar__buttonLabel {
      width: 100%;
      line-height: 1.2;
      white-space: normal;
      word-break: break-word;
      overflow-wrap: anywhere;
    }

    &:hover,
    &:focus {
      color: $color__text;
      border-color: $color__border--focus;

      .icon {
        color: $color__text;
      }
    }
  }

  .editorPreview__content .editorSidebar__button {
    width: 100%;
  }

  .is-ghost {
    opacity: 0.6;
    min-height: var(--cat-min-h, 44px);
    background: rgba(0, 0, 0, 0.02);
    border: 1px dashed rgba(0, 0, 0, 0.1);
  }

  .is-chosen {
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.06) inset;
  }

  .is-drag {
    cursor: grabbing;
  }

  .editorSidebar__buttonLabel {
    width: 100%;
    line-height: 1.2;
    white-space: normal;
    word-break: break-word;
    overflow-wrap: anywhere;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
  }

  .editorSidebar__helpIcon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    border-radius: 50%;
    background: $color__border;
    color: $color__background;
    font-size: 11px;
    font-weight: 600;
    cursor: help;
    transition: all 0.2s ease;

    &:hover {
      background: $color__text;
      transform: scale(1.1);
    }
  }

  .editorSidebar__button {
    @include btn-reset;
    @include font-tiny-btn;
    box-sizing: border-box;
    cursor: move;
    display: flex;
    flex-direction: column;
    flex: 1 1 calc(50% - 10px);
    height: 100px;
    padding: 8px 20px;
    margin: 0;
    background: $color__background;
    border-radius: $border-radius;
    border: 1px solid $color__border;
    color: $color__text--light;
    text-align: center;

    &--full-width {
      flex-basis: 100%;
    }

    .icon {
      flex-grow: 1;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: $color__icons;
    }

    &:hover,
    &:focus {
      color: $color__text;
      border-color: $color__border--focus;

      .icon {
        color: $color__text;
      }
    }
  }

  .editorSidebar__button {
    position: relative;
  }

  .editorSidebar__previewGlyph {
    position: absolute;
    right: 6px;
    bottom: 6px;
    font-size: 12px;
    opacity: .55;
    transition: opacity .15s ease, transform .15s ease;
    cursor: pointer;
    pointer-events: auto;
  }

  .editorSidebar__button:hover .editorSidebar__previewGlyph {
    opacity: .95;
    transform: translateY(-1px);
  }
</style>
<style lang="scss">
  .blockPreviewPopover {
    position: fixed;
    background: #0f172a;
    border-radius: 10px;
    box-shadow: 0 12px 36px rgba(2, 6, 23, .35);
    color: #fff;
    padding: 8px;
    border: 1px solid rgba(255, 255, 255, .1);
  }

  .blockPreviewPopover__content {
    position: relative;
    background: #fff;
    color: #0f172a;
    border-radius: 8px;
    overflow: auto;
    padding: 0;
    box-sizing: border-box;
  }

  .blockPreviewPopover__resizeHandle {
    position: absolute;
    right: 4px;
    bottom: 4px;
    width: 24px;
    height: 24px;
    display: grid;
    place-items: center;
    font-size: 16px;
    line-height: 1;
    color: #334155;
    background: rgba(15, 23, 42, 0.06);
    border-radius: 6px;
    cursor: nwse-resize;
    user-select: none;
  }

  .blockPreviewPopover__resizeHandle::after {
    content: '';
    position: absolute;
    inset: -8px;
  }
  .blockPreviewPopover__resizeHandle:hover {
    background: rgba(15, 23, 42, 0.12);
  }

  .editorSidebar__previewGlyph {
    position: absolute;
    right: 6px;
    bottom: 6px;
    font-size: 12px;
    opacity: .55;
    transition: opacity .15s ease, transform .15s ease;
    cursor: pointer;
    pointer-events: auto;
  }

  .blockPreviewPopover__loading,
  .blockPreviewPopover__error {
    padding: 8px 10px;
    font-size: 13px;
    color: #e2e8f0;
  }

  .blockPreviewPopover__error {
    color: #fecaca;
  }
</style>
